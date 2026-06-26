<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autorização por rota (Fase A1) — camada 1 do defense-in-depth.
 *
 * Uso em rota: `->middleware('permissao:produto.view')`. Barra a requisição na
 * borda quando a rota toda exige uma permissão grossa, antes de chegar ao
 * controller. Delega ao MESMO Gate central (AuthServiceProvider → temPermissao),
 * então é consistente com a checagem fina dos controllers (trait
 * AutorizaPorPermissao). 403 'Sem permissão.' uniforme.
 */
class Permissao
{
    public function handle(Request $request, Closure $next, string $chave): Response
    {
        abort_if(
            $request->user() === null || Gate::forUser($request->user())->denies($chave),
            403,
            'Sem permissão.',
        );

        return $next($request);
    }
}
