<?php

namespace App\Domain\Logistica\Drivers;

use App\Domain\Logistica\Contracts\TracadorRota;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Driver Google ROUTES API (v2 computeRoutes) — a sucessora oficial da Directions
 * legada (que o Google bloqueou para projetos novos). Devolve polyline encodada +
 * distância/tempo com trânsito. Pago por chamada → cache por par origem→destino
 * (TTL curto) e FieldMask mínima. Falha-fecha: qualquer erro devolve null (o app
 * desenha o traçado reto; nunca derruba a rota).
 */
class GoogleRoutesDriver implements TracadorRota
{
    private const CACHE_TTL_S = 180;

    public function __construct(private string $apiKey) {}

    public function tracar(float $origLat, float $origLng, float $destLat, float $destLng): ?array
    {
        $chave = 'groutes:'.md5("{$origLat},{$origLng}|{$destLat},{$destLng}");

        return Cache::remember($chave, self::CACHE_TTL_S, function () use ($origLat, $origLng, $destLat, $destLng) {
            try {
                $resp = Http::timeout(8)
                    ->withHeaders([
                        'X-Goog-Api-Key' => $this->apiKey,
                        'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline',
                    ])
                    ->post('https://routes.googleapis.com/directions/v2:computeRoutes', [
                        'origin' => ['location' => ['latLng' => ['latitude' => $origLat, 'longitude' => $origLng]]],
                        'destination' => ['location' => ['latLng' => ['latitude' => $destLat, 'longitude' => $destLng]]],
                        'travelMode' => 'DRIVE',
                        'routingPreference' => 'TRAFFIC_AWARE',
                    ]);

                $rota = $resp->json('routes.0');
                if (! is_array($rota) || empty($rota['polyline']['encodedPolyline'])) {
                    return null;
                }

                // duration vem como "123s" (string protobuf).
                $duracaoSeg = (float) rtrim((string) ($rota['duration'] ?? '0s'), 's');

                return [
                    'polyline' => (string) $rota['polyline']['encodedPolyline'],
                    'distancia_km' => round(((float) ($rota['distanceMeters'] ?? 0)) / 1000, 2),
                    'duracao_min' => round($duracaoSeg / 60, 1),
                ];
            } catch (\Throwable) {
                return null; // sem key válida/quota/rede — o traçado reto assume.
            }
        });
    }
}
