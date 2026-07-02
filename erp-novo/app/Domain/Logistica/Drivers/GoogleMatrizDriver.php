<?php

namespace App\Domain\Logistica\Drivers;

use App\Domain\Logistica\Contracts\MatrizDistancia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Driver Google Distance Matrix (L5) — distância/tempo REAIS (com trânsito). Pago
 * por chamada, então: cache por par origem→destino (TTL curto) para conter custo,
 * e FALLBACK para Haversine em qualquer falha (rede/quota/chave). Ativado só quando
 * GOOGLE_MAPS_KEY está setada (o container escolhe o driver por env).
 */
class GoogleMatrizDriver implements MatrizDistancia
{
    private const CACHE_TTL_S = 120; // trânsito muda; 2 min é suficiente e barato

    public function __construct(
        private string $apiKey,
        private HaversineDriver $fallback,
    ) {}

    public function entre(float $origLat, float $origLng, float $destLat, float $destLng): array
    {
        $chave = 'gmatrix:'.md5("{$origLat},{$origLng}|{$destLat},{$destLng}");

        return Cache::remember($chave, self::CACHE_TTL_S, function () use ($origLat, $origLng, $destLat, $destLng) {
            try {
                $resp = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                    'origins' => "{$origLat},{$origLng}",
                    'destinations' => "{$destLat},{$destLng}",
                    'departure_time' => 'now', // habilita duration_in_traffic
                    'mode' => 'driving',
                    'key' => $this->apiKey,
                ]);

                $el = $resp->json('rows.0.elements.0');
                if (($el['status'] ?? null) === 'OK') {
                    $distKm = ((float) ($el['distance']['value'] ?? 0)) / 1000;
                    $durSeg = (float) ($el['duration_in_traffic']['value'] ?? $el['duration']['value'] ?? 0);

                    return ['distancia_km' => round($distKm, 2), 'duracao_min' => round($durSeg / 60, 1)];
                }
            } catch (\Throwable) {
                // qualquer erro → fallback (nunca derruba a roteirização).
            }

            return $this->fallback->entre($origLat, $origLng, $destLat, $destLng);
        });
    }
}
