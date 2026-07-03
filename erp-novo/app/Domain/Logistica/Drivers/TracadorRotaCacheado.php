<?php

namespace App\Domain\Logistica\Drivers;

use App\Domain\Logistica\Contracts\TracadorRota;
use App\Models\Logistica\RotaCache;

/**
 * DECORATOR de cache PERSISTENTE do traçado (L6 — economia da Routes API).
 *
 * Antes de consultar o driver interno (Google), procura o trajeto na base
 * `rotas_cache` pelo par de CÉLULAS (~100 m): hit = zero chamada externa, para
 * sempre. Miss = consulta o interno e SALVA — a base cresce com o uso e os
 * trajetos recorrentes da praça (mesmas ruas, mesmos bairros) param de custar.
 *
 * Bônus: mesmo com o driver interno desligado (sem key), trajetos já aprendidos
 * continuam sendo servidos do banco.
 */
class TracadorRotaCacheado implements TracadorRota
{
    public function __construct(private TracadorRota $interno) {}

    public function tracar(float $origLat, float $origLng, float $destLat, float $destLng): ?array
    {
        $ocell = RotaCache::cell($origLat, $origLng);
        $dcell = RotaCache::cell($destLat, $destLng);

        $hit = RotaCache::query()
            ->where('origem_cell', $ocell)
            ->where('destino_cell', $dcell)
            ->first();

        if ($hit) {
            $hit->increment('hits');

            return [
                'polyline' => $hit->polyline,
                'distancia_km' => (float) $hit->distancia_km,
                'duracao_min' => (float) $hit->duracao_min,
            ];
        }

        $novo = $this->interno->tracar($origLat, $origLng, $destLat, $destLng);
        if ($novo === null) {
            return null; // falha/sem key: não salva (o circuito do interno segura)
        }

        // firstOrCreate cobre corrida entre requests simultâneas (unique no par).
        RotaCache::query()->firstOrCreate(
            ['origem_cell' => $ocell, 'destino_cell' => $dcell],
            [
                'origem_lat' => $origLat, 'origem_lng' => $origLng,
                'destino_lat' => $destLat, 'destino_lng' => $destLng,
                'polyline' => $novo['polyline'],
                'distancia_km' => $novo['distancia_km'],
                'duracao_min' => $novo['duracao_min'],
                'hits' => 0,
            ],
        );

        return $novo;
    }
}
