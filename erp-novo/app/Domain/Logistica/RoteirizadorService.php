<?php

namespace App\Domain\Logistica;

use App\Domain\Logistica\Contracts\MatrizDistancia;
use App\Domain\Logistica\Contracts\TracadorRota;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Mobile\EntregadorPosicao;
use App\Models\Pedido\Pedido;

/**
 * RoteirizadorService (L5) — dado o conjunto de entregas ATIVAS de um entregador,
 * resolve a SEQUÊNCIA (ordem de visita) e devolve distância/ETA por parada e a
 * ordem otimizada. O app nunca deixa o entregador escolher aleatoriamente: o ERP
 * manda a rota.
 *
 * Heurística: NEAREST-NEIGHBOR a partir da posição atual do entregador (ou da 1ª
 * parada com coordenada, se não houver posição). É o TSP aproximado clássico —
 * barato e bom o bastante para rotas de bairro. A matriz de distância/tempo vem do
 * driver injetado (Haversine grátis; Google Distance Matrix quando ligado).
 */
class RoteirizadorService
{
    public function __construct(
        private MatrizDistancia $matriz,
        private TracadorRota $tracador,
    ) {}

    /**
     * Rota atual do entregador (sequência + distância total + ETA).
     *
     * @return array{
     *   paradas: list<array<string,mixed>>,
     *   distancia_total_km: float,
     *   duracao_total_min: float,
     *   proximo: array<string,mixed>|null
     * }
     */
    public function rotaDoEntregador(int $empresaId, int $entregadorUserId): array
    {
        $pedidos = Pedido::query()
            ->where('empresa_id', $empresaId)
            ->where('entregador_user_id', $entregadorUserId)
            ->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::PENDENTE->value))
            ->with('cliente:id,nome,endereco,numero,latitude,longitude')
            ->get();

        // Só entram na sequência as paradas com coordenada; as sem geo vão ao fim.
        $comGeo = $pedidos->filter(fn (Pedido $p) => $p->cliente?->latitude !== null && $p->cliente?->longitude !== null);
        $semGeo = $pedidos->reject(fn (Pedido $p) => $p->cliente?->latitude !== null && $p->cliente?->longitude !== null);

        $pos = EntregadorPosicao::query()->where('entregador_user_id', $entregadorUserId)->first();
        $partidaLat = $pos ? (float) $pos->latitude : ($comGeo->first()?->cliente->latitude !== null ? (float) $comGeo->first()->cliente->latitude : null);
        $partidaLng = $pos ? (float) $pos->longitude : ($comGeo->first()?->cliente->longitude !== null ? (float) $comGeo->first()->cliente->longitude : null);

        $ordenados = $this->nearestNeighbor($comGeo->all(), $partidaLat, $partidaLng);

        $paradas = [];
        $distTotal = 0.0;
        $durTotal = 0.0;
        $curLat = $partidaLat;
        $curLng = $partidaLng;
        $seq = 1;

        foreach ($ordenados as $p) {
            $lat = (float) $p->cliente->latitude;
            $lng = (float) $p->cliente->longitude;

            // Traçado REAL do trecho (Routes API): polyline + distância/tempo pelas
            // ruas. Sem key/na falha: métricas da matriz (Haversine) e sem polyline
            // (o app liga as paradas com linha reta).
            $tracado = ($curLat !== null && $curLng !== null)
                ? $this->tracador->tracar($curLat, $curLng, $lat, $lng)
                : null;

            $trecho = $tracado
                ?? (($curLat !== null && $curLng !== null)
                    ? $this->matriz->entre($curLat, $curLng, $lat, $lng)
                    : ['distancia_km' => 0.0, 'duracao_min' => 0.0]);

            $distTotal += $trecho['distancia_km'];
            $durTotal += $trecho['duracao_min'];

            $paradas[] = $this->parada($p, $seq++, $trecho, round($durTotal, 1), $tracado['polyline'] ?? null);
            $curLat = $lat;
            $curLng = $lng;
        }

        // Paradas sem coordenada entram ao final, sem métricas de trecho.
        foreach ($semGeo as $p) {
            $paradas[] = $this->parada($p, $seq++, ['distancia_km' => null, 'duracao_min' => null], null, null);
        }

        return [
            'paradas' => $paradas,
            'distancia_total_km' => round($distTotal, 2),
            'duracao_total_min' => round($durTotal, 1),
            'proximo' => $paradas[0] ?? null,
        ];
    }

    /**
     * Ordena por vizinho-mais-próximo a partir de (lat,lng). Se não houver ponto de
     * partida, devolve na ordem recebida.
     *
     * PERFORMANCE: a comparação é O(N²) — usa SEMPRE Haversine local (zero rede).
     * Chamar a Google aqui derrubava o endpoint (com 15 entregas eram ~120 chamadas
     * HTTP por request → 22s+ e timeout no app). A Google entra só no traçado final
     * dos trechos escolhidos (N chamadas, com cache), em rotaDoEntregador().
     *
     * @param  list<Pedido>  $pedidos
     * @return list<Pedido>
     */
    private function nearestNeighbor(array $pedidos, ?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null || count($pedidos) <= 1) {
            return $pedidos;
        }

        $restantes = $pedidos;
        $ordem = [];
        $curLat = $lat;
        $curLng = $lng;

        while ($restantes !== []) {
            $melhorIdx = 0;
            $melhorDist = PHP_FLOAT_MAX;
            foreach ($restantes as $i => $p) {
                $d = $this->haversineKm($curLat, $curLng, (float) $p->cliente->latitude, (float) $p->cliente->longitude);
                if ($d < $melhorDist) {
                    $melhorDist = $d;
                    $melhorIdx = $i;
                }
            }
            $escolhido = $restantes[$melhorIdx];
            $ordem[] = $escolhido;
            $curLat = (float) $escolhido->cliente->latitude;
            $curLng = (float) $escolhido->cliente->longitude;
            array_splice($restantes, $melhorIdx, 1);
        }

        return $ordem;
    }

    /** Haversine local (km) — comparação de proximidade sem custo de rede. */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  array{distancia_km: float|null, duracao_min: float|null}  $trecho
     * @return array<string,mixed>
     */
    private function parada(Pedido $p, int $seq, array $trecho, ?float $etaMin, ?string $polyline): array
    {
        return [
            'sequencia' => $seq,
            'pedido_id' => $p->id,
            'cliente' => $p->cliente?->nome,
            'endereco' => trim(($p->cliente?->endereco ?? '').', '.($p->cliente?->numero ?? '')),
            'lat' => $p->cliente?->latitude !== null ? (float) $p->cliente->latitude : null,
            'lng' => $p->cliente?->longitude !== null ? (float) $p->cliente->longitude : null,
            'distancia_trecho_km' => $trecho['distancia_km'],
            'duracao_trecho_min' => $trecho['duracao_min'],
            'eta_min' => $etaMin,
            // Traçado do trecho pelas ruas (Routes API) — null sem key (app usa reta).
            'polyline' => $polyline,
        ];
    }
}
