<?php

namespace App\Domain\Tenant;

use Illuminate\Support\Facades\DB;

/**
 * Trait para JOBS que precisam operar dentro de um tenant (empresa/grupo).
 *
 * PROBLEMA: jobs rodam FORA do ciclo HTTP, então o middleware ResolveTenant nunca
 * executa — o TenantContext nasce vazio e o `set_config('app.empresa_id', …)` da
 * RLS não é setado. Resultado: a 1ª barreira (global scope BelongsToTenant) não
 * filtra e a 2ª (RLS no Postgres) não restringe. Um job que mira a empresa X pode
 * ler/gravar dados de outra empresa, ou nascer com empresa_id errado.
 *
 * SOLUÇÃO: o job CAPTURA o tenant no momento do dispatch (no request, onde o
 * contexto existe) e o RE-APLICA no início do handle(): popula o TenantContext
 * (1ª barreira) e seta as variáveis de sessão do Postgres (2ª barreira). Assim o
 * job enxerga exatamente o mesmo recorte que a requisição que o originou.
 *
 * USO:
 *   class EnviarPushJob implements ShouldQueue {
 *       use TenantAwareJob;
 *       public function __construct(...) { $this->capturarTenant(); }
 *       public function handle(): void { $this->aplicarTenant(); ... }
 *   }
 *
 * Sem tenant ativo no dispatch (ex.: cron global/seed), capturarTenant() guarda
 * null e aplicarTenant() é NO-OP — espelhando o comportamento "sem tenant não
 * filtra" do scope/RLS. NO-OP de set_config fora do pgsql (sqlite em teste).
 */
trait TenantAwareJob
{
    /** Empresa capturada no dispatch (serializada com o job). */
    public ?int $tenantEmpresaId = null;

    /** Grupo capturado no dispatch. */
    public ?int $tenantGrupoId = null;

    /**
     * Captura o tenant ATIVO no momento do dispatch (chamar no construtor do job,
     * ainda dentro do request). Os ids são serializados junto com o job.
     */
    protected function capturarTenant(): void
    {
        $ctx = app(TenantContext::class);
        $this->tenantEmpresaId = $ctx->empresaId();
        $this->tenantGrupoId = $ctx->grupoId();
    }

    /**
     * Re-aplica o tenant capturado (chamar no início do handle()). Popula o
     * TenantContext (1ª barreira) e as variáveis de sessão do Postgres (2ª/RLS).
     */
    protected function aplicarTenant(): void
    {
        if ($this->tenantEmpresaId === null || $this->tenantGrupoId === null) {
            return; // sem tenant capturado → NO-OP (cron global/seed)
        }

        app(TenantContext::class)->set($this->tenantEmpresaId, $this->tenantGrupoId);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT set_config(?, ?, false), set_config(?, ?, false)', [
                'app.empresa_id', (string) $this->tenantEmpresaId,
                'app.grupo_id', (string) $this->tenantGrupoId,
            ]);
        }
    }
}
