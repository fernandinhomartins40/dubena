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
 * F2-04 — associa `Legacy Full` explicitamente às empresas que JÁ operavam.
 *
 * A ordem aqui é a coisa toda. `LicencaService` é fail-closed: sem assinatura,
 * nenhum recurso é liberado. Enquanto `SAAS_ENFORCE_LICENCA` está desligado isso
 * não machuca ninguém — mas no instante em que for ligado, toda empresa sem
 * assinatura perde todos os módulos de uma vez.
 *
 * Então: primeiro assinar quem já opera, depois ligar o enforcement. Este
 * comando é o "primeiro".
 *
 * Por que um comando e não um seeder: seeder roda a cada deploy e não pergunta
 * nada. Assinar empresa é ato comercial, precisa de dry-run, de conferência do
 * alvo e de trilha de quem mandou fazer.
 *
 * O alvo é deliberadamente estreito — só empresa com vínculo de tenant APROVADO
 * e sem assinatura vigente. Empresa fora da fronteira o resolver nega de
 * qualquer forma; empresa já assinante não pode ser rebaixada para um plano de
 * transição por um comando de manutenção.
 */
class SaasLegacyFull extends Command
{
    protected $signature = 'saas:legacy-full
        {--tenant= : restringe a um TenantAccount; vazio = todos}
        {--dry-run : mostra o que faria, sem gravar}
        {--force : confirma a execução fora de dry-run}';

    protected $description = 'Assina em Legacy Full as empresas que já operavam e ainda não têm assinatura (F2-04).';

    public function handle(AuditoriaPlataforma $auditoria, LicencaService $licenca): int
    {
        $plano = Plano::query()->where('slug', Plano::SLUG_LEGADO)->first();

        if ($plano === null) {
            $this->error('Plano `'.Plano::SLUG_LEGADO.'` não existe. Rode `db:seed --class=PlanosSeeder` antes.');

            return self::FAILURE;
        }

        // Um `Legacy Full` que perdeu o `transitorio` viraria oferta comercial
        // sem que ninguém tivesse decidido isso — melhor recusar e avisar.
        if (! $plano->transitorio || ! $plano->ativo) {
            $this->error('Plano `'.Plano::SLUG_LEGADO.'` precisa estar ativo e marcado como transitório.');

            return self::FAILURE;
        }

        $alvo = $this->empresasSemAssinatura();

        if ($alvo === []) {
            $this->info('Nenhuma empresa elegível: todas as empresas com vínculo aprovado já têm assinatura vigente.');

            return self::SUCCESS;
        }

        $this->line('Empresas que receberiam `'.Plano::SLUG_LEGADO.'`:');
        foreach ($alvo as $id => $nome) {
            $this->line("  #{$id}  {$nome}");
        }
        $this->line('Total: '.count($alvo));

        if ($this->option('dry-run')) {
            $this->comment('dry-run: nada foi gravado.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Criar as assinaturas acima?', false)) {
            $this->comment('Cancelado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($alvo, $plano, $auditoria, $licenca) {
            foreach (array_keys($alvo) as $empresaId) {
                $assinatura = Assinatura::withoutTenant()->create([
                    'empresa_id' => $empresaId,
                    'plano_id' => $plano->id,
                    'status' => Assinatura::STATUS_ATIVA,
                    'inicio' => now(),
                    // Sem `fim`: um prazo aqui desligaria a operação de quem já
                    // roda numa data futura que ninguém lembraria de renovar. A
                    // pressão para migrar é o relatório, não um corte automático.
                    'fim' => null,
                ]);

                $auditoria->registrar(
                    acao: 'assinatura.criada',
                    empresaId: $empresaId,
                    entidade: 'assinaturas',
                    entidadeId: $assinatura->id,
                    depois: ['plano' => $plano->slug, 'status' => Assinatura::STATUS_ATIVA, 'origem' => 'f2-04:legacy-full'],
                    motivo: 'Transição F2-04: empresa já operava antes de a licença passar a decidir.',
                );

                $licenca->invalidar($empresaId);
            }
        });

        $this->info('Assinaturas de transição criadas: '.count($alvo).'.');
        $this->comment('Confira com `saas:legacy-full --dry-run` e só então ligue SAAS_ENFORCE_LICENCA.');

        return self::SUCCESS;
    }

    /**
     * Empresas com vínculo de tenant APROVADO e sem assinatura vigente.
     *
     * @return array<int, string> id => razão social
     */
    private function empresasSemAssinatura(): array
    {
        $vinculadas = TenantCompany::query()
            ->where('status', TenantCompany::STATUS_APPROVED)
            ->when($this->option('tenant'), fn ($q, $t) => $q->where('tenant_account_id', (int) $t))
            ->pluck('empresa_id');

        $jaAssinam = Assinatura::withoutTenant()
            ->whereIn('empresa_id', $vinculadas)
            ->whereIn('status', [Assinatura::STATUS_ATIVA, Assinatura::STATUS_TRIAL])
            ->pluck('empresa_id');

        return Empresa::withoutGlobalScopes()
            ->whereIn('id', $vinculadas)
            ->whereNotIn('id', $jaAssinam)
            ->orderBy('id')
            ->pluck('razao_social', 'id')
            ->map(fn ($nome) => (string) $nome)
            ->all();
    }
}
