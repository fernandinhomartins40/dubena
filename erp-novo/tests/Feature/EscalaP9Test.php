<?php

namespace Tests\Feature;

use App\Domain\Mobile\MarketplaceService;
use App\Models\Empresa;
use App\Models\Monitora\Cerca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P9 — escala. Garante que o PRÉ-FILTRO por bounding-box do marketplace não muda
 * o resultado correto (empresas com cerca sempre entram; raio-only fora da caixa
 * não aparece) e que o readiness (/health) responde.
 */
class EscalaP9Test extends TestCase
{
    use RefreshDatabase;

    private function cercaQuadrada(Empresa $empresa): void
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
    }

    public function test_empresa_raio_only_muito_distante_e_pre_filtrada(): void
    {
        // Empresa raio-only a > 60 km do ponto: o bounding-box já a descarta (sem
        // mudar o resultado, pois também estaria fora do raio).
        Empresa::factory()->create([
            'app_marketplace_ativo' => true,
            'latitude' => -28.0, 'longitude' => -54.0, 'raio_entrega_km' => 5,
        ]);

        $this->postJson('/api/app/v1/marketplace/empresas', ['latitude' => -25.05, 'longitude' => -51.05])
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_empresa_com_cerca_entra_mesmo_com_matriz_fora_da_caixa(): void
    {
        // Matriz longe (fora do bbox), mas a CERCA cobre o ponto: o orWhereExists
        // garante que ela continua candidata (cerca não é limitada por raio/caixa).
        $empresa = Empresa::factory()->create([
            'app_marketplace_ativo' => true,
            'latitude' => -28.0, 'longitude' => -54.0, // matriz longe
        ]);
        $this->cercaQuadrada($empresa);

        $this->postJson('/api/app/v1/marketplace/empresas', ['latitude' => -25.05, 'longitude' => -51.05])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $empresa->id);
    }

    public function test_pre_filtro_nao_altera_empresa_proxima(): void
    {
        $empresa = Empresa::factory()->create([
            'app_marketplace_ativo' => true,
            'latitude' => -25.05, 'longitude' => -51.05, 'raio_entrega_km' => 10,
        ]);

        $r = app(MarketplaceService::class)->empresasNoPonto(-25.055, -51.055);
        $this->assertCount(1, $r);
        $this->assertSame($empresa->id, $r->first()['id']);
    }

    public function test_health_responde_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.db', true);
    }
}
