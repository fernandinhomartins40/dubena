<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Saas\Assinatura;
use App\Models\Saas\Plano;
use App\Models\Saas\TenantCompany;
use Illuminate\Console\Command;

/**
 * F2-04 — o retrato que decide se dá para ligar `SAAS_ENFORCE_LICENCA`.
 *
 * Duas perguntas, e o comando existe porque nenhuma delas se responde de cabeça:
 *
 *  1. Alguma empresa dentro da fronteira ficaria SEM módulo nenhum se o
 *     enforcement fosse ligado agora? (empresa sem assinatura vigente)
 *  2. Quantas ainda estão no plano de TRANSIÇÃO em vez de num plano vendável?
 *
 * A segunda é o que impede `Legacy Full` de virar permanente por esquecimento.
 * Um plano de transição sem ninguém contando quanto tempo ele dura deixa de ser
 * transição e vira o plano gratuito com tudo incluso que ninguém aprovou.
 *
 * Sai com FAILURE quando há empresa descoberta: assim serve de portão em
 * script de deploy, e não só de relatório para leitura humana.
 */
class SaasLicencaStatus extends Command
{
    protected $signature = 'saas:licenca:status {--tenant= : restringe a um TenantAccount}';

    protected $description = 'Mostra quem está sem assinatura e quem ainda depende do plano de transição (F2-04).';

    public function handle(): int
    {
        $empresas = TenantCompany::query()
            ->where('status', TenantCompany::STATUS_APPROVED)
            ->when($this->option('tenant'), fn ($q, $t) => $q->where('tenant_account_id', (int) $t))
            ->pluck('empresa_id');

        if ($empresas->isEmpty()) {
            $this->warn('Nenhuma empresa com vínculo de tenant aprovado.');

            return self::SUCCESS;
        }

        $nomes = Empresa::withoutGlobalScopes()
            ->whereIn('id', $empresas)
            ->pluck('razao_social', 'id');

        $vigentes = Assinatura::withoutTenant()
            ->with('plano:id,slug,nome,transitorio')
            ->whereIn('empresa_id', $empresas)
            ->whereIn('status', [Assinatura::STATUS_ATIVA, Assinatura::STATUS_TRIAL])
            ->get()
            ->keyBy('empresa_id');

        $semAssinatura = [];
        $emTransicao = [];

        foreach ($empresas as $id) {
            $assinatura = $vigentes->get($id);

            if ($assinatura === null) {
                $semAssinatura[] = $id;

                continue;
            }

            if ($assinatura->plano?->transitorio) {
                $emTransicao[] = $id;
            }
        }

        $this->line('Empresas na fronteira: '.$empresas->count());
        $this->line('Com assinatura vigente: '.$vigentes->count());

        if ($emTransicao !== []) {
            $this->newLine();
            $this->warn('Ainda no plano de TRANSIÇÃO ('.count($emTransicao).') — precisam migrar para um plano vendável:');
            foreach ($emTransicao as $id) {
                $this->line("  #{$id}  ".($nomes[$id] ?? '?'));
            }
        }

        if ($semAssinatura === []) {
            $this->newLine();
            $this->info('Nenhuma empresa descoberta: ligar SAAS_ENFORCE_LICENCA não tira módulo de ninguém.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('SEM assinatura ('.count($semAssinatura).') — perderiam TODOS os módulos se o enforcement fosse ligado agora:');
        foreach ($semAssinatura as $id) {
            $this->line("  #{$id}  ".($nomes[$id] ?? '?'));
        }
        $this->newLine();
        $this->comment('Rode `saas:legacy-full --dry-run` para ver a correção proposta.');

        return self::FAILURE;
    }
}
