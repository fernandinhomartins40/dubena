<?php

namespace App\Models\Monitora;

use Illuminate\Database\Eloquent\Model;

/**
 * Trecho de GPS já encaixado nas ruas (cache da Roads API).
 *
 * GLOBAL, sem `empresa_id` e sem RLS — de propósito, pelo mesmo motivo de
 * `rotas_cache`: por onde passa uma rua é fato geográfico público, não dado de
 * tenant. Compartilhar entre empresas maximiza o reuso e corta o custo.
 */
class ViaCache extends Model
{
    protected $table = 'monitora_vias_cache';

    protected $fillable = ['trecho', 'pontos', 'hits'];

    protected function casts(): array
    {
        return ['pontos' => 'array', 'hits' => 'integer'];
    }

    /**
     * Identidade do trecho: a sequência de células de ~100 m por onde ele passa.
     *
     * Arredondar a 3 casas (≈110 m) é o mesmo critério de `rotas_cache`. Dois
     * percursos que passam pelas mesmas células são o mesmo caminho para efeito
     * de desenho, e reaproveitar o encaixe de um no outro é o que faz o custo
     * cair com o uso.
     *
     * O hash mantém a coluna curta e indexável — um trecho de 100 pontos daria
     * uma string de 1,7 kB, que não cabe num índice B-tree do Postgres.
     *
     * @param  list<array{lat:float,lng:float}>  $pontos
     */
    public static function chaveDoTrecho(array $pontos): string
    {
        $celulas = array_map(
            fn ($p) => number_format($p['lat'], 3, '.', '').','.number_format($p['lng'], 3, '.', ''),
            $pontos,
        );

        return hash('xxh128', implode('|', $celulas));
    }
}
