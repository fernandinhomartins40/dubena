<?php

namespace App\Domain\Seguranca;

use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Registro de eventos de segurança (A6). Ponto único para gravar em
 * security_events: preenche autor (usuário logado), empresa (BelongsToTenant
 * resolve pelo tenant ativo) e IP. Tolerante a contexto sem request (CLI/job).
 *
 * Tipos convencionados: 'papel.criado|editado|excluido', 'papel.permissoes',
 * 'usuario.papeis', '2fa.habilitado|desabilitado', 'sessao.revogada',
 * 'autorizacao.negada'.
 */
class AuditoriaSeguranca
{
    /**
     * @param  array<string,mixed>  $detalhes
     */
    public function registrar(string $tipo, ?string $alvo = null, array $detalhes = []): void
    {
        SecurityEvent::create([
            'user_id' => Auth::id(),
            'tipo' => $tipo,
            'alvo' => $alvo,
            'detalhes' => $detalhes ?: null,
            'ip' => $this->ip(),
            // empresa_id é preenchido pelo BelongsToTenant a partir do tenant ativo.
        ]);
    }

    private function ip(): ?string
    {
        try {
            return Request::ip();
        } catch (\Throwable) {
            return null;
        }
    }
}
