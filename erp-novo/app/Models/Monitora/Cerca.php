<?php

namespace App\Models\Monitora;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cerca virtual (geofencing). Modelo do legado (ctrl-web): POLÍGONO livre com N
 * vértices (relação `pontos`), além de cor/setor opcionais. Mantém centro/raio
 * nullable para um possível modo círculo no futuro.
 */
class Cerca extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'monitora_cercas';

    protected $fillable = ['empresa_id', 'grupo_id', 'descricao', 'cor', 'setor_id', 'centro_lat', 'centro_lng', 'raio_metros', 'ativo'];

    protected function casts(): array
    {
        return [
            'centro_lat' => 'decimal:7',
            'centro_lng' => 'decimal:7',
            'raio_metros' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    /** Vértices do polígono, em ordem. */
    public function pontos(): HasMany
    {
        return $this->hasMany(CercaPonto::class)->orderBy('ordem');
    }
}
