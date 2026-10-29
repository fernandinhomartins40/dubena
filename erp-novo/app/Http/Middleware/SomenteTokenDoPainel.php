<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * PIE R1-01 — o back-office (`/api/admin`) só aceita o token do painel.
 *
 * Os apps emitem tokens com ability de PAPEL (`role:cliente`, `role:entregador`
 * …) e o `approle` separa os dois apps entre si — mas nada impedia um token de
 * app de chamar `/api/admin`: lá vale só o RBAC do usuário. Um token de cliente
 * emitido para o usuário errado (foi o que a tomada de conta pelo cadastro do
 * app produzia) abria o painel inteiro com as permissões da vítima.
 *
 * Token do painel é curinga (`createToken('spa')` ⇒ `['*']`); cookie stateful
 * chega como TransientToken e passa. Token pessoal sem `*` é de app: 403.
 * Nenhum app chama `/api/admin` (conferido em app-entregador, app-gas-em-casa
 * e mobile-shared).
 */
class SomenteTokenDoPainel
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! in_array('*', (array) $token->abilities, true)) {
            return response()->json(['message' => 'Este acesso é exclusivo do painel administrativo.'], 403);
        }

        return $next($request);
    }
}
