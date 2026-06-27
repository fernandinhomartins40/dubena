<?php

namespace App\Models\Monitora;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vértice de uma cerca poligonal (lat/lng) — paridade com cercapoligonos do legado.
 * Isolado por tenant (empresa_id/grupo_id próprios, herdados da cerca-mãe na criação)
 * para ficar coberto pela RLS — a tabela não tinha tenant até a F0.
 */
class CercaPonto extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herda empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['cerca_id' => 'monitora_cercas'];

    protected $table = 'monitora_cerca_pontos';

    protected $fillable = ['cerca_id', 'empresa_id', 'grupo_id', 'latitude', 'longitude', 'ordem'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'ordem' => 'integer',
        ];
    }

    public function cerca(): BelongsTo
    {
        return $this->belongsTo(Cerca::class);
    }
}
