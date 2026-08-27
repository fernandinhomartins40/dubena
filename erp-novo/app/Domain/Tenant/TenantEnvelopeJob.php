<?php

namespace App\Domain\Tenant;

/** Trait para jobs de negocio: payload obrigatorio e limpeza garantida. */
trait TenantEnvelopeJob
{
    /** @var array<string, mixed>|null */
    public ?array $tenantEnvelopePayload = null;

    protected function captureTenantEnvelope(TenantEnvelope $envelope): void
    {
        $this->tenantEnvelopePayload = $envelope->toPayload();
    }

    /** @template T @param callable(): T $callback @return T */
    protected function withinTenantEnvelope(TenantEnvelopeRuntime $runtime, callable $callback): mixed
    {
        if ($this->tenantEnvelopePayload === null) {
            throw new TenantAccessDeniedException('Job de negocio sem TenantEnvelope serializado.');
        }

        return $runtime->run(TenantEnvelope::fromPayload($this->tenantEnvelopePayload), $callback);
    }
}
