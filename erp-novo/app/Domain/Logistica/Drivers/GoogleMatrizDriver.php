<?php

namespace App\Domain\Logistica\Drivers;

use App\Domain\Logistica\Contracts\MatrizDistancia;

/**
 * Driver Google de distância/tempo (L5) — delega à ROUTES API v2 via
 * GoogleRoutesDriver (a Distance Matrix legada foi bloqueada pelo Google para
 * projetos novos; a Routes devolve distância+tempo com trânsito na mesma chamada,
 * já com cache). FALLBACK para Haversine em qualquer falha — a roteirização nunca
 * cai. Ativado só quando GOOGLE_MAPS_KEY está setada (bind por env).
 */
class GoogleMatrizDriver implements MatrizDistancia
{
    public function __construct(
        private GoogleRoutesDriver $routes,
        private HaversineDriver $fallback,
    ) {}

    public function entre(float $origLat, float $origLng, float $destLat, float $destLng): array
    {
        $r = $this->routes->tracar($origLat, $origLng, $destLat, $destLng);
        if ($r !== null) {
            return ['distancia_km' => $r['distancia_km'], 'duracao_min' => $r['duracao_min']];
        }

        return $this->fallback->entre($origLat, $origLng, $destLat, $destLng);
    }
}
