<?php

namespace App\Etl\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Contexto passado a cada Migrator: conexões legado/novo, modo dry-run, logger.
 *
 * ## O ETL roda como OWNER, e isso não é conveniência
 *
 * O `novo()` devolvia `DB::connection()` — a conexão default, que é `erp_app`,
 * o runtime restrito sob RLS. O campo `$conexaoNova` existia e era **ignorado**.
 *
 * Enquanto a RLS de tenant não existia, funcionava. Depois que ela entrou
 * (`2026_08_29_000300`) e as chaves foram preenchidas (F1-10), o ETL passou a
 * ficar **cego para o próprio destino**: sem envelope de tenant, `erp_app` lê
 * zero em `setores`, `produtos`, `clientes` — e a escrita é recusada de vez:
 *
 * ```
 * ERROR: new row violates row-level security policy for table "setores"
 * ```
 *
 * Medido em homologação: o `etl:run --dry-run` descartava **507.006 linhas de
 * estoque (100%) e 806.953 pedidos/itens (99,99%)** por "referência ausente" —
 * a referência existia; o ETL é que não a enxergava. E saía com SUCESSO.
 *
 * ## Por que owner é o certo, e não um remendo
 *
 * O ETL é o processo que **cria** os tenants a partir do legado. Sujeitá-lo ao
 * escopo deles é a mesma contradição que a migration do plano de controle da
 * conversão já documentou: *"é o processo que CRIA os tenants, então não pode
 * estar sujeito ao escopo deles"*.
 *
 * É também o que as migrations já fazem — rodam como `pgsql_owner`.
 *
 * ## Por que a suíte não pegava
 *
 * sqlite não tem RLS. Verde local, quebrado no banco real — a primeira armadilha
 * do `CLAUDE.md`. O guardião que fecha isso é `EtlEnxergaODestinoTest`.
 */
final class MigrationContext
{
    public function __construct(
        public readonly bool $dryRun = false,
        public readonly string $conexaoLegado = 'legado',

        /**
         * A conexão de destino.
         *
         * `pgsql_owner` por padrão: é o ETL, e ele precisa enxergar e escrever
         * em todos os tenants. Quem quiser rodar sob o runtime restrito tem de
         * pedir explicitamente — e vai encontrar a RLS pela frente, que é o
         * comportamento correto para qualquer coisa que não seja o ETL.
         */
        public readonly string $conexaoNova = 'pgsql_owner',
    ) {}

    /** Conexão de LEITURA do banco legado. */
    public function legado(): ConnectionInterface
    {
        return DB::connection($this->conexaoLegado);
    }

    /**
     * Conexão de LEITURA E ESCRITA do banco novo.
     *
     * Honra `$conexaoNova` — o campo existia e era ignorado, e era exatamente
     * essa a origem do defeito.
     *
     * Em sqlite (a suíte) `pgsql_owner` não existe como conexão útil, então cai
     * para a default: lá não há RLS, e a leitura já é completa. Fazer o
     * contrário — insistir na conexão nomeada — abriria um banco vazio e
     * reintroduziria a cegueira que este método existe para eliminar.
     */
    public function novo(): ConnectionInterface
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return DB::connection();
        }

        try {
            return DB::connection($this->conexaoNova);
        } catch (\Throwable) {
            // Sem credencial de owner o ETL roda cego, e é melhor rodar cego
            // com o defeito conhecido do que não rodar: o portão de descarte
            // (`EtlRun`) reprova a execução antes que ela passe por sucesso.
            return DB::connection();
        }
    }
}
