<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\SuperAdmin\AuthController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PIE P2-E-06 — só o token de OPERAÇÃO abre as rotas da plataforma.
 *
 * O token de cadastro do 2FA prova apenas a senha. Se ele abrisse o painel, o
 * 2FA obrigatório voltaria a ser decorativo: bastaria nunca confirmar o
 * cadastro. Fica depois de `auth:platform`.
 */
class SessaoDeOperacaoDaPlataforma
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->tokenCan(AuthController::ABILITY_OPERACAO)) {
            return response()->json([
                'message' => 'Cadastre a verificação em duas etapas para continuar.',
                'two_factor_setup_required' => true,
            ], 423);
        }

        return $next($request);
    }
}
