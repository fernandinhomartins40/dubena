<?php

namespace App\Models\Rh;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Turno/escala de trabalho do colaborador (por dia da semana) — C5. */
class ColaboradorTurno extends Model
{
    use BelongsToTenant;

    /** @var array<string,string> FK => tabela do pai (herança de empresa_id na criação sem tenant ativo). */
    protected $tenantParent = ['colaborador_id' => 'colaboradores'];

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
