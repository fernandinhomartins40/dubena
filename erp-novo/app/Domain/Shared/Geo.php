<?php

namespace App\Domain\Shared;

/**
 * Utilitário de GEODISTÂNCIA — ponto único do Haversine (Q-4 da auditoria).
 *
 * A mesma fórmula estava reimplementada em 5+ services (Distribuidor, Roteirizador,
 * PedidoMobile, Missao, Monitora), cada uma com seu raio da Terra hard-coded. Aqui
 * fica UMA implementação, testável, com km e metros. Precisão de Haversine é
 * suficiente para as escalas do produto (praças urbanas → rotas de bairro).
 */
final class Geo
{
    /** Raio médio da Terra em metros. */
    private const RAIO_M = 6_371_000.0;

    /** Distância entre dois pontos (lat/lng em graus) em METROS. */
    public static function metros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::RAIO_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Distância entre dois pontos em QUILÔMETROS. */
    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return self::metros($lat1, $lng1, $lat2, $lng2) / 1000;
    }

    /**
     * Bounding box (delta em graus) para um raio em METROS a partir de uma latitude.
     * Serve para PRÉ-FILTRAR candidatos numa query indexada (lat/lng BETWEEN …)
     * antes do cálculo fino do Haversine — evita varrer a tabela inteira (PF-1).
     *
     * @return array{lat_delta: float, lng_delta: float}
     */
    public static function boundingBox(float $lat, float $raioMetros): array
    {
        // 1 grau de latitude ≈ 111.32 km em qualquer lugar; longitude encolhe com cos(lat).
        $latDelta = $raioMetros / 111_320.0;
        $cos = max(0.00001, cos(deg2rad($lat)));
        $lngDelta = $raioMetros / (111_320.0 * $cos);

        return ['lat_delta' => $latDelta, 'lng_delta' => $lngDelta];
    }
}
