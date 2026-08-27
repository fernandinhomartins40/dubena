<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantMembership;
use App\Models\Saas\TenantNetworkLink;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantBoundarySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_sombra_nao_infere_tenant_de_empresa_ou_grupo_legado(): void
    {
        $empresa = Empresa::factory()->create();

        $this->assertSame(0, TenantAccount::count());
        $this->assertSame(0, TenantCompany::count());

        $tenant = TenantAccount::create(['legal_name' => 'Conta juridica aprovada']);
        $link = TenantCompany::create([
            'tenant_account_id' => $tenant->id,
            'empresa_id' => $empresa->id,
        ]);

        $this->assertSame(TenantCompany::STATUS_PENDING_OWNERSHIP, $link->refresh()->status);
        $this->assertSame(1, $tenant->companies()->count());
    }

    public function test_uma_empresa_nao_pode_ser_vinculada_a_dois_tenants(): void
    {
        $empresa = Empresa::factory()->create();
        $tenantA = TenantAccount::create(['legal_name' => 'Tenant A']);
        $tenantB = TenantAccount::create(['legal_name' => 'Tenant B']);

        TenantCompany::create(['tenant_account_id' => $tenantA->id, 'empresa_id' => $empresa->id]);

        $this->expectException(QueryException::class);
        TenantCompany::create(['tenant_account_id' => $tenantB->id, 'empresa_id' => $empresa->id]);
    }

    public function test_grant_operacional_e_link_comercial_sao_registros_independentes(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create();
        $provider = TenantAccount::create(['legal_name' => 'Fornecedor SaaS']);
        $consumer = TenantAccount::create(['legal_name' => 'Rede cliente']);
        $membership = TenantMembership::create([
            'tenant_account_id' => $consumer->id,
            'user_id' => $user->id,
        ]);
        $companyLink = TenantCompany::create([
            'tenant_account_id' => $consumer->id,
            'empresa_id' => $empresa->id,
        ]);

        TenantNetworkLink::create([
            'provider_tenant_account_id' => $provider->id,
            'consumer_tenant_account_id' => $consumer->id,
            'relationship_type' => 'FRANCHISE',
        ]);

        $this->assertSame(0, TenantCompanyGrant::count());

        $grant = TenantCompanyGrant::create([
            'tenant_membership_id' => $membership->id,
            'tenant_account_id' => $consumer->id,
            'empresa_id' => $empresa->id,
            'tenant_company_id' => $companyLink->id,
            'can_read' => true,
            'can_operate' => false,
        ]);

        $this->assertTrue($grant->can_read);
        $this->assertFalse($grant->can_operate);
        $this->assertSame($consumer->id, $grant->tenantAccount->id);
        $this->assertSame($companyLink->id, $grant->tenantCompany->id);
        $this->assertSame(1, TenantNetworkLink::count());
    }

    public function test_toda_tabela_company_do_manifesto_tem_chave_saas_aditiva(): void
    {
        $classification = config('saas_table_classification');
        $companyTables = array_keys(array_filter(
            $classification,
            fn (array $entry) => $entry['class'] === 'COMPANY',
        ));

        foreach ($companyTables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'tenant_account_id'), "{$table} sem tenant_account_id");
        }
    }
}
