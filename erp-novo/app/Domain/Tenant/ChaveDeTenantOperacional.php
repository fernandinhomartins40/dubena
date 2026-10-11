<?php

namespace App\Domain\Tenant;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Preenche `tenant_account_id` nas tabelas operacionais (classe COMPANY) a
 * partir do vínculo documental APROVADO da empresa de cada linha.
 *
 * ## Por que existe
 *
 * A regra já estava escrita — na migration `2026_08_29_000800`. Só que migration
 * roda UMA vez, e em homologação rodou com o banco vazio. A carga real veio
 * depois (2026-10-11): o ETL grava por `DB::table`, sem model e sem envelope,
 * então 3,5 milhões de linhas entraram com a chave nula. A policy canônica
 * compara `tenant_account_id` com o tenant da sessão, e nulo não é igual a nada:
 * o dono fez login, a API respondeu 200 e listou zero clientes, zero pedidos.
 *
 * Sem erro em lugar nenhum — é a RLS funcionando sobre dado incompleto.
 *
 * ## O que NÃO faz
 *
 * Não decide titularidade (mesma fronteira de `tenant:preencher-chave`): só
 * copia o tenant de `tenant_companies` com status APPROVED. Linha de empresa sem
 * vínculo aprovado continua sem chave e, portanto, invisível ao runtime — é o
 * fail-closed pretendido, e sai contada no resultado para ninguém confundir com
 * "preenchi tudo".
 *
 * Também não mexe em policy: instalá-las é papel da migration, e elas já estão
 * lá. Aqui só entra o dado.
 */
final class ChaveDeTenantOperacional
{
    /**
     * Mesmas exceções da migration: auditoria de login acontece antes de existir
     * envelope e tem política própria de retenção.
     *
     * @var list<string>
     */
    private const FORA = ['audit_logs', 'login_logs'];

    /**
     * Situação de cada tabela, sem gravar nada.
     *
     * @return array<string, array{sem_chave:int, preenchiveis:int, sem_vinculo:int, divergentes:int}>
     */
    public function preview(): array
    {
        $this->exigirPostgres();

        $situacao = [];

        foreach ($this->tabelas() as $tabela) {
            $linha = DB::selectOne(<<<SQL
                SELECT count(*) FILTER (WHERE linha.tenant_account_id IS NULL) AS sem_chave,
                       count(*) FILTER (WHERE linha.tenant_account_id IS NULL AND vinculo.id IS NOT NULL) AS preenchiveis,
                       count(*) FILTER (WHERE linha.tenant_account_id IS NOT NULL AND vinculo.id IS NOT NULL
                                          AND linha.tenant_account_id <> vinculo.tenant_account_id) AS divergentes
                FROM {$tabela} linha
                LEFT JOIN tenant_companies vinculo
                  ON vinculo.empresa_id = linha.empresa_id AND vinculo.status = 'APPROVED'
            SQL);

            $situacao[$tabela] = [
                'sem_chave' => (int) $linha->sem_chave,
                'preenchiveis' => (int) $linha->preenchiveis,
                'sem_vinculo' => (int) $linha->sem_chave - (int) $linha->preenchiveis,
                'divergentes' => (int) $linha->divergentes,
            ];
        }

        return $situacao;
    }

    /**
     * Grava. Uma transação por tabela: são milhões de linhas, e a operação é
     * idempotente (só toca chave nula) — se cair no meio, rodar de novo retoma.
     *
     * @return array<string, int> linhas preenchidas por tabela (só as > 0)
     */
    public function aplicar(): array
    {
        $this->exigirPostgres();

        // Chave divergente é dado sob o tenant ERRADO. Preencher o resto por
        // cima esconderia o problema atrás de um "concluído".
        foreach ($this->preview() as $tabela => $s) {
            if ($s['divergentes'] > 0) {
                throw new LogicException("Recusado: {$tabela} tem {$s['divergentes']} linha(s) com chave de tenant diferente do vínculo aprovado da empresa.");
            }
        }

        $gravadas = [];

        foreach ($this->tabelas() as $tabela) {
            $n = DB::transaction(fn (): int => DB::affectingStatement(<<<SQL
                UPDATE {$tabela} linha
                   SET tenant_account_id = vinculo.tenant_account_id
                  FROM tenant_companies vinculo
                 WHERE vinculo.empresa_id = linha.empresa_id
                   AND vinculo.status = 'APPROVED'
                   AND linha.tenant_account_id IS NULL
            SQL));

            if ($n > 0) {
                $gravadas[$tabela] = $n;
            }
        }

        return $gravadas;
    }

    /**
     * Tabelas COMPANY que têm as duas colunas. Lista vazia é erro, não "nada a
     * fazer": significaria manifesto ilegível ou catálogo invisível para a role
     * — e sair com sucesso aí é exatamente o silêncio que este serviço corrige.
     *
     * @return list<string>
     */
    public function tabelas(): array
    {
        $classificadas = array_keys(array_filter(
            (array) config('saas_table_classification'),
            fn (array $entrada): bool => ($entrada['class'] ?? null) === 'COMPANY',
        ));

        // `pg_attribute`, não `information_schema`: este só mostra o que a role
        // possui, e já devolveu "zero tabelas" com o banco cheio.
        $comAsDuas = array_column(DB::select(<<<'SQL'
            SELECT c.relname AS tabela
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace AND n.nspname = 'public'
            JOIN pg_attribute a ON a.attrelid = c.oid AND NOT a.attisdropped
            WHERE c.relkind IN ('r', 'p') AND a.attname IN ('empresa_id', 'tenant_account_id')
            GROUP BY c.relname
            HAVING count(*) = 2
        SQL), 'tabela');

        $tabelas = array_values(array_diff(array_intersect($classificadas, $comAsDuas), self::FORA));
        sort($tabelas);

        if ($tabelas === []) {
            throw new LogicException('Nenhuma tabela operacional encontrada: manifesto de classificação vazio ou catálogo ilegível para esta role.');
        }

        return $tabelas;
    }

    private function exigirPostgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('A chave de tenant operacional só existe sob PostgreSQL (é ela que a RLS compara).');
        }
    }
}
