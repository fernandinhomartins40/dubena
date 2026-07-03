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

    /** Circuito aberto: após uma falha, NENHUMA chamada por este intervalo. */
    private const CIRCUITO_TTL_S = 300;

    private const CIRCUITO_KEY = 'groutes:circuito-aberto';

    public function __construct(private string $apiKey) {}

    public function tracar(float $origLat, float $origLng, float $destLat, float $destLng): ?array
    {
        // CIRCUIT BREAKER: com a API desabilitada/quota estourada, cada request de
        // rota disparava dezenas de 403 (e Cache::remember NÃO guarda null) — o
        // endpoint levava 20s+ e o app dava timeout. Uma falha abre o circuito e
        // curto-circuita tudo por 5 min (traçado reto assume, nada quebra).
        if (Cache::get(self::CIRCUITO_KEY)) {
            return null;
        }

        $chave = 'groutes:'.md5("{$origLat},{$origLng}|{$destLat},{$destLng}");
        $memo = Cache::get($chave);
        if ($memo !== null) {
            return $memo['ok'] ? $memo['dados'] : null; // falha também é cacheada
        }

        try {
            $resp = Http::timeout(4)
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
            if (is_array($rota) && ! empty($rota['polyline']['encodedPolyline'])) {
                // duration vem como "123s" (string protobuf).
                $duracaoSeg = (float) rtrim((string) ($rota['duration'] ?? '0s'), 's');
                $dados = [
                    'polyline' => (string) $rota['polyline']['encodedPolyline'],
                    'distancia_km' => round(((float) ($rota['distanceMeters'] ?? 0)) / 1000, 2),
                    'duracao_min' => round($duracaoSeg / 60, 1),
                ];
                Cache::put($chave, ['ok' => true, 'dados' => $dados], self::CACHE_TTL_S);

                return $dados;
            }

            // Resposta sem rota (403/erro de API/ponto inalcançável).
            $this->registrarFalha($chave, $resp->status() >= 400);
        } catch (\Throwable) {
            $this->registrarFalha($chave, true); // rede/timeout
        }

        return null;
    }

    /** Cacheia a falha do par e, quando é erro de serviço, abre o circuito. */
    private function registrarFalha(string $chave, bool $abrirCircuito): void
    {
        Cache::put($chave, ['ok' => false], self::CACHE_TTL_S);
        if ($abrirCircuito) {
            Cache::put(self::CIRCUITO_KEY, true, self::CIRCUITO_TTL_S);
        }
    }
}
