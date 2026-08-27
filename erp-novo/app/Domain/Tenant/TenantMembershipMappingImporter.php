<?php

namespace App\Domain\Tenant;

use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Aplica memberships/grants explicitos sem inferir regras no kernel SaaS. */
final class TenantMembershipMappingImporter
{
    /** @param array<string,mixed> $plan @return array{memberships:int,grants:int} */
    public function preview(array $plan): array
    {
        $tenant = TenantAccount::query()->where('document', $plan['tenant_document'] ?? null)->first();
        if ($tenant === null || ! is_array($plan['memberships'] ?? null)) {
            throw new InvalidArgumentException('Mapping de memberships exige tenant_document existente e memberships.');
        }

        $users = [];
        $summary = ['memberships' => 0, 'grants' => 0];
        foreach ($plan['memberships'] as $membership) {
            $userId = (int) ($membership['user_id'] ?? 0);
            $role = (string) ($membership['membership_role'] ?? 'MEMBER');
            if ($userId <= 0 || isset($users[$userId]) || User::query()->whereKey($userId)->where('ativo', true)->doesntExist() || ! in_array($role, ['MEMBER', 'OWNER'], true)) {
                throw new InvalidArgumentException("Membership invalida para usuario {$userId}.");
            }
            $users[$userId] = true;
            $summary['memberships']++;

            foreach (($membership['grants'] ?? []) as $grant) {
                $empresaId = (int) ($grant['empresa_id'] ?? 0);
                $company = TenantCompany::query()->where('tenant_account_id', $tenant->id)->where('empresa_id', $empresaId)->where('status', TenantCompany::STATUS_APPROVED)->first();
                if ($company === null || ! array_key_exists('can_read', $grant) || ! array_key_exists('can_operate', $grant) || ((bool) $grant['can_operate'] && ! (bool) $grant['can_read'])) {
                    throw new InvalidArgumentException("Grant invalido para empresa {$empresaId}.");
                }
                $summary['grants']++;
            }
        }

        return $summary;
    }

    /** @param array<string,mixed> $plan @return array{memberships:int,grants:int} */
    public function apply(array $plan): array
    {
        $summary = $this->preview($plan);

        DB::transaction(function () use ($plan): void {
            $tenant = TenantAccount::query()->where('document', $plan['tenant_document'])->firstOrFail();
            foreach ($plan['memberships'] as $membershipData) {
                $membership = TenantMembership::query()->firstOrCreate(
                    ['tenant_account_id' => $tenant->id, 'user_id' => $membershipData['user_id']],
                    ['status' => TenantMembership::STATUS_ACTIVE, 'membership_role' => $membershipData['membership_role'] ?? 'MEMBER', 'approved_at' => now(), 'approval_evidence_ref' => $membershipData['approval_evidence_ref'] ?? 'migration-mapping'],
                );
                foreach ($membershipData['grants'] as $grantData) {
                    $company = TenantCompany::query()->where('tenant_account_id', $tenant->id)->where('empresa_id', $grantData['empresa_id'])->where('status', TenantCompany::STATUS_APPROVED)->firstOrFail();
                    TenantCompanyGrant::query()->updateOrCreate(
                        ['tenant_membership_id' => $membership->id, 'tenant_company_id' => $company->id],
                        ['tenant_account_id' => $tenant->id, 'empresa_id' => $company->empresa_id, 'can_read' => $grantData['can_read'], 'can_operate' => $grantData['can_operate'], 'approved_at' => now(), 'grant_evidence_ref' => $grantData['grant_evidence_ref'] ?? 'migration-mapping'],
                    );
                }
            }
        });

        return $summary;
    }
}
