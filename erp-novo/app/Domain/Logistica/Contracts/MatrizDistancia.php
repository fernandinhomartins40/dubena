<?php

namespace App\Domain\Logistica\Contracts;

/**
 * Gate de distância/tempo entre pontos (L5). O RoteirizadorService depende desta
 * abstração; o driver concreto é escolhido por env (Haversine grátis por padrão,
 * Google Distance Matrix quando GOOGLE_MAPS_KEY estiver setada). Assim ligamos o
 * Google sem tocar na lógica de roteirização.
 */
interface MatrizDistancia
{
    /**
     * Distância (km) e duração (minutos) de A para B.
     *
     * @return array{distancia_km: float, duracao_min: float}
     */
    public function entre(float $origLat, float $origLng, float $destLat, float $destLng): array;
}
