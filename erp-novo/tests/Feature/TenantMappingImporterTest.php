<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantMappingImporter;
use App\Models\Empresa;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TenantMappingImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_nao_persiste_e_apply_cria_somente_vinculos_documentados(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create();
        $plan = [
            'tenants' => [[
                'legal_name' => 'Conta comprovada',
                'classification_evidence_ref' => 'legal-doc-1',
                'companies' => [['empresa_id' => $empresa->id, 'ownership_evidence_ref' => 'legal-doc-2']],
                'memberships' => [[
                    'user_id' => $user->id,
                    'approval_evidence_ref' => 'legal-doc-3',
                    'grants' => [[
                        'empresa_id' => $empresa->id,
                        'can_read' => true,
                        'can_operate' => true,
                        'grant_evidence_ref' => 'legal-doc-4',
                    ]],
                ]],
            ]],
        ];
        $importer = app(TenantMappingImporter::class);

        $this->assertSame(['tenants' => 1, 'companies' => 1, 'memberships' => 1, 'grants' => 1], $importer->preview($plan));
        $this->assertDatabaseCount('tenant_accounts', 0);

        $importer->apply($plan);

        $this->assertDatabaseCount('tenant_accounts', 1);
        $this->assertDatabaseCount('tenant_company_grants', 1);
        $this->assertSame(Empresa::OWNERSHIP_APPROVED, $empresa->refresh()->ownership_status);
        $this->assertTrue(TenantCompanyGrant::firstOrFail()->can_operate);
    }

    public function test_recusa_grant_sem_evidencia_ou_empresa_do_mesmo_tenant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(TenantMappingImporter::class)->preview(['tenants' => []]);
    }
}
