<?php

namespace App\Api\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * FASE 5: middleware `access` portado de api-app-gc para o módulo Api do ERP.
 * Registra o acesso às rotas da API do app (observabilidade). NÃO faz
 * autorização por si só — a proteção é via `auth:api` (Passport).
 */
class Access
{
    public function handle($request, Closure $next)
    {
        Log::info('[api-mobile] ' . $request->method() . ' ' . $request->path());
        return $next($request);
    }
}
