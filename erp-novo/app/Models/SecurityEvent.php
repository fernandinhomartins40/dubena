<?php

namespace App\Models;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento de segurança (A6) — trilha de ações sensíveis de acesso (papel, 2FA,
 * autorização negada…). Escopado por empresa (RLS). `criado_em` no banco; sem
 * updated_at.
 */
class SecurityEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const CREATED_AT = 'criado_em';

    protected $table = 'security_events';

    protected $fillable = ['empresa_id', 'user_id', 'tipo', 'alvo', 'detalhes', 'ip', 'criado_em'];

    protected function casts(): array
    {
        return [
            'detalhes' => 'array',
            'criado_em' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
