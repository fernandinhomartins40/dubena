<?php

namespace App\Etl\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Carga que PRESERVA os ids do legado.
 *
 * Por que não `Model::updateOrCreate(['id' => $x], ...)`: `id` não está no
 * `$fillable` dos models (e não deve estar), então o Eloquent descarta o id
 * informado e o auto-increment escolhe outro. As FKs do dump (bairro→cidade,
 * cliente→cidade, pedido→cliente) passariam a apontar para a linha errada — e
 * quando a cidade referenciada tivesse id alto, para nenhuma.
 *
 * Escreve pelo Query Builder, em lotes, com `upsert` (recarga idempotente), e
 * ressincroniza a sequence no fim para o próximo insert da aplicação não colidir
 * com um id já ocupado pela carga.
 *
 * ## A conexão vem do CONTEXTO, não do default
 *
 * Este trait é usado por **22 dos 23 migradores**, e usava `DB::connection()`
 * fixo — a conexão do runtime (`erp_app`), sob RLS.
 *
 * Depois que a RLS de tenant entrou, isso quebrou o ETL inteiro em silêncio: a
 * leitura devolvia zero e a escrita era recusada com
 * `new row violates row-level security policy`. Como cada migrador trata
 * "referência ausente" descartando a linha, o resultado era descartar quase
 * tudo e sair com SUCESSO.
 *
 * O ETL cria os tenants; não pode estar sujeito ao escopo deles. Ver
 * `MigrationContext::novo()`.
 */
trait PreservaIdsDoLegado
{
    /**
     * A conexão de destino, injetada pelo migrador a partir do contexto.
     *
     * Nula significa "ainda não recebi contexto" — e aí cai no default, que é o
     * comportamento antigo. Cada migrador chama `usarConexaoDe($ctx)` no início
     * do `migrar()`; o guardião `EtlEnxergaODestinoTest` reprova quem esquecer.
     */
    private ?ConnectionInterface $conexaoDestino = null;

    /** Liga este trait à conexão de destino do contexto (owner, no Postgres). */
    protected function usarConexaoDe(MigrationContext $ctx): void
    {
        $this->conexaoDestino = $ctx->novo();
    }

    /** A conexão em que este trait lê e escreve o DESTINO. */
    protected function destino(): ConnectionInterface
    {
        return $this->conexaoDestino ?? DB::connection();
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @param  list<string>  $chave  colunas que identificam a linha (default: id)
     */
    protected function gravarPreservandoId(
        string $tabela,
        array $linhas,
        array $chave = ['id'],
        int $lote = 500,
    ): int {
        if ($linhas === []) {
            return 0;
        }

        $agora = now();
        foreach ($linhas as &$l) {
            if (! array_key_exists('created_at', $l) || $l['created_at'] === null) {
                $l['created_at'] = $agora;
            }
            $l['updated_at'] = $agora;
        }
        unset($l);

        $linhas = $this->carimbarChaveDeTenant($tabela, $linhas);

        $conexao = $this->destino();
        foreach (array_chunk($linhas, $lote) as $bloco) {
            $conexao->table($tabela)->upsert($bloco, $chave);
        }

        $this->ressincronizarSequence($tabela);

        return count($linhas);
    }

    /** @var array<string, bool> tabela => tem a coluna `tenant_account_id` */
    private array $temChaveDeTenant = [];

    /** @var array{empresa: array<int,int>, grupo: array<int,int>}|null */
    private ?array $titularidadeAprovada = null;

    /**
     * Preenche `tenant_account_id` no que o ETL grava — quando a titularidade
     * JÁ está aprovada.
     *
     * Sem isto a recarga não funciona. Provado em homologação (2026-10-11): com
     * as chaves preenchidas pelo pós-carga, refazer o `etl:run` parou em
     * `financeiros` — o gatilho `app_enforce_financial_configuration_tenant`
     * recusa a linha que chega sem tenant apontando para um plano de contas que
     * já tem. E a recarga final é exatamente o que o cutover precisa fazer.
     *
     * A fonte é a mesma do pós-carga, e só ela: `tenant_companies` APPROVED
     * para tabela com `empresa_id`, `tenant_legacy_group_scopes` APPROVED para
     * configuração por grupo. Nada é inferido.
     *
     * Na PRIMEIRA carga não há titularidade (o tenant nasce depois das
     * empresas): os mapas vêm vazios e a linha segue sem a coluna, como sempre
     * foi — quem preenche é `saas:tenant:preencher-chave-operacional`.
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function carimbarChaveDeTenant(string $tabela, array $linhas): array
    {
        $primeira = $linhas[0];
        // `empresas` fica de fora: a chave DELA é a própria decisão de
        // titularidade (`tenant:preencher-chave`), e derivá-la do grupo é
        // justamente o que o plano proíbe.
        if ($tabela === 'empresas' || array_key_exists('tenant_account_id', $primeira)) {
            return $linhas;
        }

        $escopo = array_key_exists('empresa_id', $primeira) ? 'empresa'
            : (array_key_exists('grupo_id', $primeira) ? 'grupo' : null);
        if ($escopo === null) {
            return $linhas;
        }

        $conexao = $this->destino();
        $this->temChaveDeTenant[$tabela] ??= $conexao->getSchemaBuilder()->hasColumn($tabela, 'tenant_account_id');
        if (! $this->temChaveDeTenant[$tabela]) {
            return $linhas;
        }

        $mapa = $this->titularidadeAprovada()[$escopo];
        if ($mapa === []) {
            return $linhas;
        }

        // Todas as linhas recebem a coluna (o upsert exige o mesmo formato), e
        // a de empresa/grupo sem aprovação recebe NULL — que é o estado dela.
        foreach ($linhas as &$l) {
            $dono = $l[$escopo.'_id'] ?? null;
            $l['tenant_account_id'] = $dono !== null ? ($mapa[(int) $dono] ?? null) : null;
        }
        unset($l);

        return $linhas;
    }

    /** @return array{empresa: array<int,int>, grupo: array<int,int>} */
    private function titularidadeAprovada(): array
    {
        if ($this->titularidadeAprovada !== null) {
            return $this->titularidadeAprovada;
        }

        $mapas = ['empresa' => [], 'grupo' => []];
        try {
            $conexao = $this->destino();
            foreach ($conexao->table('tenant_companies')->where('status', 'APPROVED')->get(['empresa_id', 'tenant_account_id']) as $v) {
                $mapas['empresa'][(int) $v->empresa_id] = (int) $v->tenant_account_id;
            }
            foreach ($conexao->table('tenant_legacy_group_scopes')->where('status', 'APPROVED')->get(['grupo_id', 'tenant_account_id']) as $v) {
                $mapas['grupo'][(int) $v->grupo_id] = (int) $v->tenant_account_id;
            }
        } catch (\Throwable) {
            // Banco anterior à F1 (sem as tabelas de fronteira): não há o que
            // carimbar, e a carga segue como antes.
        }

        return $this->titularidadeAprovada = $mapas;
    }

    /**
     * Anula referências que não existem na tabela-alvo.
     *
     * O dump referencia linhas que podem não ter sobrevivido às etapas
     * anteriores da carga (ex.: rua descartada por falta de cidade). Para FK
     * NULLABLE isso vira null — preferível a derrubar a carga inteira por uma
     * referência solta. Para FK obrigatória, a linha tem de ser descartada pelo
     * migrator (aqui não se inventa valor).
     *
     * @param  list<array<string, mixed>>  $linhas
     * @param  array<string, string>  $fks  coluna => tabela referenciada
     * @return list<array<string, mixed>>
     */
    protected function anularFksInvalidas(array $linhas, array $fks): array
    {
        if ($linhas === []) {
            return $linhas;
        }

        foreach ($fks as $coluna => $tabela) {
            $refs = [];
            foreach ($linhas as $l) {
                $v = $l[$coluna] ?? null;
                if ($v !== null && $v !== '') {
                    $refs[(int) $v] = true;
                }
            }
            if ($refs === []) {
                continue;
            }

            $existentes = [];
            foreach (array_chunk(array_keys($refs), 5000) as $bloco) {
                foreach ($this->destino()->table($tabela)->whereIn('id', $bloco)->pluck('id') as $id) {
                    $existentes[(int) $id] = true;
                }
            }

            foreach ($linhas as &$l) {
                $v = $l[$coluna] ?? null;
                if ($v !== null && $v !== '' && ! isset($existentes[(int) $v])) {
                    $l[$coluna] = null;
                }
            }
            unset($l);
        }

        return $linhas;
    }

    /** Deixa a sequence acima do maior id carregado (só faz sentido no Postgres). */
    protected function ressincronizarSequence(string $tabela): void
    {
        $conexao = $this->destino();
        if ($conexao->getDriverName() !== 'pgsql') {
            return;
        }

        $conexao->statement(
            "SELECT setval(pg_get_serial_sequence(?, 'id'), "
            ."COALESCE((SELECT MAX(id) FROM {$tabela}), 1))",
            [$tabela]
        );
    }
}
