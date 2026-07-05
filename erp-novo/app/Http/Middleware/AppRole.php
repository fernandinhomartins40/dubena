<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * FASE 3 (PLANO_SEGURANCA_MULTITENANT_APPS) — separação de PAPEL nos tokens do app.
 *
 * Os logins do app emitem tokens com ability de papel (`role:cliente` no login do
 * cliente, `role:entregador` no login por e-mail/senha). Este middleware exige o
 * papel nas sub-rotas: token de CLIENTE não alcança rota de entregador (frota,
 * jornada, missões) e vice-versa.
 *
 * A checagem vale para TOKENS PESSOAIS (Bearer dos apps). Autenticação stateful
 * (SPA por cookie — staff do back-office) e tokens curinga (`*`) passam: a
 * separação aqui é entre os DOIS apps, não um RBAC do back-office (esse é o
 * middleware `permissao`).
 */
class AppRole
{
    public function handle(Request $request, Closure $next, string $papel): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can('role:'.$papel)) {
            return response()->json(['message' => 'Acesso não permitido para este perfil.'], 403);
        }

        return $next($request);
    }
}
