<?php

namespace Tests\Feature;

use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantStagingArtifact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantStagingArtifactTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_remove_payload_expirado_e_mantem_trilha(): void
    {
        $tenant = TenantAccount::create(['legal_name' => 'Tenant staging']);
        $artifact = TenantStagingArtifact::create([
            'tenant_account_id' => $tenant->id,
            'owner' => 'etl-import',
            'purpose' => 'preview',
            'payload' => ['cpf' => 'sensitive'],
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('saas:staging:purgar')->assertSuccessful();

        $artifact->refresh();
        $this->assertSame([], $artifact->payload);
        $this->assertNotNull($artifact->purged_at);
        $this->assertSame('etl-import', $artifact->owner);
    }
}
