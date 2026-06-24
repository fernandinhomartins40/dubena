<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Número de sorteio atribuído a um cliente — C10. Leaf puro: acessado via o
 * Sorteio pai (grupo-scoped). Sem coluna de tenant própria; isolamento pelo pai.
 */
class SorteioNumero extends Model
{
    protected $table = 'sorteio_numeros';

    protected $fillable = ['sorteio_id', 'cliente_id', 'numero'];

    public function sorteio(): BelongsTo
    {
        return $this->belongsTo(Sorteio::class);
    }
}
