<?php

namespace App\Domain\Monitora\Drivers;

use App\Domain\Monitora\Contracts\MalhaViaria;

/**
 * Malha viária sintética para a suíte e para ambiente sem rede.
 *
 * Devolve um XADREZ de ruas alinhado à grade, o que é o bastante para exercitar
 * a geometria: o grafo tem cruzamentos de verdade, as faces fecham, e uma
 * "quadra" tem tamanho conhecido — dá para afirmar no teste qual contorno
 * deveria sair de um clique.
 *
 * Não apagar: o CI depende dele, e sem ele a suíte chamaria o Overpass.
 */
class FakeMalhaViaria implements MalhaViaria
{
    /** Espaçamento entre ruas, em graus (~110 m na latitude de Guarapuava). */
    private const PASSO = 0.001;

    /** @return list<list<array{lat:float,lng:float}>> */
    public function vias(float $sul, float $oeste, float $norte, float $leste): array
    {
        $vias = [];
        $passo = self::PASSO;

        // Horizontais, de oeste a leste.
        for ($lat = $this->alinhar($sul); $lat <= $norte; $lat += $passo) {
            $linha = [];
            for ($lng = $this->alinhar($oeste); $lng <= $leste; $lng += $passo) {
                $linha[] = ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
            }
            if (count($linha) >= 2) {
                $vias[] = $linha;
            }
        }

        // Verticais, de sul a norte.
        for ($lng = $this->alinhar($oeste); $lng <= $leste; $lng += $passo) {
            $linha = [];
            for ($lat = $this->alinhar($sul); $lat <= $norte; $lat += $passo) {
                $linha[] = ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
            }
            if (count($linha) >= 2) {
                $vias[] = $linha;
            }
        }

        return $vias;
    }

    /** Desce a coordenada para o múltiplo do passo — é o que faz as ruas se cruzarem. */
    private function alinhar(float $v): float
    {
        return floor($v / self::PASSO) * self::PASSO;
    }
}
