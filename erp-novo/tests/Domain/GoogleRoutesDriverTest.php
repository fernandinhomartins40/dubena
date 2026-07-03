<?php

namespace Tests\Domain;

use App\Domain\Logistica\Drivers\GoogleRoutesDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * L6 — GoogleRoutesDriver: sucesso cacheado; FALHA abre o circuito (nenhuma nova
 * chamada HTTP) — proteção que impede o endpoint de rota de pendurar quando a
 * Routes API está desabilitada/sem quota.
 */
class GoogleRoutesDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_sucesso_devolve_polyline_e_cacheia(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response([
                'routes' => [[
                    'duration' => '420s',
                    'distanceMeters' => 3500,
                    'polyline' => ['encodedPolyline' => 'abc_enc'],
                ]],
            ], 200),
        ]);

        $driver = new GoogleRoutesDriver('key-teste');

        $r1 = $driver->tracar(-25.39, -51.46, -25.40, -51.47);
        $r2 = $driver->tracar(-25.39, -51.46, -25.40, -51.47); // cache hit

        $this->assertSame('abc_enc', $r1['polyline']);
        $this->assertSame(3.5, $r1['distancia_km']);
        $this->assertSame(7.0, $r1['duracao_min']);
        $this->assertSame($r1, $r2);
        Http::assertSentCount(1);
    }

    public function test_falha_403_abre_circuito_e_nao_chama_mais(): void
    {
        Http::fake([
            'routes.googleapis.com/*' => Http::response(['error' => ['code' => 403]], 403),
        ]);

        $driver = new GoogleRoutesDriver('key-teste');

        $this->assertNull($driver->tracar(-25.39, -51.46, -25.40, -51.47));
        // Pares DIFERENTES: com o circuito aberto, nenhuma chamada nova.
        $this->assertNull($driver->tracar(-25.10, -51.10, -25.20, -51.20));
        $this->assertNull($driver->tracar(-25.30, -51.30, -25.31, -51.31));

        Http::assertSentCount(1);
    }
}
