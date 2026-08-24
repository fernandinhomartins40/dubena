<?php

namespace App\Console\Commands;

use App\Domain\Satelite\GerarAlertasComodato;
use App\Domain\Satelite\VigilanciaComodatoService;
use App\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * comodato:vigiar — cruza vasilhame emprestado com histórico de compra.
 *
 * A pergunta: o cliente que está com 25 botijões da revenda compra gás aqui na
 * proporção de quem usa 25 botijões? Quando não compra, ou o vasilhame virou
 * ativo ocioso, ou está sendo enchido na concorrência.
 *
 * Por default só RELATA. `--aplicar` grava as avaliações e sincroniza a central
 * de alertas — que é o que o cron faz.
 */
class ComodatoVigiar extends Command
{
    protected $signature = 'comodato:vigiar
        {--empresa= : Id da empresa; sem isto, todas as ativas.}
        {--aplicar : Grava as avaliações e sincroniza os alertas.}
        {--todos : Lista também os clientes classificados como OK.}';

    protected $description = 'Cruza comodato com histórico de compra e alerta desproporções.';

    public function handle(VigilanciaComodatoService $vigilancia, GerarAlertasComodato $gerador): int
    {
        $empresas = $this->option('empresa') !== null
            ? [(int) $this->option('empresa')]
            : Empresa::query()->where('ativo', true)->pluck('id')->all();

        foreach ($empresas as $empresaId) {
            $this->porEmpresa($empresaId, $vigilancia, $gerador);
        }

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->warn('Somente leitura: nada foi gravado. Use --aplicar para registrar avaliações e alertas.');
        }

        return self::SUCCESS;
    }

    private function porEmpresa(int $empresaId, VigilanciaComodatoService $vigilancia, GerarAlertasComodato $gerador): void
    {
        if ($this->option('aplicar')) {
            $r = $gerador->executar($empresaId);

            $this->info(sprintf(
                'Empresa %d: %d avaliados | %d alerta(s) de giro | %d de vencimento | %d encerrado(s)',
                $empresaId, $r['avaliados'], $r['giro'], $r['vencimento'], $r['encerrados'],
            ));

            return;
        }

        // Leitura: avaliarEmpresa() grava a avaliação (é a série histórica, e
        // ela não é o alerta). Para não escrever num dry-run, roda em transação
        // revertida — o relatório sai igual e o banco fica intacto.
        $avaliacoes = [];
        DB::beginTransaction();
        try {
            $avaliacoes = $vigilancia->avaliarEmpresa($empresaId);
        } finally {
            DB::rollBack();
        }

        $this->relatar($empresaId, $avaliacoes);
    }

    /** @param list<\App\Models\Satelite\ComodatoAvaliacao> $avaliacoes */
    private function relatar(int $empresaId, array $avaliacoes): void
    {
        if ($avaliacoes === []) {
            $this->line("Empresa {$empresaId}: nenhum cliente com comodato acima do mínimo vigiado.");

            return;
        }

        $preocupantes = array_filter($avaliacoes, fn ($a) => $a->preocupante());

        usort($preocupantes, function ($a, $b) {
            // Ordem da fila: risco patrimonial primeiro. Um crítico com 4
            // vasilhames não vale a manhã que um crítico com 249 vale.
            $peso = fn ($x) => ($x->classificacao === 'CRITICO' ? 1_000_000 : 0) + (float) $x->em_posse;

            return $peso($b) <=> $peso($a);
        });

        $this->info(sprintf(
            'Empresa %d: %d cliente(s) com comodato | %d precisam de averiguação',
            $empresaId, count($avaliacoes), count($preocupantes),
        ));

        $mostrar = $this->option('todos') ? $avaliacoes : $preocupantes;

        if ($mostrar === []) {
            return;
        }

        $this->table(
            ['Cliente', 'Posse', 'Giro', 'Habitual', 'Sem comprar', 'Nível', 'Motivo'],
            array_map(fn ($a) => [
                mb_substr($a->cliente?->nome ?? "#{$a->cliente_id}", 0, 26),
                rtrim(rtrim(number_format((float) $a->em_posse, 2, ',', '.'), '0'), ','),
                number_format((float) $a->giro, 1, ',', '.').'x',
                $a->baseline_giro !== null ? number_format((float) $a->baseline_giro, 1, ',', '.').'x' : '—',
                $a->dias_sem_compra !== null ? $a->dias_sem_compra.'d' : '—',
                $a->classificacao,
                mb_substr((string) $a->motivo, 0, 46),
            ], array_slice($mostrar, 0, 40)),
        );
    }
}
