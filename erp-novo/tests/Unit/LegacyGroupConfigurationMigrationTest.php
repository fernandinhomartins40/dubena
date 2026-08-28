<?php

namespace Tests\Unit;

use Tests\TestCase;

class LegacyGroupConfigurationMigrationTest extends TestCase
{
    public function test_ponte_de_configuracao_exige_evidencia_e_policy_canonica(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_001100_protect_legacy_group_configuration.php');

        $this->assertStringContainsString("'tenant_legacy_group_scopes'", $source);
        $this->assertStringContainsString("'evidence_ref'", $source);
        $this->assertStringContainsString('app_tenant_can_read_group_config', $source);
        $this->assertStringContainsString('app_tenant_can_operate_group_config', $source);
        $this->assertStringContainsString('nao pertencem a migration', $source);

        $protector = file_get_contents(dirname(__DIR__, 2).'/app/Domain/Tenant/TenantLegacyGroupConfigurationProtector.php');
        $this->assertStringContainsString('configuracao(oes) sem ponte documental aprovada', $protector);
        $this->assertStringContainsString("'clientecontatotipos'", $protector);
        $this->assertStringContainsString("'clientecontatosituacoes'", $protector);
        $this->assertStringContainsString("'motivos_nao_venda'", $protector);
        $this->assertStringContainsString("'contamovimentotipos'", $protector);
        $this->assertStringContainsString("'cargos'", $protector);
        $this->assertStringContainsString("'veiculo_tipos'", $protector);
        $this->assertStringContainsString("'promocoes'", $protector);
        $this->assertStringContainsString("'monitora_veiculo_tipos'", $protector);
        $this->assertStringContainsString("'checklists'", $protector);
        $this->assertStringContainsString("'sorteios'", $protector);
        $this->assertStringContainsString('protectChildTable', $protector);
        $this->assertStringContainsString('ENABLE ROW LEVEL SECURITY', $protector);
        $this->assertStringContainsString('FORCE ROW LEVEL SECURITY', $protector);

        $scopePolicy = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_29_001200_protect_tenant_legacy_group_scopes.php');
        $this->assertStringContainsString('ALTER TABLE tenant_legacy_group_scopes ENABLE ROW LEVEL SECURITY', $scopePolicy);
        $this->assertStringContainsString('WITH CHECK (false)', $scopePolicy);
    }
}
