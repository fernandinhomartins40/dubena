<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Monitora\Veiculo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L4 — Jornada pelo app do entregador: iniciar (com veículo), consultar a atual,
 * encerrar, e a regra de que a posição só conta com jornada ativa.
 */
class JornadaEntregadorTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $entregador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->entregador = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
    }

    private function veiculo(): Veiculo
    {
        return Veiculo::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'placa' => 'ABC1234', 'km_atual' => 1000, 'ativo' => true,
        ]);
    }

    public function test_iniciar_jornada_com_veiculo_e_checklist(): void
    {
        $veiculo = $this->veiculo();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/jornada/iniciar', [
                'veiculo_id' => $veiculo->id,
                'checklist' => ['pneus' => 'ok', 'gas' => 'ok'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ativa')
            ->assertJsonPath('data.veiculo.placa', 'ABC1234');

        $this->assertDatabaseHas('jornadas', [
            'entregador_user_id' => $this->entregador->id, 'status' => 'ativa', 'veiculo_id' => $veiculo->id,
        ]);
    }

    public function test_jornada_atual_reflete_estado(): void
    {
        $this->actingAs($this->entregador, 'sanctum')->getJson('/api/app/v1/entregador/jornada')
            ->assertOk()->assertJsonPath('data', null);

        $this->actingAs($this->entregador, 'sanctum')->postJson('/api/app/v1/entregador/jornada/iniciar', []);

        $this->actingAs($this->entregador, 'sanctum')->getJson('/api/app/v1/entregador/jornada')
            ->assertOk()->assertJsonPath('data.status', 'ativa');
    }

    public function test_encerrar_jornada(): void
    {
        $veiculo = $this->veiculo();
        $this->actingAs($this->entregador, 'sanctum')->postJson('/api/app/v1/entregador/jornada/iniciar', ['veiculo_id' => $veiculo->id]);

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/jornada/encerrar', ['km_final' => 1200])
            ->assertOk()->assertJsonPath('data.status', 'encerrada');

        $this->assertSame(1200, (int) $veiculo->refresh()->km_atual);
    }

    public function test_posicao_sem_jornada_e_rejeitada(): void
    {
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/posicao', ['latitude' => -25.0, 'longitude' => -51.0])
            ->assertStatus(422);
    }

    public function test_dashboard_sem_jornada_indica_fora_de_servico(): void
    {
        $this->actingAs($this->entregador, 'sanctum')->getJson('/api/app/v1/entregador/dashboard')
            ->assertOk()
            ->assertJsonPath('data.em_servico', false)
            ->assertJsonPath('data.pendentes', 0);
    }
}
