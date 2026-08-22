<?php

namespace App\Domain\Logistica;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;

/**
 * Distância em linha reta entre a empresa e o cliente.
 *
 * Linha reta (haversine), não rota de rua: o cálculo é local, instantâneo e
 * não depende de cota de API externa. Para faixas de preço ("até 5 km custa
 * X") a diferença para a rota real não muda a faixa na prática — e chamar o
 * Google a cada cálculo de taxa custaria dinheiro por pedido.
 */
class DistanciaEntrega
{
    /** Raio médio da Terra em km. */
    private const RAIO_TERRA_KM = 6371.0;

    /** @var array<int, array{lat: float, lng: float}|null> */
    private array $cacheEmpresa = [];

    /**
     * Distância até o cliente, ou null quando não dá para saber.
     *
     * Devolve NULL — nunca zero — quando falta coordenada de um dos lados.
     * Zero significaria "está na porta" e daria a faixa mais barata justamente
     * a quem o sistema não sabe onde mora.
     */
    public function emKm(?Cliente $cliente): ?float
    {
        if ($cliente === null || $cliente->latitude === null || $cliente->longitude === null) {
            return null;
        }

        $origem = $this->coordenadaDaEmpresa((int) $cliente->empresa_id);
        if ($origem === null) {
            return null;
        }

        return $this->haversine(
            $origem['lat'], $origem['lng'],
            (float) $cliente->latitude, (float) $cliente->longitude,
        );
    }

    /** @return array{lat: float, lng: float}|null */
    private function coordenadaDaEmpresa(int $empresaId): ?array
    {
        if (array_key_exists($empresaId, $this->cacheEmpresa)) {
            return $this->cacheEmpresa[$empresaId];
        }

        $empresa = Empresa::query()->find($empresaId, ['id', 'latitude', 'longitude']);

        $coord = ($empresa?->latitude !== null && $empresa?->longitude !== null)
            ? ['lat' => (float) $empresa->latitude, 'lng' => (float) $empresa->longitude]
            : null;

        return $this->cacheEmpresa[$empresaId] = $coord;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::RAIO_TERRA_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
