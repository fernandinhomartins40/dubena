<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Monitora\Cerca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MP1 — Descoberta de empresas por geolocalização (marketplace de gás).
 * Cobre: cobertura por cerca (geofence), fallback por raio da matriz, adesão ao
 * marketplace e ordenação por distância.
 */
class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    /** Cria uma cerca quadrada cobrindo a região de (-25.0,-51.0) a (-25.1,-51.1). */
    private function cercaQuadrada(Empresa $empresa): Cerca
    {
        $cerca = Cerca::query()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Zona', 'ativo' => true,
        ]);
        $cerca->pontos()->createMany([
            ['latitude' => -25.0, 'longitude' => -51.0, 'ordem' => 0],
            ['latitude' => -25.0, 'longitude' => -51.1, 'ordem' => 1],
            ['latitude' => -25.1, 'longitude' => -51.1, 'ordem' => 2],
            ['latitude' => -25.1, 'longitude' => -51.0, 'ordem' => 3],
        ]);

        return $cerca;
    }

    public function test_empresa_com_cerca_cobrindo_o_ponto_aparece(): void
    {
        $empresa = Empresa::factory()->create([
            'app_marketplace_ativo' => true, 'latitude' => -25.05, 'longitude' => -51.05,
        ]);
        $this->cercaQuadrada($empresa);

        $this->postJson('/api/app/v1/marketplace/empresas', ['latitude' => -25.05, 'longitude' => -51.05])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $empresa->id);
    }

    public function test_ponto_fora_da_cerca_nao_traz_a_empresa(): void
    {
        $empresa = Empresa::factory()->create(['app_marketplace_ativo' => true]);
        $this->cercaQuadrada($empresa);

        // Bem longe da cerca.
        $this->postJson('/api/app/v1/marketplace/empresas', ['latitude' => -10.0, 'longitude' => -40.0])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_fallback_por_raio_quando_empresa_nao_tem_cerca(): void
    {
        // Sem cerca, mas raio 5 km a partir da matriz.
        $empresa = Empresa::factory()->create([
            'app_marketplace_ativo' => true,
            'latitude' => -25.05, 'longitude' => -51.05, 'raio_entrega_km' => 5,
        ]);

        // Ponto ~1 km da matriz → dentro do raio.
        $this->postJson('/api/app/v1/marketplace/empresas', ['latitude' => -25.055, 'longitude' => -51.055])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $empresa->id);

        // Ponto bem longe → fora do raio.
        $this->postJson('/api/app/v1/marketplace/empresas', ['latitude' => -26.5, 'longitude' => -52.5])
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_empresa_sem_adesao_nao_aparece(): void
    {
        $empresa = Empresa::factory()->create([
            'app_marketplace_ativo' => false,
            'latitude' => -25.05, 'longitude' => -51.05, 'raio_entrega_km' => 50,
        ]);

        $this->postJson('/api/app/v1/marketplace/empresas', ['latitude' => -25.05, 'longitude' => -51.05])
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_ordena_por_distancia_da_matriz(): void
    {
        // Duas empresas com raio cobrindo o ponto; a mais próxima vem primeiro.
        $perto = Empresa::factory()->create([
            'app_marketplace_ativo' => true, 'latitude' => -25.05, 'longitude' => -51.05, 'raio_entrega_km' => 100,
        ]);
        $longe = Empresa::factory()->create([
            'app_marketplace_ativo' => true, 'latitude' => -25.40, 'longitude' => -51.40, 'raio_entrega_km' => 100,
        ]);

        $resp = $this->postJson('/api/app/v1/marketplace/empresas', ['latitude' => -25.05, 'longitude' => -51.05])
            ->assertOk()->assertJsonCount(2, 'data');

        $this->assertEquals($perto->id, $resp->json('data.0.id'));
        $this->assertEquals($longe->id, $resp->json('data.1.id'));
    }

    public function test_valida_coordenadas(): void
    {
        $this->postJson('/api/app/v1/marketplace/empresas', ['latitude' => 200, 'longitude' => 0])
            ->assertStatus(422);
    }
}
