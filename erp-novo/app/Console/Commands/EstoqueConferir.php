<?php

namespace App\Console\Commands;

use App\Domain\Estoque\ConferenciaDeSaldo;
use App\Domain\Satelite\ConferenciaDeCustodia;
use App\Models\Empresa;
use Illuminate\Console\Command;

/**
 * F4-02 — confere a projeção de saldo contra a soma do ledger.
 *
 * Read-only por desenho. O gate da F4 diz que *"divergência nunca é ajustada
 * silenciosamente"*, e este comando é a materialização disso: ele mostra, e a
 * decisão é humana.
 *
 * Sai com FAILURE quando há divergência, para servir de portão em script de
 * deploy — e não só de relatório para leitura.
 */
class EstoqueConferir extends Command
{
    protected $signature = 'estoque:conferir
        {--empresa= : confere só esta empresa; vazio = todas}
        {--csv= : grava o resultado neste arquivo}';

    protected $description = 'Compara o saldo projetado com a soma do ledger (F4-02). Não altera nada.';

    public function handle(ConferenciaDeSaldo $conferencia, ConferenciaDeCustodia $custodia): int
    {
        $empresas = Empresa::withoutGlobalScopes()
            ->when($this->option('empresa'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id')
            ->pluck('razao_social', 'id');

        if ($empresas->isEmpty()) {
            $this->error('Nenhuma empresa encontrada.');

            return self::FAILURE;
        }

        $linhas = [];
        $comDivergencia = 0;

        foreach ($empresas as $empresaId => $nome) {
            $daCustodia = $custodia->divergencias((int) $empresaId);

            if ($daCustodia->isNotEmpty()) {
                $comDivergencia++;
                $this->newLine();
                $this->warn("Empresa #{$empresaId} — {$nome}: ".$daCustodia->count().' divergência(s) de CUSTÓDIA');

                foreach ($daCustodia->take(20) as $d) {
                    $this->line(sprintf(
                        '  comodato %-6d cliente %-6d  projetado %10.3f  ledger %10.3f  dif %10.3f',
                        $d['comodato_id'], $d['cliente_id'], $d['projetado'], $d['ledger'], $d['diferenca'],
                    ));
                }
            }

            $divergencias = $conferencia->divergencias((int) $empresaId);

            if ($divergencias->isEmpty()) {
                continue;
            }

            $comDivergencia++;
            $this->newLine();
            $this->warn("Empresa #{$empresaId} — {$nome}: ".$divergencias->count().' divergência(s) de ESTOQUE');

            foreach ($divergencias->take(20) as $d) {
                $this->line(sprintf(
                    '  setor %-5d produto %-6d  projetado %12.3f  ledger %12.3f  dif %12.3f',
                    $d['setor_id'], $d['produto_id'], $d['projetado'], $d['ledger'], $d['diferenca'],
                ));

                $linhas[] = array_merge(['empresa_id' => $empresaId], $d);
            }

            if ($divergencias->count() > 20) {
                $this->line('  ... e mais '.($divergencias->count() - 20).' (use --csv para a lista completa)');

                foreach ($divergencias->skip(20) as $d) {
                    $linhas[] = array_merge(['empresa_id' => $empresaId], $d);
                }
            }
        }

        if ($this->option('csv') && $linhas !== []) {
            $this->gravarCsv((string) $this->option('csv'), $linhas);
        }

        if ($comDivergencia === 0) {
            $this->info('Estoque e custódia fecham com o ledger em '.$empresas->count().' empresa(s).');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error("{$comDivergencia} verificacao(oes) com divergência entre projeção e ledger.");
        $this->comment('Este comando NÃO ajusta: uma diferença pode ser bug da projeção OU');
        $this->comment('movimento faltando no ledger, e sobrescrever apaga a pergunta.');

        return self::FAILURE;
    }

    /** @param  list<array<string, mixed>>  $linhas */
    private function gravarCsv(string $caminho, array $linhas): void
    {
        $arquivo = fopen($caminho, 'w');
        fputcsv($arquivo, array_keys($linhas[0]));

        foreach ($linhas as $linha) {
            fputcsv($arquivo, $linha);
        }

        fclose($arquivo);
        $this->info('CSV gravado: '.$caminho);
    }
}
