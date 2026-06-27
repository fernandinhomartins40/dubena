<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Auditoria de segurança (A6) — leitura da trilha de eventos sensíveis e do
 * histórico de login. Gated por `auditoria.view`. Escopo por empresa (RLS +
 * filtro explícito por empresa ativa, defense-in-depth).
 */
class AuditoriaController extends Controller
{
    use AutorizaPorPermissao;

    /** Eventos de segurança (papel/usuário/2fa/403…), paginados e filtráveis. */
    public function eventos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'auditoria.view');

        $eventos = SecurityEvent::query()
            ->with('user:id,name')
            ->when($request->query('tipo'), fn ($q, $t) => $q->where('tipo', $t))
            ->when($request->query('inicio'), fn ($q, $i) => $q->where('criado_em', '>=', $i.' 00:00:00'))
            ->when($request->query('fim'), fn ($q, $f) => $q->where('criado_em', '<=', $f.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($eventos->items())->map(fn (SecurityEvent $e) => [
                'id' => $e->id,
                'tipo' => $e->tipo,
                'alvo' => $e->alvo,
                'detalhes' => $e->detalhes,
                'autor' => $e->user?->name,
                'ip' => $e->ip,
                'criado_em' => $e->criado_em,
            ]),
            'meta' => [
                'current_page' => $eventos->currentPage(),
                'last_page' => $eventos->lastPage(),
                'total' => $eventos->total(),
            ],
        ]);
    }

    /** Histórico de login (tentativas), da empresa ativa. */
    public function logins(Request $request): JsonResponse
    {
        $this->autorizar($request, 'auditoria.view');

        $empresaId = (int) $request->user()->empresa_id;

        $logs = LoginLog::query()
            // login_logs não é tenant-scoped (pré-login); filtra pela empresa do
            // usuário OU pelos logins sem empresa atribuída do mesmo período.
            ->where(fn ($q) => $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id'))
            ->when($request->boolean('apenas_falhas'), fn ($q) => $q->where('sucesso', false))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($logs->items())->map(fn (LoginLog $l) => [
                'id' => $l->id,
                'email' => $l->email,
                'sucesso' => $l->sucesso,
                'motivo' => $l->motivo,
                'ip' => $l->ip,
                'criado_em' => $l->criado_em,
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
