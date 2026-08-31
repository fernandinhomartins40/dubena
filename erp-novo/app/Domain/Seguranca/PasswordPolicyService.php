<?php

namespace App\Domain\Seguranca;

use App\Domain\Tenant\TenantContext;
use App\Models\PasswordPolicy;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Política de senha (A5) — traduz a configuração numa regra de validação
 * Laravel, usada na criação e no reset de senha.
 *
 * F2-07 — a política é declarada POR EMPRESA, mas a senha é do USUÁRIO.
 *
 * Essa diferença abria um buraco concreto: um gerente que atende as filiais A
 * (mínimo 12, com complexidade) e B (mínimo 8) trocava a senha com a filial B
 * ativa e passava com 8 caracteres — enfraquecendo a mesma credencial que abre
 * a filial A. A política mais rígida era contornada escolhendo por qual porta
 * entrar.
 *
 * A empresa continua podendo declarar a sua exigência: isso tem valor e não foi
 * removido. O que muda é a regra APLICADA a uma pessoa, que passa a ser a mais
 * rígida entre as empresas que ela alcança — uma credencial só é tão forte
 * quanto a porta mais exigente que ela abre.
 *
 * Sem política cadastrada, vale um padrão seguro (mín. 8). O default é um PISO:
 * empresa sem política não derruba a exigência de uma irmã que declarou uma.
 */
class PasswordPolicyService
{
    /** Piso aplicado quando nenhuma empresa alcançada declara política. */
    private const MIN_PADRAO = 8;

    public function __construct(private TenantContext $tenant) {}

    /** Regra de validação de senha para o usuário autenticado. */
    public function regra(): Password
    {
        $pol = $this->politicaAtiva();
        $regra = Password::min($pol['min_len']);

        if ($pol['exige_complexidade']) {
            $regra->letters()->mixedCase()->numbers();
        }

        return $regra;
    }

    /**
     * Política efetiva: a mais rígida entre as empresas alcançadas.
     *
     * @return array{min_len:int, exige_complexidade:bool, expira_dias:int}
     */
    public function politicaAtiva(): array
    {
        $empresas = $this->empresasAlcancadas();

        // `withoutTenant`: `PasswordPolicy` é escopada pela empresa ATIVA, e com
        // o escopo ligado a consulta enxergaria uma política só — exatamente a
        // da porta mais permissiva, que é o buraco que este serviço fecha.
        //
        // A leitura continua estreita: só os ids que o usuário comprovadamente
        // alcança, apurados logo abaixo. Não é uma janela para o banco inteiro.
        $politicas = PasswordPolicy::withoutTenant()
            ->whereIn('empresa_id', $empresas)
            ->get();

        return [
            // Mais rígido = maior mínimo, e nunca abaixo do piso padrão.
            'min_len' => (int) max(self::MIN_PADRAO, $politicas->max('min_len') ?? 0),

            // Uma única empresa exigindo complexidade basta: a senha precisa
            // servir para todas as portas, não para a mais permissiva.
            'exige_complexidade' => $politicas->contains(fn ($p) => (bool) $p->exige_complexidade),

            'expira_dias' => $this->expiracaoMaisCurta($politicas),
        ];
    }

    /**
     * Prazo de expiração mais curto entre os declarados.
     *
     * `0` significa "nunca expira", não "menor que tudo" — tratá-lo como número
     * faria uma empresa sem prazo cancelar a exigência de uma irmã que tem um.
     *
     * @param  Collection<int, PasswordPolicy>  $politicas
     */
    private function expiracaoMaisCurta(Collection $politicas): int
    {
        $prazos = $politicas->map(fn ($p) => (int) $p->expira_dias)->filter(fn (int $d) => $d > 0);

        return $prazos->isEmpty() ? 0 : (int) $prazos->min();
    }

    /**
     * Empresas cuja política alcança este usuário.
     *
     * Fora de uma sessão autenticada (console, reset por link), cai para a
     * empresa ativa do contexto: é o que existe, e é mais seguro do que não
     * aplicar política nenhuma.
     *
     * @return list<int>
     */
    private function empresasAlcancadas(): array
    {
        $user = Auth::user();

        if ($user instanceof User) {
            // `pluck` direto na relação: sem escopo de tenant resolvido, um
            // `with` filtrado devolveria menos empresas do que o usuário
            // realmente alcança — e menos empresas = política mais frouxa.
            $ids = $user->empresas()->pluck('empresas.id')->all();
            $ids[] = $user->empresa_id;

            $ids = array_values(array_unique(array_filter($ids)));
            if ($ids !== []) {
                return $ids;
            }
        }

        return array_values(array_filter([$this->tenant->empresaId()]));
    }
}
