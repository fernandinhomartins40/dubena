<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige a policy de `integracao_consumos`: a escrita da PLATAFORMA era
 * rejeitada em silêncio.
 *
 * ## O defeito
 *
 * A F6-01 criou a tabela para responder *quem gastou quanto*, e o cabeçalho da
 * própria migration diz, sobre `empresa_id`/`grupo_id` nulos:
 *
 * > os dois nulos significam "chave da plataforma" — **o caso que hoje some no
 * > log, e que é justamente o que se quer enxergar**.
 *
 * Mas a policy saiu com a forma canônica:
 *
 * ```sql
 * WITH CHECK (empresa_id IS NOT NULL AND app_tenant_can_operate(...))
 * ```
 *
 * Com `FORCE ROW LEVEL SECURITY`, o Postgres **rejeita** todo insert de
 * `empresa_id` nulo. Ou seja: a tabela criada para enxergar o consumo da
 * plataforma é exatamente a que nunca o registra.
 *
 * Conferido ao vivo em homologação:
 *
 * ```
 * ERROR: new row violates row-level security policy for table "integracao_consumos"
 * ```
 *
 * ## Por que passou despercebido
 *
 * Duas camadas escondem:
 *
 *  - `ConsumoDeIntegracao::registrar()` engole toda exceção — de propósito, e
 *    corretamente: contar chamada não pode derrubar uma geocodificação. Mas isso
 *    transforma a rejeição da RLS em nada;
 *  - a suíte roda em **sqlite**, que não tem RLS. Verde local, defeito em
 *    produção.
 *
 * É a mesma família do defeito de *grants* (`2026_09_01_000200`): o código dizia
 * uma coisa, o banco fazia outra, e nenhum teste comparava os dois.
 *
 * ## Por que `IS NULL OR` não afrouxa nada
 *
 * A linha de `empresa_id` nulo não pertence a tenant nenhum — não há sigilo a
 * violar ao gravá-la. O `USING` continua intacto, então nenhuma revenda a **lê**;
 * e o `WITH CHECK` continua impedindo uma revenda de gravar linha de OUTRA, que
 * é a proteção que importa.
 */
return new class extends Migration
{
    /** Tabelas cujo `empresa_id` nulo é um caso legítimo de plataforma. */
    private const TABELAS = ['integracao_consumos'];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TABELAS as $tabela) {
            if (DB::selectOne('SELECT to_regclass(?) AS r', ['public.'.$tabela])?->r === null) {
                continue;
            }

            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");

            DB::statement(
                "CREATE POLICY tenant_isolation ON {$tabela}
                 USING (empresa_id IS NOT NULL AND app_tenant_can_read(tenant_account_id, empresa_id))
                 WITH CHECK (empresa_id IS NULL OR app_tenant_can_operate(tenant_account_id, empresa_id))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Devolve a forma anterior EXATA, mesmo sabendo que ela tem o defeito —
        // `down()` restaura o estado, não corrige o passado.
        foreach (self::TABELAS as $tabela) {
            if (DB::selectOne('SELECT to_regclass(?) AS r', ['public.'.$tabela])?->r === null) {
                continue;
            }

            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");

            DB::statement(
                "CREATE POLICY tenant_isolation ON {$tabela}
                 USING (empresa_id IS NOT NULL AND app_tenant_can_read(tenant_account_id, empresa_id))
                 WITH CHECK (empresa_id IS NOT NULL AND app_tenant_can_operate(tenant_account_id, empresa_id))"
            );
        }
    }
};
