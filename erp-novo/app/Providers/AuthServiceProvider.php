<?php

namespace App\Providers;

use App\Domain\Shared\PermissaoCatalogo;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Enforcement central de autorização (Fase A1 do PLANO_CONTROLE_ACESSO_HIERARQUIA).
 *
 * Antes da A1 a autorização vivia 100% em `temPermissao()` chamado manualmente em
 * cada controller — fácil esquecer, sem ponto único, sem auditabilidade. Aqui
 * criamos o PONTO ÚNICO: um Gate por chave do catálogo, mais o `before` do
 * bypass de suporte. Todo controller passa a autorizar via `Gate::authorize()`
 * (trait AutorizaPorPermissao) ou via o middleware `permissao:`/`can:`.
 *
 * IMPORTANTE — sem mudança funcional: o Gate apenas DELEGA a `temPermissao()`, a
 * mesma regra de sempre (papel→permissão na empresa ativa, ou papel global).
 * O `before` replica o bypass do suporte que já existia dentro de `temPermissao()`,
 * mas explicitado na camada de Gate para que TODO ability (inclusive futuros que
 * não passem por `temPermissao`) respeite o suporte.
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
        // delega à regra única `temPermissao($chave)`. Como o catálogo é a fonte
        // da verdade e o RbacContratoTest garante que toda chave usada existe
        // aqui, todo `Gate::authorize('modulo.acao')` tem definição.
        foreach (PermissaoCatalogo::chaves() as $chave) {
            Gate::define($chave, fn (User $user) => $user->temPermissao($chave));
        }
    }
}
