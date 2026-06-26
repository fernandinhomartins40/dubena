<?php

namespace App\Http\Middleware;

use App\Domain\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function __construct(private TenantContext $tenant) {}

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
                $this->setRlsTenant($empresaId, $grupoId);
            }
        }

        return $next($request);
    }

    /**
     * Define `app.empresa_id` e `app.grupo_id` na sessão do Postgres para alimentar
     * as policies de RLS. É a 2ª barreira: mesmo uma query crua só vê linhas do
     * tenant ativo — empresa (tabelas operacionais) e grupo (cadastros de apoio
     * compartilhados). NO-OP fora do pgsql. set_config(..., false) = escopo de sessão.
     */
    private function setRlsTenant(int $empresaId, int $grupoId): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('SELECT set_config(?, ?, false), set_config(?, ?, false)', [
            'app.empresa_id', (string) $empresaId,
            'app.grupo_id', (string) $grupoId,
        ]);
    }
}
