<?php

namespace App\Domain\Logistica\Drivers;

use App\Domain\Logistica\Contracts\TracadorRota;

/**
 * Null-driver do traçado (L6) — sem GOOGLE_MAPS_KEY não há desenho de rota pelas
 * ruas; o app liga as paradas com linha reta. Zero custo, zero rede.
 */
class SemTracado implements TracadorRota
{
    public function tracar(float $origLat, float $origLng, float $destLat, float $destLng): ?array
    {
        return null;
    }
}
