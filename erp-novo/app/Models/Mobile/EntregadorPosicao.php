<?php

namespace App\Models\Mobile;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot da última posição de um entregador (P6) — TENANT. 1 linha por
 * entregador, atualizada a cada ping do app. O trajeto contínuo (efêmero) vai
 * para Redis; aqui fica só o "onde está agora".
 */
class EntregadorPosicao extends Model
{
    use BelongsToTenant;

    protected $table = 'entregador_posicoes_ultima';

    protected $fillable = [
        'empresa_id', 'entregador_user_id', 'latitude', 'longitude',
        'velocidade', 'direcao', 'atualizado_em',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'velocidade' => 'decimal:2',
            'direcao' => 'integer',
            'atualizado_em' => 'datetime',
        ];
    }

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregador_user_id');
    }
}
