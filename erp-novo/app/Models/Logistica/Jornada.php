<?php

namespace App\Models\Logistica;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Monitora\Veiculo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jornada (turno) do entregador (L0). O vínculo REAL entregador↔veículo: no início
 * do expediente o entregador escolhe o veículo do Monitora e preenche o checklist.
 * Uma jornada 'ativa' por entregador (índice parcial no PostgreSQL + guarda no
 * JornadaService). Tenant-scoped.
 */
class Jornada extends Model
{
    use BelongsToTenant;

    protected $table = 'jornadas';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'entregador_user_id', 'veiculo_id',
        'iniciada_em', 'encerrada_em', 'km_inicial', 'km_final', 'checklist', 'status',
    ];

    protected function casts(): array
    {
        return [
            'iniciada_em' => 'datetime',
            'encerrada_em' => 'datetime',
            'km_inicial' => 'integer',
            'km_final' => 'integer',
            'checklist' => 'array',
        ];
    }

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregador_user_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'veiculo_id');
    }

    public function estaAtiva(): bool
    {
        return $this->status === 'ativa';
    }
}
