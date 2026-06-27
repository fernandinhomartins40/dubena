<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Monitora\Veiculo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1 — API do Monitora: tipos de veículo, dados extras do veículo e histórico de posições.
 */
class MonitoraApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    public function test_cria_tipo_e_veiculo_com_tipo_e_campos_extras(): void
    {
        [$user] = $this->suporte();

        $tipoId = $this->actingAs($user, 'sanctum')->postJson('/api/admin/monitora/tipos', [
            'descricao' => 'Caminhão', 'velocidade_maxima' => 80,
        ])->assertCreated()->json('data.id');

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/monitora/veiculos', [
            'placa' => 'XYZ1A23', 'descricao' => 'Truck 1', 'tipo_id' => $tipoId,
            'motorista' => 'João', 'km_atual' => 12000, 'deviceid' => 'DEV-9',
        ])->assertCreated()
            ->assertJsonPath('data.motorista', 'João')
            ->assertJsonPath('data.tipo.descricao', 'Caminhão');
    }

    public function test_atualiza_veiculo(): void
    {
        [$user, $empresa] = $this->suporte();
        $veiculo = Veiculo::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'placa' => 'AAA1B11', 'ativo' => true,
        ]);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/monitora/veiculos/{$veiculo->id}", [
            'placa' => 'AAA1B11', 'motorista' => 'Maria', 'km_atual' => 500,
        ])->assertOk()->assertJsonPath('data.motorista', 'Maria');
    }

    public function test_historico_filtra_por_periodo(): void
    {
        [$user, $empresa] = $this->suporte();
        $veiculo = Veiculo::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'placa' => 'BBB2C22', 'ativo' => true,
        ]);
        $veiculo->posicoes()->createMany([
            ['latitude' => -25.0, 'longitude' => -51.0, 'registrado_em' => '2026-06-01 10:00:00'],
            ['latitude' => -25.1, 'longitude' => -51.1, 'registrado_em' => '2026-06-10 10:00:00'],
            ['latitude' => -25.2, 'longitude' => -51.2, 'registrado_em' => '2026-06-20 10:00:00'],
        ]);

        $data = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/monitora/veiculos/{$veiculo->id}/historico?de=2026-06-05&ate=2026-06-15")
            ->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertEqualsWithDelta(-25.1, $data[0]['latitude'], 0.0001);
    }

    public function test_tipos_isolam_por_grupo(): void
    {
        [$userA] = $this->suporte();
        [$userB] = $this->suporte();

        $this->actingAs($userA, 'sanctum')->postJson('/api/admin/monitora/tipos', ['descricao' => 'Moto A'])->assertCreated();

        $dataB = $this->actingAs($userB, 'sanctum')->getJson('/api/admin/monitora/tipos')->assertOk()->json('data');
        $this->assertFalse(collect($dataB)->pluck('descricao')->contains('Moto A'), 'Tipo de veículo vazou entre grupos.');
    }
}
