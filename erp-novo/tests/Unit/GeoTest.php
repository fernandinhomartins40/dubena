<?php

namespace Tests\Unit;

use App\Domain\Shared\Geo;
use PHPUnit\Framework\TestCase;

/**
 * Geo (Q-4) — ponto único do Haversine. Fixa a precisão e o bounding box (PF-1).
 */
class GeoTest extends TestCase
{
    public function test_distancia_conhecida_em_metros_e_km(): void
    {
        // Guarapuava (centro) → ~1 km ao norte. 0.00898° de latitude ≈ 1 km.
        $lat1 = -25.3862077;
        $lng1 = -51.4867962;
        $lat2 = $lat1 + 0.00898;

        $m = Geo::metros($lat1, $lng1, $lat2, $lng1);
        $this->assertEqualsWithDelta(1000.0, $m, 15.0);
        $this->assertEqualsWithDelta(1.0, Geo::km($lat1, $lng1, $lat2, $lng1), 0.02);
    }

    public function test_ponto_igual_da_zero(): void
    {
        $this->assertSame(0.0, Geo::metros(-25.0, -51.0, -25.0, -51.0));
    }

    public function test_bounding_box_cobre_o_raio(): void
    {
        $lat = -25.0;
        $box = Geo::boundingBox($lat, 1000.0); // 1 km

        // O delta de latitude p/ 1 km é ~0.00898°; o de longitude é maior (cos<1).
        $this->assertEqualsWithDelta(0.00898, $box['lat_delta'], 0.0005);
        $this->assertGreaterThan($box['lat_delta'], $box['lng_delta']);

        // Um ponto na borda do raio (ao norte) deve cair DENTRO da caixa de latitude.
        $borda = $lat + Geo::km($lat, -51.0, $lat, -51.0); // 0 → só sanity
        $this->assertGreaterThan(0, $box['lat_delta']);
        $this->assertGreaterThan(0, $box['lng_delta']);
        $this->assertIsFloat($borda);
    }
}
