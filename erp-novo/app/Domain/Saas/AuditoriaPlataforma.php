<?php

namespace App\Domain\Saas;

use App\Domain\Auditoria\ContextoAuditoria;
use App\Models\Saas\PlatformAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * AuditoriaPlataforma (P4) — trilha append-only de TODA ação cross-tenant do
 * SuperAdmin (platform_audit_logs). É a prestação de contas do único acesso que
 * cruza empresas: nada atravessa o sigilo entre tenants sem ficar registrado aqui
 * (quem/quando/qual tenant/antes-depois/IP).
 */
class AuditoriaPlataforma
{
    /**
     * @param  array<string,mixed>|null  $antes
     * @param  array<string,mixed>|null  $depois
     */
    public function registrar(
        string $acao,
        ?int $empresaId = null,
        ?string $entidade = null,
        ?int $entidadeId = null,
        ?array $antes = null,
        ?array $depois = null,
        // F2-06 criou a coluna; sem o parâmetro ela ficaria sempre nula — e
        // intervenção de plataforma é justamente onde o porquê mais importa.
        ?string $motivo = null,
    ): void {
        DB::table('platform_audit_logs')->insert([
            // F2-06: correlacao liga esta linha a acao HTTP que a originou.
            // `tenant_account_id` nao vem do envelope aqui: o SuperAdmin opera
            // SEM tenant resolvido, por desenho, entao ele e derivado da empresa
            // alvo — que e o que identifica de quem e o dado tocado.
            ...app(ContextoAuditoria::class)->camposDePlataforma($empresaId),
            'platform_admin_id' => $this->adminId(),
            'acao' => $acao,
            'empresa_id' => $empresaId,
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'antes' => $antes !== null ? json_encode($antes) : null,
            'depois' => $depois !== null ? json_encode($depois) : null,
            'motivo' => $motivo,
            'ip' => $this->ip(),
            'criado_em' => now(),
        ]);
    }

    private function adminId(): ?int
    {
        $admin = auth('platform')->user();

        return $admin instanceof PlatformAdmin ? $admin->id : null;
    }

    private function ip(): ?string
    {
        try {
            return Request::ip();
        } catch (\Throwable) {
            return null;
        }
    }
}
