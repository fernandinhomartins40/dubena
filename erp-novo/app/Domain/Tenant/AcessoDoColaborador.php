<?php

namespace App\Domain\Tenant;

use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantMembership;
use App\Models\User;

/**
 * PIE P2-D-02 — usuário criado pelo painel entra na fronteira SaaS.
 *
 * Com o envelope ligado, toda requisição exige membership + grant aprovados. O
 * cadastro de usuário criava só o `User` e os papéis: o funcionário novo logava
 * e recebia 403 "Empresa sem vinculo de titularidade SaaS aprovado." em TUDO.
 * A revenda não conseguia dar acesso ao próprio atendente sem rodar
 * `saas:tenant:importar-memberships` com um JSON.
 *
 * O runtime não grava grant (`WITH CHECK (false)` é de propósito): a concessão
 * vai pelo owner. O escopo é o mais estreito que resolve o problema e não amplia
 * o que o ator já tem:
 *  - só no tenant do envelope do ator;
 *  - só na empresa ATIVA, que o envelope já provou que o ator opera;
 *  - papel `MEMBER`; quem pode mais é decidido pelo RBAC da revenda, não aqui.
 *
 * A evidência registra quem concedeu — a mesma trilha que o importador exige.
 */
final class AcessoDoColaborador
{
    public function __construct(private TenantEnvelopeRuntime $runtime) {}

    public function concederNaEmpresaAtiva(User $novo, User $ator): void
    {
        $envelope = $this->runtime->current();
        if ($envelope === null) {
            // Sem envelope (sqlite/CI, instalação sem SaaS ligado) não há
            // fronteira a satisfazer.
            return;
        }

        $empresaId = $envelope->activeEmpresaId;
        $tenantId = $envelope->tenantAccountId;
        $evidencia = sprintf('painel:usuario.create:ator=%d:em=%s', $ator->id, now()->toIso8601String());

        ConexaoDeOwner::executar(function () use ($novo, $empresaId, $tenantId, $evidencia) {
            $company = TenantCompany::query()
                ->where('tenant_account_id', $tenantId)
                ->where('empresa_id', $empresaId)
                ->where('status', TenantCompany::STATUS_APPROVED)
                ->first();
            if ($company === null) {
                throw new TenantAccessDeniedException('Empresa ativa sem titularidade SaaS aprovada.');
            }

            $membership = TenantMembership::query()->firstOrCreate(
                ['tenant_account_id' => $tenantId, 'user_id' => $novo->id],
                [
                    'status' => TenantMembership::STATUS_ACTIVE,
                    'membership_role' => 'MEMBER',
                    'approved_at' => now(),
                    'approval_evidence_ref' => $evidencia,
                ],
            );

            TenantCompanyGrant::query()->firstOrCreate(
                ['tenant_membership_id' => $membership->id, 'empresa_id' => $empresaId],
                [
                    'tenant_account_id' => $tenantId,
                    'tenant_company_id' => $company->id,
                    'can_read' => true,
                    'can_operate' => true,
                    'approved_at' => now(),
                    'grant_evidence_ref' => $evidencia,
                ],
            );
        });
    }
}
