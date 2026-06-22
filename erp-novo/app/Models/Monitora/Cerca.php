<?php

namespace App\Models\Monitora;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cerca virtual (geofencing): centro + raio em metros.
 */
class Cerca extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'monitora_cercas';

    protected $fillable = ['empresa_id', 'descricao', 'centro_lat', 'centro_lng', 'raio_metros', 'ativo'];

    protected function casts(): array
    {
        return [
            'centro_lat' => 'decimal:7',
            'centro_lng' => 'decimal:7',
            'raio_metros' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }
}
