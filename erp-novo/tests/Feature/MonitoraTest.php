<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Monitora\CercaPonto;
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
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

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

    public function test_cria_cerca_poligonal(): void
    {
        [$user] = $this->suporte();

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/monitora/cercas', [
            'descricao' => 'Pátio', 'cor' => '#FF6200',
            'pontos' => [
                ['latitude' => -25.39, 'longitude' => -51.45],
                ['latitude' => -25.40, 'longitude' => -51.45],
                ['latitude' => -25.40, 'longitude' => -51.46],
                ['latitude' => -25.39, 'longitude' => -51.46],
            ],
        ])->assertCreated();

        $resp->assertJsonPath('data.descricao', 'Pátio')->assertJsonCount(4, 'data.pontos');
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/monitora/cercas')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(4, 'data.0.pontos');
    }

    public function test_cerca_exige_ao_menos_3_pontos(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/monitora/cercas', [
            'descricao' => 'X', 'pontos' => [['latitude' => -25.3, 'longitude' => -51.4]],
        ])->assertStatus(422)->assertJsonValidationErrorFor('pontos');
    }

    public function test_atualiza_e_exclui_cerca(): void
    {
        [$user] = $this->suporte();
        $pontos = [
            ['latitude' => -25.39, 'longitude' => -51.45],
            ['latitude' => -25.40, 'longitude' => -51.45],
            ['latitude' => -25.40, 'longitude' => -51.46],
        ];
        $id = $this->actingAs($user, 'sanctum')->postJson('/api/admin/monitora/cercas', [
            'descricao' => 'Antiga', 'pontos' => $pontos,
        ])->json('data.id');

        // Atualiza descrição + regrava com 4 pontos.
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/monitora/cercas/{$id}", [
            'descricao' => 'Nova', 'pontos' => array_merge($pontos, [['latitude' => -25.39, 'longitude' => -51.46]]),
        ])->assertOk()->assertJsonPath('data.descricao', 'Nova')->assertJsonCount(4, 'data.pontos');
        $this->assertDatabaseHas('monitora_cercas', ['id' => $id, 'descricao' => 'Nova']);
        $this->assertSame(4, CercaPonto::where('cerca_id', $id)->count());

        // Exclui (vértices em cascata).
        $this->actingAs($user, 'sanctum')->deleteJson("/api/admin/monitora/cercas/{$id}")->assertOk();
        $this->assertDatabaseMissing('monitora_cercas', ['id' => $id]);
        $this->assertSame(0, CercaPonto::where('cerca_id', $id)->count());
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
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/monitora/veiculos')->assertStatus(403);
    }
}
