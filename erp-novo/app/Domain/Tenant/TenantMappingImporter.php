<?php

namespace App\Domain\Tenant;

use App\Models\Empresa;
use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** F1-10: aplica somente mapeamento documental explícito. */
final class TenantMappingImporter
{
    /** @param array<string, mixed> $plan @return array{tenants:int,companies:int,memberships:int,grants:int} */
    public function preview(array $plan): array
    {
        $tenants = $plan['tenants'] ?? null;
        if (! is_array($tenants) || $tenants === []) {
            throw new InvalidArgumentException('O mapeamento exige ao menos um tenant explícito.');
        }

        $summary = ['tenants' => 0, 'companies' => 0, 'memberships' => 0, 'grants' => 0];
        $mappedCompanies = [];
        foreach ($tenants as $tenant) {
            if (! is_array($tenant) || trim((string) ($tenant['legal_name'] ?? '')) === '' || trim((string) ($tenant['classification_evidence_ref'] ?? '')) === '') {
                throw new InvalidArgumentException('Tenant sem nome jurídico ou evidência de classificação.');
            }
            $summary['tenants']++;
            $companies = $tenant['companies'] ?? [];
            $tenantCompanyIds = [];
            if (! is_array($companies) || $companies === []) {
                throw new InvalidArgumentException('Tenant sem empresas aprovadas.');
            }
            foreach ($companies as $company) {
                $empresaId = (int) ($company['empresa_id'] ?? 0);
                if ($empresaId <= 0 || trim((string) ($company['ownership_evidence_ref'] ?? '')) === '') {
                    throw new InvalidArgumentException('Empresa sem id ou evidência de titularidade.');
                }
                if (isset($mappedCompanies[$empresaId]) || Empresa::find($empresaId) === null || TenantCompany::where('empresa_id', $empresaId)->exists()) {
                    throw new InvalidArgumentException("Empresa {$empresaId} ausente, duplicada ou já vinculada.");
                }
                $mappedCompanies[$empresaId] = true;
                $tenantCompanyIds[$empresaId] = true;
                $summary['companies']++;
            }
            foreach (($tenant['memberships'] ?? []) as $membership) {
                $userId = (int) ($membership['user_id'] ?? 0);
                if ($userId <= 0 || User::find($userId) === null || trim((string) ($membership['approval_evidence_ref'] ?? '')) === '') {
                    throw new InvalidArgumentException('Membership sem usuário ou evidência.');
                }
                $summary['memberships']++;
                foreach (($membership['grants'] ?? []) as $grant) {
                    $empresaId = (int) ($grant['empresa_id'] ?? 0);
                    if (! isset($tenantCompanyIds[$empresaId]) || ! array_key_exists('can_read', $grant) || ! array_key_exists('can_operate', $grant) || trim((string) ($grant['grant_evidence_ref'] ?? '')) === '') {
                        throw new InvalidArgumentException('Grant sem empresa do plano, flags ou evidência.');
                    }
                    if ((bool) $grant['can_operate'] && ! (bool) $grant['can_read']) {
                        throw new InvalidArgumentException('Grant operacional sem leitura explícita.');
                    }
                    $summary['grants']++;
                }
            }
        }

        return $summary;
    }

    /** @param array<string, mixed> $plan @return array{tenants:int,companies:int,memberships:int,grants:int} */
    public function apply(array $plan): array
    {
        $summary = $this->preview($plan);

        DB::transaction(function () use ($plan): void {
            foreach ($plan['tenants'] as $tenantData) {
                $tenant = TenantAccount::create([
                    'legal_name' => $tenantData['legal_name'],
                    'document' => $tenantData['document'] ?? null,
                    'status' => TenantAccount::STATUS_ACTIVE,
                    'classified_at' => now(),
                    'classification_evidence_ref' => $tenantData['classification_evidence_ref'],
                ]);
                $companyLinks = [];
                foreach ($tenantData['companies'] as $companyData) {
                    $empresa = Empresa::findOrFail($companyData['empresa_id']);
                    $companyLinks[$empresa->id] = TenantCompany::create([
                        'tenant_account_id' => $tenant->id,
                        'empresa_id' => $empresa->id,
                        'status' => TenantCompany::STATUS_APPROVED,
                        'approved_at' => now(),
                        'ownership_evidence_ref' => $companyData['ownership_evidence_ref'],
                    ]);
                    $empresa->forceFill(['ownership_status' => Empresa::OWNERSHIP_APPROVED])->save();
                }
                foreach (($tenantData['memberships'] ?? []) as $membershipData) {
                    $membership = TenantMembership::create([
                        'tenant_account_id' => $tenant->id,
                        'user_id' => $membershipData['user_id'],
                        'status' => TenantMembership::STATUS_ACTIVE,
                        'membership_role' => $membershipData['membership_role'] ?? 'MEMBER',
                        'approved_at' => now(),
                        'approval_evidence_ref' => $membershipData['approval_evidence_ref'],
                    ]);
                    foreach (($membershipData['grants'] ?? []) as $grantData) {
                        $company = $companyLinks[(int) $grantData['empresa_id']];
                        TenantCompanyGrant::create([
                            'tenant_membership_id' => $membership->id,
                            'tenant_account_id' => $tenant->id,
                            'tenant_company_id' => $company->id,
                            'empresa_id' => $company->empresa_id,
                            'can_read' => $grantData['can_read'],
                            'can_operate' => $grantData['can_operate'],
                            'approved_at' => now(),
                            'grant_evidence_ref' => $grantData['grant_evidence_ref'],
                        ]);
                    }
                }
            }
        });

        return $summary;
    }
}
