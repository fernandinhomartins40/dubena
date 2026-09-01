<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * F1-10 — copia a titularidade JÁ APROVADA para a coluna que a RLS lê.
 *
 * ## O problema que isto resolve
 *
 * A decisão de titularidade existe desde 2026-08-27: `tenant_companies` tem os
 * vínculos com `status = 'APPROVED'`, e `empresas.ownership_status` diz
 * `OWNERSHIP_APPROVED`.
 *
 * Só que a RLS **não lê nenhum dos dois**. Ela compara
 * `empresas.tenant_account_id` com o tenant do envelope
 * (`app_tenant_can_read`), e essa coluna está **nula em todas as empresas**.
 *
 * O efeito, conferido em homologação: a RLS não alcança empresa nenhuma. Uma
 * revenda faz login e não enxerga o próprio dado — sem erro em log, sem sintoma
 * além do "sumiu tudo" que chega ao suporte.
 *
 * ## O que este comando NÃO faz
 *
 * **Não decide titularidade.** A migration `2026_08_29_000300` é explícita:
 * *"Nenhuma migration faz backfill: F1-10 só poderá preencher a partir de
 * `tenant_companies` aprovado"*. E o plano proíbe copiar automaticamente a
 * fronteira de `grupo`.
 *
 * Então a regra aqui é estreita de propósito: copia **apenas** o que já está
 * `APPROVED`, e só isso. Empresa sem vínculo aprovado fica como está — inclusive
 * a `OWNERSHIP_UNRESOLVED`, que é o caso do registro de seed que nunca foi
 * revenda.
 *
 * Inventar o dono de uma empresa é o erro que este comando existe para não
 * cometer: um `tenant_account_id` errado não vaza dado (a RLS ainda exige grant
 * aprovado por membership), mas coloca a empresa sob a conta errada — e o
 * conserto depois do cutover é muito mais caro que a espera agora.
 *
 * ## Por que idempotente e com `--dry-run`
 *
 * Roda contra o banco real. `--dry-run` é o padrão implícito da casa para
 * qualquer escrita em produção: mostra o que faria, não faz. E reexecutar não
 * pode causar dano — quem já está preenchido corretamente é ignorado.
 */
class TenantPreencherChaveDasEmpresas extends Command
{
    protected $signature = 'tenant:preencher-chave
        {--dry-run : mostra o que faria e NÃO grava}
        {--empresa=* : limita a estas empresas (por id)}';

    protected $description = 'F1-10 — preenche empresas.tenant_account_id a partir dos vínculos APROVADOS. Não decide titularidade.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $filtro = array_map('intval', (array) $this->option('empresa'));

        $this->info('== tenant:preencher-chave — F1-10 ==');
        $this->line($dryRun ? 'Modo DRY-RUN: nada será gravado.' : 'Modo REAL: as alterações serão gravadas.');
        $this->newLine();

        $candidatas = $this->candidatas($filtro);

        if ($candidatas === []) {
            // Zero candidatas pode ser "já está tudo certo" ou "não consegui
            // ler" — e as duas não podem se parecer. O diagnóstico abaixo
            // distingue.
            return $this->explicarVazio($filtro);
        }

        $aplicar = [];
        $conflitos = [];

        foreach ($candidatas as $c) {
            // Uma empresa vinculada a DOIS tenants aprovados é ambiguidade real,
            // e ambiguidade não se resolve por desempate automático — foi
            // exatamente esse tipo de escolha silenciosa que o plano proibiu.
            if ((int) $c->tenants_aprovados > 1) {
                $conflitos[] = "empresa {$c->empresa_id}: {$c->tenants_aprovados} tenants aprovados";

                continue;
            }

            $aplicar[] = $c;
        }

        $this->table(
            ['empresa', 'razão social', 'tenant', 'titular', 'atual'],
            array_map(fn ($c) => [
                $c->empresa_id,
                mb_strimwidth((string) $c->razao_social, 0, 34, '…'),
                $c->tenant_account_id,
                mb_strimwidth((string) $c->legal_name, 0, 28, '…'),
                $c->atual === null ? '(vazio)' : $c->atual,
            ], $aplicar),
        );

        if ($conflitos !== []) {
            $this->newLine();
            $this->error('AMBIGUIDADE — estas empresas NÃO serão tocadas:');

            foreach ($conflitos as $c) {
                $this->error('  '.$c);
            }

            $this->line('Titularidade ambígua é decisão humana; o comando não desempata.');
        }

        $this->newLine();

        if ($dryRun) {
            $this->info(count($aplicar).' empresa(s) seriam atualizadas. Nada foi gravado.');
            $this->line('Rode sem --dry-run para aplicar.');

            return $conflitos === [] ? self::SUCCESS : self::FAILURE;
        }

        $gravadas = $this->aplicar($aplicar);

        $this->info("{$gravadas} empresa(s) atualizadas.");
        $this->line('A RLS passa a alcançar estas empresas quando houver envelope de tenant.');

        return $conflitos === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * A conexão de OWNER — obrigatória aqui, não conveniência.
     *
     * `empresas` está sob RLS e a comparação é justamente com
     * `tenant_account_id`. Pela conexão do runtime (`erp_app`, sem envelope de
     * tenant) o comando enxerga **zero empresas** — foi o que aconteceu na
     * primeira tentativa contra o banco real, e o diagnóstico saiu como
     * "Nenhuma empresa no banco".
     *
     * É circular por natureza: o comando precisa ler as empresas para
     * preenchê-las, e não as enxerga porque ainda não estão preenchidas. A
     * escrita tem o mesmo problema — o `WITH CHECK` recusaria o update.
     *
     * Só troca de conexão em PostgreSQL: em sqlite não há RLS e `pgsql_owner`
     * aponta para outro lugar, o que reintroduziria a cegueira que este método
     * existe para evitar.
     */
    private function owner(): ConnectionInterface
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return DB::connection();
        }

        try {
            return DB::connection('pgsql_owner');
        } catch (\Throwable) {
            return DB::connection();
        }
    }

    /**
     * As empresas cuja titularidade JÁ foi aprovada e que ainda não têm a chave.
     *
     * O `is distinct from` (e não `!=`) é deliberado: com `tenant_account_id`
     * nulo, `e.tenant_account_id != tc.tenant_account_id` devolve NULL, não
     * TRUE — e nenhuma linha seria selecionada. Seria o comando reportando
     * "nada a fazer" com o banco inteiro por preencher.
     *
     * @param  list<int>  $filtro
     * @return list<object>
     */
    private function candidatas(array $filtro): array
    {
        $sql = <<<'SQL'
            SELECT e.id                          AS empresa_id,
                   e.razao_social,
                   e.tenant_account_id           AS atual,
                   tc.tenant_account_id,
                   ta.legal_name,
                   (SELECT count(*)
                      FROM tenant_companies x
                     WHERE x.empresa_id = e.id
                       AND x.status = 'APPROVED') AS tenants_aprovados
              FROM empresas e
              JOIN tenant_companies tc ON tc.empresa_id = e.id
                                      AND tc.status = 'APPROVED'
              JOIN tenant_accounts ta ON ta.id = tc.tenant_account_id
             WHERE e.ownership_status = 'OWNERSHIP_APPROVED'
               AND e.tenant_account_id IS DISTINCT FROM tc.tenant_account_id
        SQL;

        $bind = [];

        if ($filtro !== []) {
            $sql .= ' AND e.id IN ('.implode(',', array_fill(0, count($filtro), '?')).')';
            $bind = $filtro;
        }

        return $this->owner()->select($sql.' ORDER BY e.id', $bind);
    }

    /**
     * Grava, uma empresa por vez e dentro de transação.
     *
     * Uma por vez porque um `update ... from` em massa esconderia qual linha
     * falhou; o volume aqui é de dezenas, não de milhares.
     *
     * @param  list<object>  $aplicar
     */
    private function aplicar(array $aplicar): int
    {
        return $this->owner()->transaction(function () use ($aplicar) {
            $gravadas = 0;

            foreach ($aplicar as $c) {
                $gravadas += $this->owner()->table('empresas')
                    ->where('id', $c->empresa_id)
                    // Só grava se ainda estiver como o comando leu. Entre a
                    // leitura e a escrita alguém pode ter mexido, e sobrescrever
                    // sem olhar é como se perde uma decisão de titularidade.
                    ->where(fn ($q) => $c->atual === null
                        ? $q->whereNull('tenant_account_id')
                        : $q->where('tenant_account_id', $c->atual))
                    ->update([
                        'tenant_account_id' => $c->tenant_account_id,
                        'updated_at' => now(),
                    ]);
            }

            return $gravadas;
        });
    }

    /**
     * Distingue "já está tudo certo" de "não há o que ler".
     *
     * Imprimir sucesso sobre uma lista vazia é o defeito que esta base persegue
     * desde o registry que dizia "ETL concluído" sem ter migrado nada.
     *
     * @param  list<int>  $filtro
     */
    private function explicarVazio(array $filtro): int
    {
        $totalEmpresas = (int) $this->owner()->table('empresas')->count();
        $aprovadas = (int) $this->owner()->table('tenant_companies')->where('status', 'APPROVED')->count();
        $jaPreenchidas = (int) $this->owner()->table('empresas')->whereNotNull('tenant_account_id')->count();
        $semDono = (int) $this->owner()->table('empresas')->where('ownership_status', 'OWNERSHIP_UNRESOLVED')->count();

        if ($totalEmpresas === 0) {
            $this->error('Nenhuma empresa no banco — não há o que preencher, e isso não é sucesso.');

            return self::FAILURE;
        }

        if ($aprovadas === 0) {
            $this->error('Nenhum vínculo APPROVED em tenant_companies.');
            $this->line('A titularidade precisa ser decidida e aprovada ANTES — este comando só copia o que já foi.');

            return self::FAILURE;
        }

        $this->info('Nada a fazer: toda empresa com titularidade aprovada já tem a chave preenchida.');
        $this->line("  empresas: {$totalEmpresas} | com chave: {$jaPreenchidas} | vínculos aprovados: {$aprovadas}");

        if ($semDono > 0 && $filtro === []) {
            $this->newLine();
            $this->warn("{$semDono} empresa(s) com OWNERSHIP_UNRESOLVED ficam de fora, por decisão.");
            $this->line('São registros sem titularidade aprovada — o comando não inventa dono.');
        }

        return self::SUCCESS;
    }
}
