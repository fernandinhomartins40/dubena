<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Turno/escala de trabalho do colaborador (por dia da semana) — C5. */
class ColaboradorTurno extends Model
{
    protected $table = 'colaborador_turnos';

    protected $fillable = ['colaborador_id', 'dia_semana', 'entrada', 'saida'];

    protected function casts(): array
    {
        return ['dia_semana' => 'integer'];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }
}
