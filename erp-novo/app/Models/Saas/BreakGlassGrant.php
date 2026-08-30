<?php

namespace App\Models\Saas;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Concessao temporaria de acesso elevado (F2-05).
 *
 * Substitui o bypass permanente de `users.support`: aqui o acesso tem alvo,
 * motivo, prazo e autor, e expira sozinho.
 */
class BreakGlassGrant extends Model
{
    protected $table = 'break_glass_grants';

    protected $fillable = [
        'user_id', 'empresa_id', 'motivo', 'ticket_ref',
        'concedido_por_platform_admin_id', 'inicia_em', 'expira_em',
        'revogado_em', 'revogado_motivo',
    ];

    protected function casts(): array
    {
        return [
            'inicia_em' => 'datetime',
            'expira_em' => 'datetime',
            'revogado_em' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Vigente = comecou, nao expirou e nao foi revogada. */
    public function vigente(): bool
    {
        $agora = now();

        return $this->revogado_em === null
            && $this->inicia_em <= $agora
            && $this->expira_em > $agora;
    }
}
