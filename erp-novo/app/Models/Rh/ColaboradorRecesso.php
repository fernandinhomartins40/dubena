<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Recesso/férias/afastamento do colaborador — C5. */
class ColaboradorRecesso extends Model
{
    protected $table = 'colaborador_recessos';

    protected $fillable = ['colaborador_id', 'tipo', 'inicio', 'fim', 'observacao'];

    protected function casts(): array
    {
        return ['inicio' => 'date', 'fim' => 'date'];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }
}
