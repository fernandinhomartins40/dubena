<?php

namespace App\Models\Monitora;

use Illuminate\Database\Eloquent\Model;

/**
 * Malha viária de uma região, já baixada do OpenStreetMap.
 *
 * GLOBAL, sem `empresa_id` e sem RLS — mesmo critério de `monitora_vias_cache`:
 * por onde passa a Rua XV de Novembro é fato geográfico público, não dado de
 * tenant. Compartilhar entre empresas maximiza o reuso.
 *
 * O cache aqui não é economia de dinheiro (o Overpass é gratuito) e sim de
 * TEMPO e de educação: a consulta leva ~2 s e o serviço é comunitário, com
 * política de uso justo. Refazer a mesma consulta a cada clique do operador
 * seria abusivo e deixaria a ferramenta lenta justamente no gesto que precisa
 * ser instantâneo.
 */
class MalhaCache extends Model
{
    protected $table = 'monitora_malha_cache';

    protected $fillable = ['regiao', 'vias', 'hits'];

    protected function casts(): array
    {
        return ['vias' => 'array', 'hits' => 'integer'];
    }

    /**
     * Identidade da região: o retângulo pedido, arredondado para uma grade fixa.
     *
     * Arredondar para múltiplos de 0,01° (~1,1 km) faz dois cliques na mesma
     * vizinhança caírem no MESMO retângulo — que é o ponto: sem isso cada
     * clique geraria uma chave nova e o cache nunca acertaria.
     */
    public static function chaveDaRegiao(float $sul, float $oeste, float $norte, float $leste): string
    {
        $g = fn (float $v): string => number_format(floor($v * 100) / 100, 2, '.', '');

        return $g($sul).','.$g($oeste).','.$g($norte).','.$g($leste);
    }
}
