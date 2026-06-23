<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dependente/familiar do colaborador — C5. */
class ColaboradorFamilia extends Model
{
    protected $table = 'colaborador_familias';

    protected $fillable = ['colaborador_id', 'nome', 'parentesco', 'data_nascimento'];

    protected function casts(): array
    {
        return ['data_nascimento' => 'date'];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }
}
