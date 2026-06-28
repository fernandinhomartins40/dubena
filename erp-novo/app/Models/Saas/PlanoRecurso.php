<?php

namespace App\Models\Saas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recurso (feature-flag) liberado por um plano (P2) — GLOBAL. `recurso_chave` é
 * uma chave do RecursoCatalogo (ex.: 'marketplace').
 */
class PlanoRecurso extends Model
{
    protected $table = 'plano_recurso';

    protected $fillable = ['plano_id', 'recurso_chave'];

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }
}
