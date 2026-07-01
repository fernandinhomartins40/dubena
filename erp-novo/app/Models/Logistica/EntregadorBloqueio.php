<?php

namespace App\Models\Logistica;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloqueio temporário de um entregador na distribuição (L1). Tenant-scoped.
 */
class EntregadorBloqueio extends Model
{
    use BelongsToTenant;

    protected $table = 'entregador_bloqueios';

    protected $fillable = [
        'empresa_id', 'entregador_user_id', 'operador_user_id', 'motivo', 'ate', 'ativo',
    ];

    protected function casts(): array
    {
        return ['ate' => 'datetime', 'ativo' => 'boolean'];
    }

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregador_user_id');
    }

    /** Bloqueio ainda em vigor (ativo e não expirado). */
    public function emVigor(): bool
    {
        return $this->ativo && ($this->ate === null || $this->ate->isFuture());
    }
}
