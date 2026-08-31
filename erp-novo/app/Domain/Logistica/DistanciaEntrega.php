<?php

namespace App\Domain\Logistica;

use App\Domain\Shared\Geo;
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

    /**
     * F6-04 — delega ao ponto unico (`Geo`), em vez de reimplementar.
     *
     * A copia local usava raio 6371.0 e o `Geo` usa 6_371_000 m; a diferenca
     * numerica e de centimetros, e nao era esse o problema. O problema e que
     * quatro copias da mesma formula significam que uma correcao alcanca uma so
     * — e o `Geo` foi criado exatamente para acabar com isso (Q-4 da auditoria),
     * antes de as copias voltarem.
     */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return Geo::km($lat1, $lng1, $lat2, $lng2);
    }
}
