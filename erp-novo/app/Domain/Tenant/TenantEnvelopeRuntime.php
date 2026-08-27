<?php

namespace App\Domain\Tenant;

use Illuminate\Support\Facades\DB;

/** Ciclo único de envelope para HTTP, jobs, eventos e WebSockets. */
final class TenantEnvelopeRuntime
{
    private ?TenantEnvelope $envelope = null;

    public function current(): ?TenantEnvelope
    {
        return $this->envelope;
    }

    /** @template T @param callable(): T $callback @return T */
    public function run(TenantEnvelope $envelope, callable $callback): mixed
    {
        if ($this->envelope !== null) {
            throw new TenantAccessDeniedException('Nao e permitido sobrepor TenantEnvelope no mesmo worker.');
        }

        $this->envelope = $envelope;
        $this->applyDatabaseContext($envelope);

        try {
            return $callback();
        } finally {
            $this->clear();
        }
    }

    public function clear(): void
    {
        $this->clearDatabaseContext();
        $this->envelope = null;
    }

    private function applyDatabaseContext(TenantEnvelope $envelope): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('SELECT set_config(?, ?, false), set_config(?, ?, false)', [
            'app.tenant_account_id', (string) $envelope->tenantAccountId,
            'app.tenant_membership_id', (string) $envelope->tenantMembershipId,
        ]);
    }

    private function clearDatabaseContext(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT set_config('app.tenant_account_id', '', false), set_config('app.tenant_membership_id', '', false)");
    }
}
