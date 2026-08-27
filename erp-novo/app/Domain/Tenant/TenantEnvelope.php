<?php

namespace App\Domain\Tenant;

use InvalidArgumentException;

/**
 * Contexto SaaS imutavel e serializavel. Nao consulta sessao, grupo ou modelo
 * global; a fronteira precisa existir por inteiro na entrada de cada processo.
 */
final class TenantEnvelope
{
    /** @var list<int> */
    public readonly array $readableEmpresaIds;

    /** @var list<int> */
    public readonly array $operableEmpresaIds;

    /**
     * @param list<int> $readableEmpresaIds
     * @param list<int> $operableEmpresaIds
     */
    public function __construct(
        public readonly int $tenantAccountId,
        public readonly int $tenantMembershipId,
        public readonly int $activeEmpresaId,
        array $readableEmpresaIds,
        array $operableEmpresaIds,
        public readonly string $correlationId,
    ) {
        if ($tenantAccountId <= 0 || $tenantMembershipId <= 0 || $activeEmpresaId <= 0 || trim($correlationId) === '') {
            throw new InvalidArgumentException('TenantEnvelope exige ids positivos e correlation id.');
        }

        $this->readableEmpresaIds = $this->normalize($readableEmpresaIds);
        $this->operableEmpresaIds = $this->normalize($operableEmpresaIds);
        if (! in_array($activeEmpresaId, $this->operableEmpresaIds, true)) {
            throw new InvalidArgumentException('A empresa ativa deve ter grant operacional explicito.');
        }
        if (array_diff($this->operableEmpresaIds, $this->readableEmpresaIds) !== []) {
            throw new InvalidArgumentException('Grant operacional sem grant de leitura e invalido.');
        }
    }

    public function canRead(int $empresaId): bool
    {
        return in_array($empresaId, $this->readableEmpresaIds, true);
    }

    public function canOperate(int $empresaId): bool
    {
        return in_array($empresaId, $this->operableEmpresaIds, true);
    }

    public function requireRead(int $empresaId): void
    {
        if (! $this->canRead($empresaId)) {
            throw new TenantAccessDeniedException('Leitura negada fora do grant tenant-empresa.');
        }
    }

    public function requireOperation(int $empresaId): void
    {
        if (! $this->canOperate($empresaId)) {
            throw new TenantAccessDeniedException('Operacao negada fora do grant tenant-empresa.');
        }
    }

    /** @return array<string, int|list<int>|string> */
    public function toPayload(): array
    {
        return [
            'tenant_account_id' => $this->tenantAccountId,
            'tenant_membership_id' => $this->tenantMembershipId,
            'active_empresa_id' => $this->activeEmpresaId,
            'readable_empresa_ids' => $this->readableEmpresaIds,
            'operable_empresa_ids' => $this->operableEmpresaIds,
            'correlation_id' => $this->correlationId,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            (int) ($payload['tenant_account_id'] ?? 0),
            (int) ($payload['tenant_membership_id'] ?? 0),
            (int) ($payload['active_empresa_id'] ?? 0),
            is_array($payload['readable_empresa_ids'] ?? null) ? $payload['readable_empresa_ids'] : [],
            is_array($payload['operable_empresa_ids'] ?? null) ? $payload['operable_empresa_ids'] : [],
            (string) ($payload['correlation_id'] ?? ''),
        );
    }

    /** @param list<int> $ids @return list<int> */
    private function normalize(array $ids): array
    {
        $normalized = array_values(array_unique(array_map('intval', $ids)));
        $normalized = array_values(array_filter($normalized, fn (int $id) => $id > 0));
        sort($normalized);

        if ($normalized === []) {
            throw new InvalidArgumentException('TenantEnvelope exige grants explicitos.');
        }

        return $normalized;
    }
}
