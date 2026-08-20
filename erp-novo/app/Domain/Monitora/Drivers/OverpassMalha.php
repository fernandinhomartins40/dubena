<?php

namespace App\Domain\Monitora\Drivers;

use App\Domain\Monitora\Contracts\MalhaViaria;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Malha viária pelo Overpass (OpenStreetMap).
 *
 * Escolhido em vez do Google porque a Roads API não devolve a malha — só
 * encaixa linha existente. O Overpass entrega as ruas como linhas com
 * geometria, que é o que permite fechar uma quadra.
 *
 * Gratuito e sem cota, mas é infraestrutura comunitária: por isso o
 * `User-Agent` identifica a aplicação (a política de uso exige) e o resultado
 * é cacheado por `MalhaCacheada`.
 */
class OverpassMalha implements MalhaViaria
{
    /**
     * Vias que entram na malha.
     *
     * `service` (acessos de estacionamento) e `footway` ficam de FORA: eles
     * fatiam a quadra por dentro e o contorno sairia menor que o quarteirão
     * real. `living_street` entra porque em loteamento residencial é a via
     * normal.
     */
    private const TIPOS = 'residential|tertiary|secondary|primary|unclassified|living_street|trunk|motorway|road';

    /** Teto do retângulo pedido, em graus (~11 km). Acima disso a consulta pesa demais. */
    private const LADO_MAXIMO = 0.1;

    public function __construct(
        private string $endpoint = 'https://overpass-api.de/api/interpreter',
        private int $timeout = 30,
    ) {}

    /** @return list<list<array{lat:float,lng:float}>> */
    public function vias(float $sul, float $oeste, float $norte, float $leste): array
    {
        // Retângulo grande demais derruba a consulta no servidor e devolve erro
        // depois de 30 s de espera. Recusar aqui é mais honesto que fazer o
        // operador esperar para nada.
        if (($norte - $sul) > self::LADO_MAXIMO || ($leste - $oeste) > self::LADO_MAXIMO) {
            return [];
        }

        $consulta = sprintf(
            '[out:json][timeout:%d];way["highway"~"^(%s)$"](%s,%s,%s,%s);out geom;',
            $this->timeout,
            self::TIPOS,
            $this->grau($sul),
            $this->grau($oeste),
            $this->grau($norte),
            $this->grau($leste),
        );

        try {
            $resposta = Http::timeout($this->timeout + 5)
                // A política de uso do Overpass pede identificação da
                // aplicação; requisição anônima em volume é bloqueada.
                ->withHeaders(['User-Agent' => 'ERP-Dubena/1.0 (geofencing)'])
                ->asForm()
                ->post($this->endpoint, ['data' => $consulta]);

            if (! $resposta->successful()) {
                Log::warning('Overpass respondeu '.$resposta->status());

                return [];
            }

            $vias = [];
            foreach ($resposta->json('elements') ?? [] as $elemento) {
                $linha = [];
                foreach ($elemento['geometry'] ?? [] as $ponto) {
                    $linha[] = ['lat' => (float) $ponto['lat'], 'lng' => (float) $ponto['lon']];
                }
                // Uma via precisa de dois pontos para ser aresta do grafo.
                if (count($linha) >= 2) {
                    $vias[] = $linha;
                }
            }

            return $vias;
        } catch (\Throwable $e) {
            Log::warning('Overpass indisponível: '.$e->getMessage());

            return [];
        }
    }

    /** Coordenada com 6 casas — precisão de ~0,1 m, suficiente e sem notação científica. */
    private function grau(float $v): string
    {
        return number_format($v, 6, '.', '');
    }
}
