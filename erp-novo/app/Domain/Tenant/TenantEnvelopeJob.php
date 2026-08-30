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

        $envelope = TenantEnvelope::fromPayload($this->tenantEnvelopePayload);
        $atual = $runtime->current();

        // Fila sincrona (`QUEUE_CONNECTION=sync`) executa o job DENTRO do
        // request, onde o envelope ja esta ativo — e `run()` recusa sobrepor,
        // de proposito, para nao vazar tenant entre jobs no mesmo worker.
        //
        // Reusar so vale se for o MESMO tenant: identico, o envelope aninhado
        // nao mudaria nada; diferente, sobrepor e exatamente o vazamento que a
        // guarda existe para impedir, entao deixamos `run()` recusar.
        if ($atual !== null && $atual->tenantAccountId === $envelope->tenantAccountId) {
            return $callback();
        }

        return $runtime->run($envelope, $callback);
    }
}
