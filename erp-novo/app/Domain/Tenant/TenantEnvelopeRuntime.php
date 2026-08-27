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

        // Compatibilidade transitória: o grupo vem da empresa ativa já
        // autorizada pela policy canônica, nunca de sessão, payload legado ou
        // escolha por maioria. Assim tabelas ainda group-scoped continuam
        // fechadas durante a conversão sem transformar grupo em tenant SaaS.
        $grupoId = DB::table('empresas')
            ->where('id', $envelope->activeEmpresaId)
            ->value('grupo_id');
        if (! is_numeric($grupoId) || (int) $grupoId <= 0) {
            throw new TenantAccessDeniedException('Empresa ativa aprovada sem grupo legado válido.');
        }

        DB::statement('SELECT set_config(?, ?, false), set_config(?, ?, false), set_config(?, ?, false)', [
            'app.empresa_id', (string) $envelope->activeEmpresaId,
            'app.grupo_id', (string) $grupoId,
            'app.empresas_visiveis', implode(',', $envelope->readableEmpresaIds),
        ]);
    }

    private function clearDatabaseContext(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT set_config('app.tenant_account_id', '', false), set_config('app.tenant_membership_id', '', false), set_config('app.empresa_id', '', false), set_config('app.grupo_id', '', false), set_config('app.empresas_visiveis', '', false)");
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
