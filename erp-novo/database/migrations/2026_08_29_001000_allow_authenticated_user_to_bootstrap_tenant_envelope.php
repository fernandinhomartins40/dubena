<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A fronteira precisa ser consultável pelo usuário já autenticado antes de o
 * TenantEnvelope existir. Esta é uma permissão de bootstrap, limitada ao
 * próprio membership/grant aprovado; não usa a role owner nem abre dados de
 * negócio sem envelope.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.app_current_authenticated_user_id()
RETURNS bigint LANGUAGE sql STABLE AS $$
    SELECT NULLIF(current_setting('app.authenticated_user_id', true), '')::bigint
$$;
SQL);

        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON tenant_company_grants');
        DB::statement(<<<'SQL'
CREATE POLICY tenant_isolation ON tenant_company_grants
USING (
    (tenant_account_id = app_current_tenant_account_id()
        AND tenant_membership_id = app_current_tenant_membership_id())
    OR EXISTS (
        SELECT 1 FROM tenant_memberships membership
        WHERE membership.id = tenant_company_grants.tenant_membership_id
          AND membership.user_id = app_current_authenticated_user_id()
          AND membership.status = 'ACTIVE'
          AND tenant_company_grants.approved_at IS NOT NULL
    )
)
WITH CHECK (false)
SQL);

        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON tenant_companies');
        DB::statement(<<<'SQL'
CREATE POLICY tenant_isolation ON tenant_companies
USING (
    app_tenant_can_read(tenant_account_id, empresa_id)
    OR EXISTS (
        SELECT 1
        FROM tenant_company_grants tenant_grant
        JOIN tenant_memberships membership ON membership.id = tenant_grant.tenant_membership_id
        WHERE tenant_grant.tenant_company_id = tenant_companies.id
          AND membership.user_id = app_current_authenticated_user_id()
          AND membership.status = 'ACTIVE'
          AND tenant_grant.approved_at IS NOT NULL
          AND tenant_grant.can_read = true
    )
)
WITH CHECK (false)
SQL);
    }

    public function down(): void
    {
        // Não reintroduz policy recursiva nem bypass de fronteira.
    }
};
