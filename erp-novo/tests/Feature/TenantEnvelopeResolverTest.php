<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantAccessDeniedException;
use App\Domain\Tenant\TenantEnvelopeResolver;
use App\Models\Empresa;
use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantEnvelopeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_somente_com_titularidade_membership_e_grant_aprovados(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create();
        $tenant = TenantAccount::create(['legal_name' => 'Tenant aprovado', 'status' => TenantAccount::STATUS_ACTIVE]);
        $company = TenantCompany::create([
            'tenant_account_id' => $tenant->id,
            'empresa_id' => $empresa->id,
            'status' => TenantCompany::STATUS_APPROVED,
            'approved_at' => now(),
            'ownership_evidence_ref' => 'evidence-1',
        ]);
        $membership = TenantMembership::create([
            'tenant_account_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => TenantMembership::STATUS_ACTIVE,
            'approved_at' => now(),
            'approval_evidence_ref' => 'evidence-2',
        ]);
        TenantCompanyGrant::create([
            'tenant_membership_id' => $membership->id,
            'tenant_account_id' => $tenant->id,
            'tenant_company_id' => $company->id,
            'empresa_id' => $empresa->id,
            'can_read' => true,
            'can_operate' => true,
            'approved_at' => now(),
            'grant_evidence_ref' => 'evidence-3',
        ]);

        $envelope = app(TenantEnvelopeResolver::class)->resolveFor($user, $empresa->id, 'correlation-1');

        $this->assertSame($tenant->id, $envelope->tenantAccountId);
        $this->assertTrue($envelope->canOperate($empresa->id));
    }

    public function test_recusa_empresa_legada_sem_vinculo_aprovado(): void
    {
        $this->expectException(TenantAccessDeniedException::class);

        app(TenantEnvelopeResolver::class)->resolveFor(User::factory()->create(), Empresa::factory()->create()->id, 'correlation-1');
    }
}
