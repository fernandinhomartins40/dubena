<?php

namespace App\Domain\Logistica\Drivers;

use App\Domain\Logistica\Contracts\MatrizDistancia;

/**
 * Driver GRÁTIS (default) — distância em linha reta (Haversine) e duração estimada
 * por uma velocidade média urbana. Sem custo, sem rede, sem trânsito real. Cobre o
 * MVP de roteirização; a L5 troca por Google Distance Matrix quando valer o custo.
 */
class HaversineDriver implements MatrizDistancia
{
    /** Velocidade média urbana assumida (km/h) para estimar a duração. */
    private const VELOCIDADE_MEDIA_KMH = 25.0;

    public function entre(float $origLat, float $origLng, float $destLat, float $destLng): array
    {
        $km = $this->haversineKm($origLat, $origLng, $destLat, $destLng);
        $min = self::VELOCIDADE_MEDIA_KMH > 0 ? ($km / self::VELOCIDADE_MEDIA_KMH) * 60 : 0.0;

        return ['distancia_km' => round($km, 2), 'duracao_min' => round($min, 1)];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
