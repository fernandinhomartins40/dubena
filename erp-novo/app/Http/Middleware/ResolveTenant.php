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

            // Troca da empresa ATIVA (contexto): config, caixa, numeração fiscal.
            $solicitada = $request->header('X-Empresa-Id');
            if ($solicitada !== null && method_exists($user, 'podeAcessarEmpresa')) {
                $solicitada = (int) $solicitada;
                if ($solicitada > 0 && $user->podeAcessarEmpresa($solicitada)) {
                    $empresaId = $solicitada;
                    $grupoId = (int) $user->grupoIdDaEmpresa($solicitada);
                }
            }

            if ($empresaId > 0 && $grupoId > 0) {
                // As LISTAGENS mostram todas as empresas do usuário na rede —
                // não só a ativa. É o comportamento do ctrl-web
                // (`empresas_permitidas` + `whereIn`), e o que o dono de uma
                // rede espera: ver a operação inteira ao abrir uma tela.
                $visiveis = method_exists($user, 'empresasVisiveis')
                    ? $user->empresasVisiveis($grupoId)
                    : [$empresaId];

                $this->tenant->set($empresaId, $grupoId, $visiveis);

                // Filtro OPCIONAL por empresa (o combo da tela). Equivale ao
                // `if ($empresa_id != 0)` do legado: refina a visão, nunca
                // amplia — uma empresa fora do conjunto é ignorada.
                $this->tenant->filtrarPorEmpresa($this->empresaDoFiltro($request));

                $this->setRlsTenant($empresaId, $grupoId, $this->tenant->empresasVisiveis());
            }
        }

        return $next($request);
    }

    /**
     * Limpa as variáveis de RLS ao FIM da requisição. As GUCs são de sessão
     * (persistem na conexão), então numa conexão reutilizada (pooling, ou a conexão
     * única dos testes) o tenant de uma requisição vazaria para a próxima operação
     * na mesma conexão. Zerar aqui garante que, fora de uma requisição resolvida,
     * a RLS negue acesso em vez de aplicar um tenant obsoleto. CLI/ETL de
     * plataforma usam uma conexão privilegiada explícita; runtime é fail-closed.
     * NO-OP fora do pgsql.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->limparRlsTenant();
    }

    /**
     * Empresa pedida no filtro da tela (`?empresa_id=`), se houver.
     *
     * Não confundir com `X-Empresa-Id`: aquele troca o CONTEXTO (config, caixa,
     * numeração fiscal); este só refina o que a listagem mostra.
     */
    private function empresaDoFiltro(Request $request): ?int
    {
        $valor = $request->query('empresa_id');

        return is_numeric($valor) ? (int) $valor : null;
    }

    /**
     * Define as GUCs que alimentam as policies de RLS. É a 2ª barreira: mesmo
     * uma query crua só vê linhas permitidas.
     *
     * `app.empresa_id` = a empresa ATIVA (contexto).
     * `app.empresas_visiveis` = a lista que as listagens podem ver, em CSV.
     *
     * As duas precisam andar juntas: se a policy continuasse comparando só com
     * `app.empresa_id`, o banco barraria exatamente o que a aplicação passou a
     * liberar — as filiais sumiriam de novo, agora sem erro visível.
     *
     * NO-OP fora do pgsql. set_config(..., false) = escopo de sessão.
     *
     * @param  list<int>  $visiveis
     */
    private function setRlsTenant(int $empresaId, int $grupoId, array $visiveis = []): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $lista = implode(',', $visiveis !== [] ? $visiveis : [$empresaId]);

        DB::statement(
            'SELECT set_config(?, ?, false), set_config(?, ?, false), set_config(?, ?, false)',
            [
                'app.empresa_id', (string) $empresaId,
                'app.grupo_id', (string) $grupoId,
                'app.empresas_visiveis', $lista,
            ]
        );
    }

    /** Zera as GUCs de tenant (fim de requisição). NO-OP fora do pgsql. */
    private function limparRlsTenant(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT set_config('app.empresa_id', '', false), "
            ."set_config('app.grupo_id', '', false), "
            ."set_config('app.empresas_visiveis', '', false)"
        );
    }
}
