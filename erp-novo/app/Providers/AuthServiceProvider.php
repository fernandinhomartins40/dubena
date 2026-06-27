<?php

namespace App\Providers;

use App\Domain\Acesso\PolicyEvaluator;
use App\Domain\Shared\PermissaoCatalogo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Enforcement central de autorização (Fases A1 + A4 do PLANO_CONTROLE_ACESSO).
 *
 * A1 criou o PONTO ÚNICO: um Gate por chave do catálogo + o `before` do bypass de
 * suporte. Todo controller autoriza via a trait AutorizaPorPermissao ou o
 * middleware `permissao:`.
 *
 * A4 (ABAC) torna o Gate ciente de RECURSO: quando a checagem recebe um recurso
 * (`Gate::allows('pedido.aprovar', $pedido)`), a decisão passa pelo PolicyEvaluator
 * (RBAC + escopo hierárquico + condições de atributo). SEM recurso, o Gate
 * continua sendo RBAC puro (`temPermissao`) — totalmente compatível com a A1.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Bypass do suporte: support = "pode tudo" (regra herdada do legado).
        // Devolver `true` curto-circuita TODA verificação de Gate antes de avaliar
        // a ability específica; devolver `null` deixa seguir para o Gate definido.
        Gate::before(function (User $user) {
            return $user->support ? true : null;
        });

        // Um Gate por chave do catálogo (a fonte da verdade do RBAC). Cada Gate
        // delega ao PolicyEvaluator: sem recurso = RBAC puro (temPermissao); com
        // recurso = RBAC + escopo (A3) + condições ABAC (A4). O RbacContratoTest
        // garante que toda chave usada existe aqui.
        foreach (PermissaoCatalogo::chaves() as $chave) {
            Gate::define(
                $chave,
                fn (User $user, array|Model|null $recurso = null) => $this->evaluator()->permite($user, $chave, $recurso),
            );
        }
    }

    /** Resolve o avaliador no container (scoped — usa o TenantContext da request). */
    private function evaluator(): PolicyEvaluator
    {
        return app(PolicyEvaluator::class);
    }
}
