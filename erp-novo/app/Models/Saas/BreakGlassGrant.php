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

    /** Só olhar: não exige aprovação de um segundo administrador. */
    public const ESCOPO_LEITURA = 'LEITURA';

    /** Mexe em dado/dinheiro: exige aprovação de PlatformAdmin distinto. */
    public const ESCOPO_OPERACAO = 'OPERACAO';

    protected $fillable = [
        'user_id', 'empresa_id', 'escopo', 'motivo', 'ticket_ref',
        'concedido_por_platform_admin_id', 'aprovado_em', 'aprovado_por_platform_admin_id',
        'twofa_verificado_em', 'inicia_em', 'expira_em',
        'revogado_em', 'revogado_motivo',
    ];

    protected function casts(): array
    {
        return [
            'inicia_em' => 'datetime',
            'expira_em' => 'datetime',
            'revogado_em' => 'datetime',
            'aprovado_em' => 'datetime',
            'twofa_verificado_em' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Vigente = comecou, nao expirou, nao foi revogada, teve 2FA conferido e —
     * quando o escopo e OPERACAO — foi aprovada por um segundo administrador.
     *
     * Fail-closed por construcao: concessao gravada sem 2FA ou sem a aprovacao
     * exigida nao autoriza nada, mesmo dentro do prazo.
     */
    public function vigente(): bool
    {
        $agora = now();

        $noPrazo = $this->revogado_em === null
            && $this->inicia_em <= $agora
            && $this->expira_em > $agora;

        if (! $noPrazo || $this->twofa_verificado_em === null) {
            return false;
        }

        return $this->escopo !== self::ESCOPO_OPERACAO || $this->aprovado_em !== null;
    }
}
