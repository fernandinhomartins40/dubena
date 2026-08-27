<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1-06/F1-10: promove somente a titularidade documental ja aprovada para as
 * tabelas operacionais que possuem empresa_id. Nao deriva tenant de grupo,
 * usuario, maioria de linhas ou qualquer outro dado legado.
 *
 * Linhas de empresas sem TenantCompany APPROVED permanecem sem chave SaaS e a
 * policy canonica as nega ao runtime. Assim, uma copia/teste nunca amplia a
 * fronteira do produto para continuar acessivel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->companyTablesWithEmpresaId() as $table) {
            $drift = (int) DB::scalar(<<<SQL
                SELECT count(*)
                FROM {$table} business_row
                JOIN tenant_companies company_link
                  ON company_link.empresa_id = business_row.empresa_id
                 AND company_link.status = 'APPROVED'
                WHERE business_row.tenant_account_id IS NOT NULL
                  AND business_row.tenant_account_id <> company_link.tenant_account_id
            SQL);
            if ($drift > 0) {
                throw new LogicException("F1 recusada: {$table} possui {$drift} chave(s) tenant divergente(s) do TenantCompany aprovado.");
            }

            DB::statement(<<<SQL
                UPDATE {$table} business_row
                   SET tenant_account_id = company_link.tenant_account_id
                  FROM tenant_companies company_link
                 WHERE company_link.empresa_id = business_row.empresa_id
                   AND company_link.status = 'APPROVED'
                   AND business_row.tenant_account_id IS NULL
            SQL);

            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                USING (app_tenant_can_read(tenant_account_id, empresa_id))
                WITH CHECK (app_tenant_can_operate(tenant_account_id, empresa_id))
            SQL);
        }
    }

    public function down(): void
    {
        // Nao restaura policy fail-open nem apaga chaves preenchidas por vinculo aprovado.
    }

    /** @return list<string> */
    private function companyTablesWithEmpresaId(): array
    {
        $classified = array_keys(array_filter(
            (array) config('saas_table_classification'),
            fn (array $entry): bool => $entry['class'] === 'COMPANY',
        ));
        // Auditoria de login ocorre antes de existir identidade/envelope. Estas
        // tabelas possuem política própria de retenção e não são fronteira de
        // leitura de negócio; protegê-las com grant operacional quebra o login.
        $classified = array_values(array_diff($classified, ['audit_logs', 'login_logs']));
        $available = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('column_name', ['empresa_id', 'tenant_account_id'])
            ->select('table_name')
            ->groupBy('table_name')
            ->havingRaw('count(distinct column_name) = 2')
            ->pluck('table_name')
            ->all();

        return array_values(array_intersect($classified, $available));
    }
};
