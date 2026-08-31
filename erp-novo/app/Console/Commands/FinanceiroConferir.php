<?php

namespace App\Console\Commands;

use App\Domain\Financeiro\ReconciliacaoFinanceira;
use App\Models\Empresa;
use Illuminate\Console\Command;

/**
 * F5-10 — confere as invariantes do dinheiro por empresa e período.
 *
 * Read-only por desenho, como o `estoque:conferir` do F4 — e pela mesma razão:
 * se a soma das parcelas não bate com o título, ou o título está errado, ou
 * falta parcela. Ajustar resolve a tela e apaga a pergunta.
 *
 * Sai com FAILURE quando há divergência, para servir de portão em script — e não
 * só de relatório para leitura. É a diferença que faltava no
 * `notify:inconsistencias`, que reportava e devolvia sucesso.
 */
class FinanceiroConferir extends Command
{
    protected $signature = 'financeiro:conferir
        {--empresa= : confere só esta empresa; vazio = todas}
        {--inicio= : início do período (AAAA-MM-DD); padrão = primeiro dia do mês}
        {--fim= : fim do período (AAAA-MM-DD); padrão = hoje}
        {--csv= : grava o resultado neste arquivo}';

    protected $description = 'Confere as invariantes financeiras por empresa e período (F5-10). Não altera nada.';

    public function handle(ReconciliacaoFinanceira $reconciliacao): int
    {
        $inicio = (string) ($this->option('inicio') ?: now()->startOfMonth()->toDateString());
        $fim = (string) ($this->option('fim') ?: now()->toDateString());

        $empresas = Empresa::withoutGlobalScopes()
            ->when($this->option('empresa'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id')
            ->pluck('razao_social', 'id');

        if ($empresas->isEmpty()) {
            $this->error('Nenhuma empresa encontrada.');

            return self::FAILURE;
        }

        $this->info("Período: {$inicio} a {$fim}");

        $linhas = [];
        $comDivergencia = 0;

        foreach ($empresas as $empresaId => $nome) {
            $divergencias = $reconciliacao->divergencias((int) $empresaId, $inicio, $fim);

            if ($divergencias->isEmpty()) {
                continue;
            }

            $comDivergencia++;
            $this->newLine();
            $this->warn("Empresa #{$empresaId} — {$nome}: ".$divergencias->count().' divergência(s)');

            foreach ($divergencias->take(20) as $d) {
                $this->line(sprintf(
                    '  %-28s %-34s esperado %12.2f  obtido %12.2f%s',
                    $d['invariante'],
                    $d['referencia'],
                    (float) $d['esperado'],
                    (float) $d['obtido'],
                    isset($d['observacao']) ? '  ('.$d['observacao'].')' : '',
                ));
            }

            if ($divergencias->count() > 20) {
                $this->line('  ... e mais '.($divergencias->count() - 20).'. Use --csv para a lista completa.');
            }

            foreach ($divergencias as $d) {
                $linhas[] = array_merge(['empresa_id' => $empresaId, 'empresa' => $nome], $d);
            }
        }

        if ($caminho = $this->option('csv')) {
            $this->gravarCsv((string) $caminho, $linhas);
            $this->info('CSV gravado em '.$caminho);
        }

        if ($comDivergencia === 0) {
            $this->info("{$empresas->count()} empresa(s) conferida(s): tudo fecha.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error("{$comDivergencia} empresa(s) com divergência — ".count($linhas).' no total.');
        $this->line('Nada foi ajustado: a decisão é humana. Cada linha é uma pergunta a responder.');

        return self::FAILURE;
    }

    /** @param  list<array<string,mixed>>  $linhas */
    private function gravarCsv(string $caminho, array $linhas): void
    {
        $arquivo = fopen($caminho, 'w');

        if ($arquivo === false) {
            $this->error("Não consegui escrever em {$caminho}.");

            return;
        }

        fputcsv($arquivo, ['empresa_id', 'empresa', 'invariante', 'referencia', 'esperado', 'obtido', 'diferenca', 'observacao']);

        foreach ($linhas as $l) {
            fputcsv($arquivo, [
                $l['empresa_id'], $l['empresa'], $l['invariante'], $l['referencia'],
                $l['esperado'], $l['obtido'], $l['diferenca'] ?? '', $l['observacao'] ?? '',
            ]);
        }

        fclose($arquivo);
    }
}
