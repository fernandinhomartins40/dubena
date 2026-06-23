<?php

namespace App\Models\Rh;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Exame ocupacional (ASO) do colaborador — C5. */
class ColaboradorExame extends Model
{
    protected $table = 'colaborador_exames';

    protected $fillable = ['colaborador_id', 'tipo', 'realizado_em', 'vencimento', 'resultado', 'medico', 'observacao'];

    protected function casts(): array
    {
        return ['realizado_em' => 'date', 'vencimento' => 'date'];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }
}
