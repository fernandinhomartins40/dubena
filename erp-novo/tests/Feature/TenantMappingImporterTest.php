<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantMappingImporter;
use App\Domain\Tenant\TenantMembershipMappingImporter;
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

    public function test_importa_membership_documentada_sem_alterar_owner_existente(): void
    {
        $empresa = Empresa::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create(['ativo' => true]);
        app(TenantMappingImporter::class)->apply([
            'tenants' => [[
                'legal_name' => 'Conta comprovada',
                'document' => '04190715000105',
                'classification_evidence_ref' => 'legal-doc-1',
                'companies' => [['empresa_id' => $empresa->id, 'ownership_evidence_ref' => 'legal-doc-2']],
                'memberships' => [[
                    'user_id' => $owner->id,
                    'membership_role' => 'OWNER',
                    'approval_evidence_ref' => 'legal-doc-3',
                    'grants' => [['empresa_id' => $empresa->id, 'can_read' => true, 'can_operate' => true, 'grant_evidence_ref' => 'legal-doc-4']],
                ]],
            ]],
        ]);

        $plan = ['tenant_document' => '04190715000105', 'memberships' => [[
            'user_id' => $member->id,
            'membership_role' => 'MEMBER',
            'approval_evidence_ref' => 'legacy-access-snapshot',
            'grants' => [['empresa_id' => $empresa->id, 'can_read' => true, 'can_operate' => true, 'grant_evidence_ref' => 'legacy-access-snapshot']],
        ]]];

        $importer = app(TenantMembershipMappingImporter::class);
        $this->assertSame(['memberships' => 1, 'grants' => 1], $importer->preview($plan));
        $importer->apply($plan);

        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $member->id, 'membership_role' => 'MEMBER']);
        $this->assertDatabaseCount('tenant_company_grants', 2);
    }
}
