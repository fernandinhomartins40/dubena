<?php

namespace App\Domain\Seguranca;

use App\Domain\Tenant\TenantContext;
use App\Models\PasswordPolicy;
use Illuminate\Validation\Rules\Password;

/**
 * Política de senha por empresa (A5). Traduz a config da empresa ativa numa
 * regra de validação Laravel (Password) usada em criação/reset de senha. Sem
 * política cadastrada, aplica um padrão seguro (mín. 8).
 */
class PasswordPolicyService
{
    public function __construct(private TenantContext $tenant) {}

    /** Regra de validação de senha para a empresa ativa. */
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
     * Política efetiva (a da empresa ativa, ou o padrão).
     *
     * @return array{min_len:int, exige_complexidade:bool, expira_dias:int}
     */
    public function politicaAtiva(): array
    {
        $empresaId = $this->tenant->empresaId();
        $pol = $empresaId ? PasswordPolicy::query()->find($empresaId) : null;

        return [
            'min_len' => $pol->min_len ?? 8,
            'exige_complexidade' => (bool) ($pol->exige_complexidade ?? false),
            'expira_dias' => $pol->expira_dias ?? 0,
        ];
    }
}
