<?php

namespace App\Models\Monitora;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vértice de uma cerca poligonal (lat/lng) — paridade com cercapoligonos do legado.
 * Escopo herdado da cerca-mãe (sem tenant próprio).
 */
class CercaPonto extends Model
{
    protected $table = 'monitora_cerca_pontos';

    protected $fillable = ['cerca_id', 'latitude', 'longitude', 'ordem'];

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
