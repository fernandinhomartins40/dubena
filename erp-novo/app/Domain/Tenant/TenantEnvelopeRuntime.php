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

    /** Executa o bootstrap de fronteira para um usuário já autenticado. */
    public function withAuthenticatedUser(int $userId, callable $callback): mixed
    {
        if ($userId <= 0) {
            throw new TenantAccessDeniedException('Bootstrap do TenantEnvelope exige usuário autenticado válido.');
        }

        $this->applyAuthenticatedUser($userId);

        try {
            return $callback();
        } finally {
            $this->clearAuthenticatedUser();
        }
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

    private function applyAuthenticatedUser(int $userId): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('SELECT set_config(?, ?, false)', ['app.authenticated_user_id', (string) $userId]);
    }

    private function clearAuthenticatedUser(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT set_config('app.authenticated_user_id', '', false)");
    }
}
