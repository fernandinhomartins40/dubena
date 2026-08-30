<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantLegacyGroupScope;
use App\Models\Saas\TenantMembership;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Empresa> */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        return [
            'grupo_id' => Grupo::factory(),
            'razao_social' => fake()->company(),
            'nome_fantasia' => fake()->company(),
            'nome_informal' => fake()->company(),
            'cnpj' => fake()->numerify('##############'),
            'uf' => 'SP',
            'ativo' => true,
        ];
    }

    /**
     * Toda empresa de teste nasce com titularidade aprovada.
     *
     * Com o enforcement SaaS ligado o resolver exige `TenantCompany` APPROVED;
     * sem isto as fixtures legadas derrubam a suite inteira. A fronteira e
     * criada aqui de proposito — em producao ela so vem do importador
     * documental.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Empresa $empresa): void {
            FronteiraTenant::paraEmpresa($empresa);
        });
    }

    /**
     * Empresa deliberadamente FORA da fronteira, para exercitar a negacao.
     *
     * Remove tambem a `TenantAccount` que ficou orfa: os testes de fronteira
     * contam essas linhas para provar que nada e inferido de empresa/grupo
     * legado, e uma conta sobrando quebraria justamente essa asserção.
     */
    public function semFronteiraSaas(): static
    {
        return $this->afterCreating(function (Empresa $empresa): void {
            $contas = TenantCompany::query()->where('empresa_id', $empresa->id)->pluck('tenant_account_id');
            TenantCompany::query()->where('empresa_id', $empresa->id)->delete();

            foreach ($contas as $contaId) {
                if (! TenantCompany::query()->where('tenant_account_id', $contaId)->exists()) {
                    TenantLegacyGroupScope::query()->where('tenant_account_id', $contaId)->delete();
                    TenantMembership::query()->where('tenant_account_id', $contaId)->delete();
                    TenantAccount::query()->whereKey($contaId)->delete();
                }
            }

            $empresa->forceFill(['ownership_status' => Empresa::OWNERSHIP_UNRESOLVED])->save();
        });
    }
}
