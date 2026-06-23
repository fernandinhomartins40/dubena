<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de ponto do colaborador (entrada/saída efetivas) — C5. */
class ColaboradorPonto extends Model
{
    protected $table = 'colaborador_pontos';

    protected $fillable = ['colaborador_id', 'data', 'entrada', 'saida'];

    protected function casts(): array
    {
        return ['data' => 'date'];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }
}
