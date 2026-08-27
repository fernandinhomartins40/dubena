<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CanonicalTenantRlsMigrationTest extends TestCase
{
    public function test_policies_sombra_usam_funcoes_canonicas_e_contexto_fail_closed(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_000400_create_canonical_tenant_rls_functions.php');

        $this->assertStringContainsString('app_current_tenant_account_id', $migration);
        $this->assertStringContainsString('app_current_tenant_membership_id', $migration);
        $this->assertStringContainsString('app_tenant_can_read', $migration);
        $this->assertStringContainsString('app_tenant_can_operate', $migration);
        $this->assertStringContainsString('SECURITY DEFINER SET search_path = public, pg_temp', $migration);
        $this->assertStringContainsString('approved_at IS NOT NULL', $migration);
    }

    public function test_vinculos_de_empresa_da_fronteira_recebem_policy_canonica(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_000700_protect_tenant_boundary_company_links.php');

        $this->assertStringContainsString("'tenant_companies', 'tenant_company_grants'", $source);
        $this->assertStringContainsString('ENABLE ROW LEVEL SECURITY', $source);
        $this->assertStringContainsString('FORCE ROW LEVEL SECURITY', $source);
        $this->assertStringContainsString('app_tenant_can_read(tenant_account_id, empresa_id)', $source);
        $this->assertStringContainsString('app_tenant_can_operate(tenant_account_id, empresa_id)', $source);
    }
}
