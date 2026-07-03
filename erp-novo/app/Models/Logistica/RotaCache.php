<?php

namespace App\Models\Logistica;

use Illuminate\Database\Eloquent\Model;

/**
 * Trajeto cacheado por par de células de grade (~100 m) — L6. GLOBAL (sem tenant):
 * trajeto pelas ruas é fato geográfico, compartilhado entre empresas para
 * maximizar o reuso e cortar custo da Routes API.
 */
class RotaCache extends Model
{
    protected $table = 'rotas_cache';

    protected $fillable = [
        'origem_cell', 'destino_cell',
        'origem_lat', 'origem_lng', 'destino_lat', 'destino_lng',
        'polyline', 'distancia_km', 'duracao_min', 'hits',
    ];

    protected function casts(): array
    {
        return [
            'distancia_km' => 'float',
            'duracao_min' => 'float',
            'hits' => 'integer',
        ];
    }

    /** Célula de grade (~100 m): lat/lng arredondados a 3 casas decimais. */
    public static function cell(float $lat, float $lng): string
    {
        return number_format($lat, 3, '.', '').','.number_format($lng, 3, '.', '');
    }
}
