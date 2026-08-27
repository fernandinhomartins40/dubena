<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1: os vinculos de fronteira tambem carregam empresa_id e, portanto, nunca
 * podem ficar fora da protecao RLS. Esta migration roda apos as funcoes
 * canonicas e nao reutiliza grupo_id como identidade SaaS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['tenant_companies', 'tenant_company_grants'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement(
                "CREATE POLICY tenant_isolation ON {$table}
                 USING (app_tenant_can_read(tenant_account_id, empresa_id))
                 WITH CHECK (app_tenant_can_operate(tenant_account_id, empresa_id))"
            );
        }
    }

    public function down(): void
    {
        // A fronteira nao regride para leitura/escrita sem contexto.
    }
};
