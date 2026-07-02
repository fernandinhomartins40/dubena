<?php

namespace App\Models\Logistica;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Config de distribuição por empresa (L3). Tenant-scoped.
 */
class LogisticaConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'logistica_configs';

    protected $fillable = [
        'empresa_id', 'modo', 'peso_distancia', 'peso_carga', 'raio_maximo_km', 'teto_carga',
    ];

    protected function casts(): array
    {
        return [
            'peso_distancia' => 'float',
            'peso_carga' => 'float',
            'raio_maximo_km' => 'integer',
            'teto_carga' => 'integer',
        ];
    }

    public const MODO_SUGERIR = 'sugerir';

    public const MODO_AUTO = 'auto';

    public function ehAuto(): bool
    {
        return $this->modo === self::MODO_AUTO;
    }
}
