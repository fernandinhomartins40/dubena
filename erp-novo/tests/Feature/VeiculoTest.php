<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Frota\Veiculo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FASE C6 — endpoints de veículo (frota). */
class VeiculoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    public function test_cria_e_lista_veiculo(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/veiculos', ['placa' => 'ABC1D23', 'descricao' => 'Caminhão 1'])
            ->assertStatus(201)
            ->assertJsonPath('data.placa', 'ABC1D23');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/veiculos')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_registra_abastecimento_e_avanca_km(): void
    {
        [$user, $empresa] = $this->suporte();
        $v = Veiculo::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'km_atual' => 1000]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/veiculos/{$v->id}/abastecimentos", ['km' => 1300, 'litros' => 40])
            ->assertStatus(201);

        $this->assertSame(1300, (int) $v->refresh()->km_atual);
    }

    public function test_show_traz_consumo_e_alerta(): void
    {
        [$user, $empresa] = $this->suporte();
        $v = Veiculo::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/veiculos/{$v->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'placa', 'consumo_medio', 'alerta_oleo' => ['precisa_trocar']]]);
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/veiculos')->assertStatus(403);
    }
}
