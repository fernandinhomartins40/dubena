<?php

namespace App\Console\Commands;

use App\Console\Concerns\EnxergaAtravesDaRls;
use App\Domain\Saas\AuditoriaPlataforma;
use App\Domain\Tenant\IdentidadeDeAutomacao;
use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantCompany;
use Illuminate\Console\Command;

/**
 * Provisiona a identidade com que as rotinas agendadas operam em cada tenant.
 *
 * Ato explícito, e não efeito colateral de deploy: dar a um processo que roda
 * sozinho o direito de ler e gravar dado de uma revenda é uma concessão, e
 * concessão deixa rastro. Cada execução que muda algo registra na trilha de
 * plataforma o que foi criado e em quais empresas.
 *
 * Idempotente: rodar de novo só cria o que falta e retira o grant de empresa
 * que deixou de estar aprovada. É o que se roda depois de aprovar uma empresa
 * nova num tenant.
 */
class SaasAutomacaoProvisionar extends Command
{
    use EnxergaAtravesDaRls;

    protected $signature = 'saas:automacao:provisionar
        {--tenant= : restringe a um TenantAccount; vazio = todos os que têm empresa aprovada}
        {--dry-run : mostra o que faria, sem gravar}';

    protected $description = 'Cria (ou ajusta) o usuário de serviço, o membership e os grants de automação de cada tenant.';

    public function handle(IdentidadeDeAutomacao $identidade, AuditoriaPlataforma $auditoria): int
    {
        // Pela conexão de owner: as tabelas de fronteira recusam escrita do
        // runtime por desenho (`WITH CHECK (false)`), e a leitura dele seria
        // cega sem envelope.
        return $this->comoOwner(fn () => $this->provisionar($identidade, $auditoria));
    }

    private function provisionar(IdentidadeDeAutomacao $identidade, AuditoriaPlataforma $auditoria): int
    {
        $tenants = TenantAccount::query()
            ->whereIn('id', TenantCompany::query()
                ->where('status', TenantCompany::STATUS_APPROVED)
                ->select('tenant_account_id'))
            ->when($this->option('tenant'), fn ($q, $t) => $q->whereKey((int) $t))
            ->orderBy('id')
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant com empresa aprovada.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($tenants as $tenant) {
                $empresas = TenantCompany::query()
                    ->where('tenant_account_id', $tenant->id)
                    ->where('status', TenantCompany::STATUS_APPROVED)
                    ->orderBy('empresa_id')->pluck('empresa_id')->all();
                $this->line("Tenant #{$tenant->id} {$tenant->legal_name}: identidade ".IdentidadeDeAutomacao::email($tenant->id)
                    .' com grant em '.count($empresas).' empresa(s): '.implode(', ', $empresas));
            }
            $this->comment('dry-run: nada foi gravado.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $r = $identidade->provisionar($tenant);
            $mudou = $r['usuario_criado'] || $r['membership_criada'] || $r['grants_criados'] > 0 || $r['grants_removidos'] > 0;

            $this->line(sprintf(
                'Tenant #%d %s: %s; grants +%d −%d; alcança %d empresa(s).',
                $tenant->id, $tenant->legal_name,
                $r['usuario_criado'] ? 'identidade CRIADA' : 'identidade já existia',
                $r['grants_criados'], $r['grants_removidos'], count($r['empresas']),
            ));

            // Só quando algo mudou: uma linha de trilha por execução ociosa
            // enterraria as que importam.
            if ($mudou) {
                $auditoria->registrar(
                    acao: 'automacao.provisionada',
                    entidade: 'tenant_accounts',
                    entidadeId: $tenant->id,
                    depois: $r,
                    motivo: 'Identidade de automação: as rotinas agendadas passam a operar no tenant com membership e grants próprios.',
                );
            }
        }

        return self::SUCCESS;
    }
}
