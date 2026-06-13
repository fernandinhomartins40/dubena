<?php

namespace Tests\Unit;

use App\Empresa;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Session;
use Tests\TestCase;

class GettingLatLongTest extends TestCase
{
    use InteractsWithSession;
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_getting_lat_long_from_google_test()
    {
        // FASE 2: depende da API externa do Google Maps (rede + chave) e de
        // Empresa::find(2) no banco. Reativar como teste de integração na Fase 3.
        $this->markTestSkipped('Depende de Google Maps externo + banco — reativar na Fase 3.');

        // -25.4052561,-51.4871819
        $baseLat = -25.4052561;
        $baseLong = -51.4871819;

        Session::put("empresa_padrao", Empresa::find(2));

        $latLong = buscaLatitudeLongitude("PR", 4109401, 153, 2331, 219);
        // $latLong = buscaLatitudeLongitude("PR", 4109401, 212, 1196, 255);

        if (!isset($latLong->location)) {
            $this->fail("No location received");
            return;
        }

        $isApproximate = $this->areCoordinatesApproximatelyEqual($baseLat, $baseLong, $latLong->location->lat, $latLong->location->lng);

        $this->assertTrue($isApproximate);
    }

    private function areCoordinatesApproximatelyEqual($lat1, $lon1, $lat2, $lon2, $tolerance = 0.01)
    {
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $dlat = abs($lat1 - $lat2);
        $dlon = abs($lon1 - $lon2);

        return $dlat <= $tolerance && $dlon <= $tolerance;
    }
}
