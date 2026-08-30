<?php

namespace App\Console\Commands;

use App\Domain\Saas\AuditoriaPlataforma;
use App\Domain\Saas\LicencaService;
use App\Models\Empresa;
use App\Models\Saas\Assinatura;
use App\Models\Saas\Plano;
use App\Models\Saas\TenantCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * F2-03 — vincula empresa a plano. É o que faz a licença existir de fato.
 *
 * O catálogo, os planos e o `LicencaService` já existiam, mas o banco tinha ZERO
 * assinaturas: sem assinatura, `LicencaService` é fail-closed e devolve lista
 * vazia de recursos. Ou seja, a licença estava correta e não decidia nada,
 * porque nunca havia o que decidir.
 *
 * Fica no console de propósito: assinar é ato comercial da plataforma, não
 * funcionalidade que o tenant executa sobre si mesmo.
 */
class SaasAssinaturaCriar extends Command
{
    protected $signature = 'saas:assinatura:criar
        {empresa : ID da empresa (ou "tenant" para todas as empresas de um tenant)}
        {plano : slug do plano (ex.: essencial, completo)}
        {--tenant= : quando `empresa` for "tenant", o ID do TenantAccount}
        {--status=ativa : ativa ou trial}
        {--trial-dias=30 : validade do trial, quando status=trial}
        {--fim= : data de término (YYYY-MM-DD); vazio = sem prazo}
        {--force : substitui assinatura vigente em vez de recusar}';

    protected $description = 'Cria a assinatura de uma empresa (ou de todas as empresas de um tenant) num plano.';

    public function handle(AuditoriaPlataforma $auditoria, LicencaService $licenca): int
    {
        $plano = Plano::query()->where('slug', $this->argument('plano'))->first();
        if ($plano === null || ! $plano->ativo) {
            $this->error('Plano inexistente ou inativo: '.$this->argument('plano'));

            return self::FAILURE;
        }

        $status = (string) $this->option('status');
        if (! in_array($status, [Assinatura::STATUS_ATIVA, Assinatura::STATUS_TRIAL], true)) {
            $this->error('Status inválido: use `ativa` ou `trial`.');

            return self::FAILURE;
        }

        $empresas = $this->empresasAlvo();
        if ($empresas === []) {
            $this->error('Nenhuma empresa alvo encontrada.');

            return self::FAILURE;
        }

        $criadas = 0;
        $puladas = 0;

        DB::transaction(function () use ($empresas, $plano, $status, $auditoria, $licenca, &$criadas, &$puladas) {
            foreach ($empresas as $empresaId) {
                $vigente = Assinatura::withoutTenant()
                    ->where('empresa_id', $empresaId)
                    ->whereIn('status', [Assinatura::STATUS_ATIVA, Assinatura::STATUS_TRIAL])
                    ->first();

                if ($vigente !== null && ! $this->option('force')) {
                    $this->warn("Empresa {$empresaId} já tem assinatura vigente (#{$vigente->id}); use --force para substituir.");
                    $puladas++;

                    continue;
                }

                // Substituir é cancelar a anterior, não apagá-la: a trilha de o
                // que a empresa teve contratado precisa sobreviver à troca.
                if ($vigente !== null) {
                    $vigente->update(['status' => Assinatura::STATUS_CANCELADA, 'fim' => now()]);
                }

                $assinatura = Assinatura::withoutTenant()->create([
                    'empresa_id' => $empresaId,
                    'plano_id' => $plano->id,
                    'status' => $status,
                    'inicio' => now(),
                    'fim' => $this->option('fim') ?: null,
                    'trial_ate' => $status === Assinatura::STATUS_TRIAL
                        ? now()->addDays(max(1, (int) $this->option('trial-dias')))
                        : null,
                ]);

                $auditoria->registrar(
                    acao: 'assinatura.criada',
                    empresaId: $empresaId,
                    entidade: 'assinaturas',
                    entidadeId: $assinatura->id,
                    depois: ['plano' => $plano->slug, 'status' => $status],
                );

                // O cache de recursos é por empresa: sem invalidar, a empresa
                // continuaria vendo a lista vazia de antes da assinatura.
                $licenca->invalidar($empresaId);
                $criadas++;
            }
        });

        $this->info("Assinaturas criadas: {$criadas}".($puladas > 0 ? " (puladas: {$puladas})" : '').'.');

        return $puladas > 0 && $criadas === 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<int> */
    private function empresasAlvo(): array
    {
        if ($this->argument('empresa') !== 'tenant') {
            $id = (int) $this->argument('empresa');

            return Empresa::withoutGlobalScopes()->whereKey($id)->exists() ? [$id] : [];
        }

        $tenantId = (int) $this->option('tenant');
        if ($tenantId <= 0) {
            $this->error('Informe --tenant=<id> ao usar "tenant" como alvo.');

            return [];
        }

        // Só empresas com vínculo APROVADO: assinar uma empresa fora da fronteira
        // criaria licença para algo que o resolver nega de qualquer forma.
        return TenantCompany::query()
            ->where('tenant_account_id', $tenantId)
            ->where('status', TenantCompany::STATUS_APPROVED)
            ->pluck('empresa_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
