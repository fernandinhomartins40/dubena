<?php

namespace App\Models\Missao;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Execução de uma missão por um entregador (L7). Carrega o ciclo de vida
 * (atribuída → em andamento → concluída/adiada/cancelada), o ADIAMENTO com
 * aprovação (ETAPA 11) e a AUDITORIA do operador (L9). Tenant-scoped.
 */
class MissaoAtribuicao extends Model
{
    use BelongsToTenant;

    protected $table = 'missao_atribuicoes';

    protected $fillable = [
        'empresa_id', 'missao_id', 'entregador_user_id', 'status', 'automatica',
        'iniciada_em', 'concluida_em',
        'adiamento_motivo', 'adiamento_detalhe', 'adiada_em', 'adiamento_aprovacao',
        'auditoria_resultado', 'auditoria_user_id', 'auditoria_em', 'auditoria_observacao', 'auditoria_sancao',
    ];

    protected function casts(): array
    {
        return [
            'automatica' => 'boolean',
            'iniciada_em' => 'datetime',
            'concluida_em' => 'datetime',
            'adiada_em' => 'datetime',
            'auditoria_em' => 'datetime',
        ];
    }

    public function missao(): BelongsTo
    {
        return $this->belongsTo(Missao::class, 'missao_id');
    }

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregador_user_id');
    }

    public function visitas(): HasMany
    {
        return $this->hasMany(MissaoVisita::class, 'missao_atribuicao_id');
    }

    public function trilha(): HasMany
    {
        return $this->hasMany(MissaoTrilha::class, 'missao_atribuicao_id');
    }

    public function emExecucao(): bool
    {
        return in_array($this->status, ['atribuida', 'em_andamento'], true);
    }
}
