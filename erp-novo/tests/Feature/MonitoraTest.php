<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Monitora\Veiculo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N11 — API do Monitora (veículos, ingestão, última posição, cercas) + RBAC.
 */
class MonitoraTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    public function test_cria_veiculo_e_ingere_posicao_aparecendo_no_mapa(): void
    {
        [$user, $empresa] = $this->suporte();

        $veiculoId = $this->actingAs($user, 'sanctum')->postJson('/api/admin/monitora/veiculos', [
            'placa' => 'XYZ1234', 'descricao' => 'Caminhão 1', 'imei' => 'IMEI-1',
        ])->assertCreated()->json('data.id');

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/monitora/veiculos/{$veiculoId}/posicoes", [
            'latitude' => -23.55, 'longitude' => -46.63, 'velocidade' => 30, 'ignicao' => true,
        ])->assertCreated();

        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/admin/monitora/ultimas-posicoes')->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertEquals('XYZ1234', $resp->json('data.0.placa'));
    }

    public function test_cria_cerca(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/monitora/cercas', [
            'descricao' => 'Pátio', 'centro_lat' => -23.55, 'centro_lng' => -46.63, 'raio_metros' => 150,
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/monitora/cercas')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_sync_endpoint_responde(): void
    {
        [$user, $empresa] = $this->suporte();
        Veiculo::create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'placa' => 'AAA1111', 'imei' => 'IMEI-X', 'ativo' => true]);

        // Sem posições no driver fake → 0 ingeridas, mas o endpoint funciona.
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/monitora/sync')
            ->assertOk()->assertJsonPath('ingeridas', 0);
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/monitora/veiculos')->assertStatus(403);
    }
}
