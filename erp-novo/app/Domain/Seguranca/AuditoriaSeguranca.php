<?php

namespace App\Domain\Seguranca;

use App\Domain\Auditoria\ContextoAuditoria;
use App\Domain\Tenant\TenantContext;
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
        $campos = [
            // F2-06: tenant e correlacao unificados com as demais trilhas.
            ...app(ContextoAuditoria::class)->campos(),
            'user_id' => Auth::id(),
            'tipo' => $tipo,
            'alvo' => $alvo,
            'detalhes' => $detalhes ?: null,
            'ip' => $this->ip(),
            // empresa_id é preenchido pelo BelongsToTenant a partir do tenant ativo.
        ];

        // PIE P2-D-01: evento ANTES do envelope (o `login.suporte`) sai sem
        // tenant. A policy aceita gravá-lo, mas o `RETURNING id` do Eloquent faz
        // o Postgres exigir também o USING de leitura — que, de propósito, não
        // mostra linha sem tenant a revenda nenhuma. Insert sem RETURNING.
        // O insert direto não dispara o `creating` do BelongsToTenant: a empresa
        // vem do contexto aqui, como o trait faria.
        if ($campos['tenant_account_id'] === null) {
            SecurityEvent::query()->insert([
                ...$campos,
                'empresa_id' => app(TenantContext::class)->empresaId(),
                'detalhes' => $campos['detalhes'] === null ? null : json_encode($campos['detalhes']),
                'criado_em' => now(),
            ]);

            return;
        }

        SecurityEvent::create($campos);
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
