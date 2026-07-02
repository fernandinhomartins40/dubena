<?php

namespace App\Domain\Logistica\Contracts;

/**
 * Gate de TRAÇADO de rota (L6) — devolve o desenho do caminho REAL (polyline
 * encodada) e distância/tempo de A→B pelas ruas. Driver real = Google Routes API
 * (a Directions legada foi descontinuada pelo Google); sem key/na falha, o app
 * cai para o traçado reto entre paradas (SemTracado).
 */
interface TracadorRota
{
    /**
     * Traça A→B. Null quando indisponível (sem key, quota, erro de rede).
     *
     * @return array{polyline: string, distancia_km: float, duracao_min: float}|null
     */
    public function tracar(float $origLat, float $origLng, float $destLat, float $destLng): ?array;
}
