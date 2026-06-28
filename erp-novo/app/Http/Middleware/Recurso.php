<?php

namespace App\Http\Middleware;

use App\Domain\Saas\LicencaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de LICENÇA por rota (P2) — defense-in-depth de PRODUTO, paralelo ao
 * middleware `permissao:` (que é de ACESSO).
 *
 * Uso em rota: `->middleware('recurso:marketplace')`. Barra a requisição quando a
 * empresa ATIVA não tem o recurso habilitado (sem assinatura/plano que o libere),
 * antes de chegar ao controller. Responde 402 (Payment Required) — semântica de
 * "recurso não contratado", distinta do 403 de permissão (RBAC).
 *
 * Roda DEPOIS de auth:sanctum + tenant (precisa do tenant resolvido).
 */
class Recurso
{
    public function __construct(private LicencaService $licenca) {}

    public function handle(Request $request, Closure $next, string $chave): Response
    {
        abort_unless(
            $this->licenca->recursoHabilitado($chave),
            402,
            'Recurso não disponível no plano atual.',
        );

        return $next($request);
    }
}
