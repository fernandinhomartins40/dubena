<?php

namespace Database\Factories\Support;

use App\Models\Empresa;
use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantLegacyGroupScope;
use App\Models\Saas\TenantMembership;
use App\Models\User;

/**
 * Monta a fronteira SaaS aprovada para as fixtures de teste.
 *
 * Com `SAAS_ENFORCE_TENANT_ENVELOPE=true` o resolver exige TenantCompany
 * APPROVED, membership ACTIVE e grant aprovado. As factories nasceram no modelo
 * legado (empresa + grupo e mais nada), entao 566 testes falhavam com "Empresa
 * sem vinculo de titularidade SaaS aprovado" — o resolver funcionando contra
 * fixtures incompletas.
 *
 * Aqui a fronteira e criada de proposito para o teste. Em producao ela so vem
 * do importador documental: nada disto e caminho de aplicacao.
 */
final class FronteiraTenant
{
    /**
     * Garante tenant + vinculo aprovado da empresa, e devolve a conta.
     *
     * Um tenant por GRUPO: espelha a rede real (a Dubena e um tenant com 11
     * empresas do mesmo grupo) e mantem o teste de multi-empresa util.
     */
    public static function paraEmpresa(Empresa $empresa): TenantAccount
    {
        $existente = TenantCompany::query()
            ->where('empresa_id', $empresa->id)
            ->where('status', TenantCompany::STATUS_APPROVED)
            ->first();
        if ($existente !== null) {
            return TenantAccount::findOrFail($existente->tenant_account_id);
        }

        $tenant = self::contaDoGrupo((int) $empresa->grupo_id);

        TenantCompany::create([
            'tenant_account_id' => $tenant->id,
            'empresa_id' => $empresa->id,
            'status' => TenantCompany::STATUS_APPROVED,
            'approved_at' => now(),
            'ownership_evidence_ref' => 'teste',
        ]);

        // O pre-cutover recusa vinculo APPROVED sem ownership aprovado.
        $empresa->forceFill(['ownership_status' => Empresa::OWNERSHIP_APPROVED])->save();

        return $tenant;
    }

    /**
     * Garante membership ativa do usuario e grant nas empresas indicadas.
     *
     * @param  list<int>|null  $empresaIds  default: a empresa padrao do usuario
     */
    public static function paraUsuario(User $user, ?array $empresaIds = null): TenantMembership
    {
        $empresaIds ??= array_filter([$user->empresa_id]);
        $empresas = Empresa::withoutGlobalScopes()->whereKey($empresaIds)->get();
        if ($empresas->isEmpty()) {
            throw new \LogicException('Fronteira de teste exige ao menos uma empresa para o usuario.');
        }

        $tenant = self::paraEmpresa($empresas->first());

        $membership = TenantMembership::firstOrCreate(
            ['tenant_account_id' => $tenant->id, 'user_id' => $user->id],
            [
                'status' => TenantMembership::STATUS_ACTIVE,
                'membership_role' => 'OWNER',
                'approved_at' => now(),
                'approval_evidence_ref' => 'teste',
            ],
        );

        foreach ($empresas as $empresa) {
            // Empresa de outro grupo tem outro tenant: conceder aqui furaria a
            // fronteira que o teste existe para exercitar.
            $company = TenantCompany::query()
                ->where('empresa_id', $empresa->id)
                ->where('tenant_account_id', $tenant->id)
                ->where('status', TenantCompany::STATUS_APPROVED)
                ->first();
            if ($company === null) {
                continue;
            }

            TenantCompanyGrant::firstOrCreate(
                ['tenant_membership_id' => $membership->id, 'empresa_id' => $empresa->id],
                [
                    'tenant_account_id' => $tenant->id,
                    'tenant_company_id' => $company->id,
                    'can_read' => true,
                    'can_operate' => true,
                    'approved_at' => now(),
                    'grant_evidence_ref' => 'teste',
                ],
            );
        }

        return $membership;
    }

    /**
     * Um tenant por grupo.
     *
     * A ponte `tenant_legacy_group_scopes` NAO e criada aqui: ela so importa
     * para a policy das configuracoes group-scoped, e cria-la por padrao
     * quebraria os testes do proprio importador documental, que contam quantas
     * pontes existem. Quem precisa dela chama `pontesDeGrupo()`.
     */
    private static function contaDoGrupo(int $grupoId): TenantAccount
    {
        $ponte = TenantLegacyGroupScope::query()
            ->where('grupo_id', $grupoId)
            ->where('status', TenantLegacyGroupScope::STATUS_APPROVED)
            ->first();
        if ($ponte !== null) {
            return TenantAccount::findOrFail($ponte->tenant_account_id);
        }

        $company = TenantCompany::query()
            ->join('empresas', 'empresas.id', '=', 'tenant_companies.empresa_id')
            ->where('empresas.grupo_id', $grupoId)
            ->where('tenant_companies.status', TenantCompany::STATUS_APPROVED)
            ->first(['tenant_companies.tenant_account_id']);
        if ($company !== null) {
            return TenantAccount::findOrFail($company->tenant_account_id);
        }

        return TenantAccount::create([
            'legal_name' => 'Tenant de teste do grupo '.$grupoId,
            'status' => TenantAccount::STATUS_ACTIVE,
            'classified_at' => now(),
            'classification_evidence_ref' => 'teste',
        ]);
    }

    /**
     * Sincroniza os grants com os vinculos legados de `empresa_user`.
     *
     * O `afterCreating` da factory nao alcanca isto: o vinculo multi-empresa e
     * criado DEPOIS do usuario. Testes de rede/filial que usam `empresas()->
     * attach()` chamam este metodo para que a fronteira acompanhe.
     */
    public static function sincronizarVinculosLegados(User $user): void
    {
        $ids = $user->empresas()->pluck('empresas.id')->all();
        $ids[] = $user->empresa_id;
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return;
        }

        foreach ($ids as $empresaId) {
            $empresa = Empresa::withoutGlobalScopes()->find($empresaId);
            if ($empresa !== null) {
                self::paraEmpresa($empresa);
            }
        }

        self::paraUsuario($user, $ids);
    }

    /** Ponte documental do grupo — só para quem exercita configuração group-scoped. */
    public static function ponteDeGrupo(Empresa $empresa): TenantLegacyGroupScope
    {
        $tenant = self::paraEmpresa($empresa);

        return TenantLegacyGroupScope::firstOrCreate(
            ['grupo_id' => $empresa->grupo_id],
            [
                'tenant_account_id' => $tenant->id,
                'status' => TenantLegacyGroupScope::STATUS_APPROVED,
                'approved_at' => now(),
                'evidence_ref' => 'teste',
            ],
        );
    }
}
