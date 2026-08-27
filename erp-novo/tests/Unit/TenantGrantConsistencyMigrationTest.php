<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TenantGrantConsistencyMigrationTest extends TestCase
{
    public function test_trigger_valida_membership_empresa_e_link_na_mesma_conta(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_000600_validate_tenant_company_grant_consistency.php');

        $this->assertStringContainsString('tenant_company_grant_consistency', $migration);
        $this->assertStringContainsString('membership.tenant_account_id = NEW.tenant_account_id', $migration);
        $this->assertStringContainsString('company.tenant_account_id = NEW.tenant_account_id', $migration);
        $this->assertStringContainsString('company.empresa_id = NEW.empresa_id', $migration);
    }
}
