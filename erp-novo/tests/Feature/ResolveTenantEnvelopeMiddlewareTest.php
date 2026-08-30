<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantEnvelopeRuntime;
use App\Models\Empresa;
use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResolveTenantEnvelopeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_e_resolvido_no_request_e_limpo_no_final(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);

        $empresa = Empresa::factory()->semFronteiraSaas()->create();
        $user = User::factory()->semFronteiraSaas()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $tenant = TenantAccount::create(['legal_name' => 'Tenant middleware', 'status' => TenantAccount::STATUS_ACTIVE]);
        $company = TenantCompany::create(['tenant_account_id' => $tenant->id, 'empresa_id' => $empresa->id, 'status' => TenantCompany::STATUS_APPROVED, 'approved_at' => now(), 'ownership_evidence_ref' => 'e-1']);
        $membership = TenantMembership::create(['tenant_account_id' => $tenant->id, 'user_id' => $user->id, 'status' => TenantMembership::STATUS_ACTIVE, 'approved_at' => now(), 'approval_evidence_ref' => 'e-2']);
        TenantCompanyGrant::create(['tenant_membership_id' => $membership->id, 'tenant_account_id' => $tenant->id, 'tenant_company_id' => $company->id, 'empresa_id' => $empresa->id, 'can_read' => true, 'can_operate' => true, 'approved_at' => now(), 'grant_evidence_ref' => 'e-3']);

        Route::middleware(['auth:sanctum', 'tenant', 'tenant.saas'])->get('/_f1-envelope', function (TenantEnvelopeRuntime $runtime) {
            return response()->json(['tenant_account_id' => $runtime->current()?->tenantAccountId]);
        });
        Sanctum::actingAs($user);

        $this->getJson('/_f1-envelope')->assertOk()->assertJson(['tenant_account_id' => $tenant->id]);
        $this->assertNull(app(TenantEnvelopeRuntime::class)->current());
    }

    public function test_flag_desligada_preserva_rota_autenticada_sem_mapping_saas(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', false);
        $empresa = Empresa::factory()->semFronteiraSaas()->create();
        $user = User::factory()->semFronteiraSaas()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        Route::middleware(['auth:sanctum', 'tenant', 'tenant.saas'])->get('/_f1-envelope-disabled', fn () => response()->json(['ok' => true]));
        Sanctum::actingAs($user);

        $this->getJson('/_f1-envelope-disabled')->assertOk()->assertJson(['ok' => true]);
    }
}
