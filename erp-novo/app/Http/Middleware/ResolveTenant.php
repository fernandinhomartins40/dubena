<?php

namespace App\Http\Middleware;

use App\Domain\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o tenant (empresa + grupo) da requisição a partir do usuário autenticado.
 *
 * Substitui o EmpresaController::change() do legado, que recarregava
 * Session('empresa_padrao'). Aqui:
 *  - empresa ativa padrão = empresa_id do usuário;
 *  - troca de empresa via header `X-Empresa-Id` (validada contra as empresas
 *    permitidas do usuário) — equivalente moderno e stateless do "trocar empresa".
 *
 * Deve rodar DEPOIS do middleware de autenticação (auth:sanctum).
 */
class ResolveTenant
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $empresaId = (int) $user->empresa_id;
            $grupoId = (int) $user->grupo_id;

            // Troca de empresa ativa (se solicitada e permitida ao usuário).
            $solicitada = $request->header('X-Empresa-Id');
            if ($solicitada !== null && method_exists($user, 'podeAcessarEmpresa')) {
                $solicitada = (int) $solicitada;
                if ($solicitada > 0 && $user->podeAcessarEmpresa($solicitada)) {
                    $empresaId = $solicitada;
                    $grupoId = (int) $user->grupoIdDaEmpresa($solicitada);
                }
            }

            if ($empresaId > 0 && $grupoId > 0) {
                $this->tenant->set($empresaId, $grupoId);
            }
        }

        return $next($request);
    }
}
