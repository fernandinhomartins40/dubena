<?php

namespace App\Domain\Tenant;

use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantMembership;
use App\Models\User;

/** Resolve somente a fronteira SaaS aprovada; nao adapta grupo/legado. */
final class TenantEnvelopeResolver
{
    public function resolveFor(User $user, int $activeEmpresaId, string $correlationId): TenantEnvelope
    {
        $company = TenantCompany::query()
            ->where('empresa_id', $activeEmpresaId)
            ->where('status', TenantCompany::STATUS_APPROVED)
            ->first();
        if ($company === null) {
            throw new TenantAccessDeniedException('Empresa sem vinculo de titularidade SaaS aprovado.');
        }

        $membership = TenantMembership::query()
            ->where('tenant_account_id', $company->tenant_account_id)
            ->where('user_id', $user->id)
            ->where('status', TenantMembership::STATUS_ACTIVE)
            ->first();
        if ($membership === null) {
            throw new TenantAccessDeniedException('Usuario sem membership SaaS ativa.');
        }

        $grants = TenantCompanyGrant::query()
            ->where('tenant_membership_id', $membership->id)
            ->where('tenant_account_id', $company->tenant_account_id)
            ->where('tenant_company_id', $company->id)
            ->whereNotNull('approved_at')
            ->get(['empresa_id', 'can_read', 'can_operate']);

        $readable = $grants->where('can_read', true)->pluck('empresa_id')->map(fn ($id) => (int) $id)->all();
        $operable = $grants->where('can_operate', true)->pluck('empresa_id')->map(fn ($id) => (int) $id)->all();

        return new TenantEnvelope(
            $company->tenant_account_id,
            $membership->id,
            $activeEmpresaId,
            $readable,
            $operable,
            $correlationId,
        );
    }
}
