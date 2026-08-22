<?php

namespace App\Console\Commands;

use App\Domain\Identidade\ConsolidarClientes;
use App\Domain\Identidade\IdentidadeCliente;
use App\Domain\Identidade\PesoTraco;
use App\Domain\Identidade\ResultadoIdentidade;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteRevisao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * identidade:varrer — encontra duplicatas que JÁ existem na base.
 *
 * O motor de identidade protege os cadastros NOVOS. Este comando aplica o mesmo
 * critério ao passivo: medido antes desta fase, 3.486 clientes duplicados
 * carregavam 20.702 pedidos — 5% do histórico fragmentado.
 *
 * Por default é somente leitura. Com `--consolidar`, funde apenas o que tem
 * escore >= 100 (documento igual, ou telefone + nome); o resto vai para a fila
 * de revisão, onde uma pessoa decide. Nada ambíguo é fundido automaticamente:
 * a base tem casos reais de duas pessoas no mesmo telefone (família), e fundi-
 * las seria um erro pior do que a duplicata.
 */
class IdentidadeVarrer extends Command
{
    protected $signature = 'identidade:varrer
        {--empresa= : Limita a uma empresa}
        {--consolidar : Funde automaticamente os pares de escore >= 100}
        {--enfileirar : Cria as revisões pendentes para a faixa de dúvida}
        {--limite=0 : Processa no máximo N clientes (0 = todos)}
        {--csv= : Grava o relatório neste arquivo}';

    protected $description = 'Varre a base em busca de cadastros duplicados usando o motor de identidade.';

    public function handle(IdentidadeCliente $identidade, ConsolidarClientes $consolidador): int
    {
        $empresa = $this->option('empresa');
        $limite = (int) $this->option('limite');
        $consolidar = (bool) $this->option('consolidar');
        $enfileirar = (bool) $this->option('enfileirar');

        // Só clientes que compartilham ALGUM traço com outro: comparar todos
        // contra todos seriam 1,5 bilhão de pares em 55 mil clientes.
        $ids = $this->comTracoCompartilhado($empresa, $limite);

        if ($ids === []) {
            $this->info('Nenhum cliente com traço compartilhado. Base sem duplicatas aparentes.');

            return self::SUCCESS;
        }

        $this->info(count($ids).' cliente(s) com traço em comum — pontuando…');
        $barra = $this->output->createProgressBar(count($ids));

        $automaticos = [];
        $revisao = [];
        $jaVistos = [];   // evita processar o par (a,b) e depois (b,a)

        // Consolidados de uma vez: uma consulta por cliente seriam 20 mil idas
        // ao banco para responder algo que cabe num set em memoria.
        $consolidados = array_flip(
            DB::table('cliente_vinculos')->pluck('cliente_id')->all(),
        );

        foreach ($ids as $id) {
            $barra->advance();

            if (isset($consolidados[$id])) {
                continue;
            }

            $cliente = Cliente::query()->with('telefones')->find($id);
            if ($cliente === null) {
                continue;
            }

            $dados = $identidade->tracosParaConsulta($cliente);
            $candidatos = $identidade->candidatos((int) $cliente->empresa_id, $dados, $cliente->id);

            foreach ($candidatos as $c) {
                $chave = min($cliente->id, $c->cliente->id).'-'.max($cliente->id, $c->cliente->id);
                if (isset($jaVistos[$chave])) {
                    continue;
                }
                $jaVistos[$chave] = true;

                // Guarda apenas IDS e o resumo. Acumular os models Eloquent
                // estourava a memoria por volta de 35% da base: 20 mil clientes
                // com relacoes carregadas nao cabem em 512 MB.
                $par = [
                    'a' => $cliente->id,
                    'nome_a' => $cliente->nome,
                    'b' => $c->cliente->id,
                    'nome_b' => $c->cliente->nome,
                    'escore' => $c->escore,
                    'confianca' => $c->confianca(),
                    'motivos' => $c->motivos,
                ];

                if ($c->consolidaAutomaticamente()) {
                    $automaticos[] = $par;
                } elseif ($c->mereceRevisao()) {
                    $revisao[] = $par;
                }
            }
        }

        $barra->finish();
        $this->newLine(2);

        $this->table(
            ['Faixa', 'Pares', 'O que acontece'],
            [
                ['Escore >= '.PesoTraco::LIMIAR_AUTOMATICO, count($automaticos), $consolidar ? 'FUNDIDOS agora' : 'seriam fundidos (use --consolidar)'],
                ['Escore '.PesoTraco::LIMIAR_REVISAO.'–'.(PesoTraco::LIMIAR_AUTOMATICO - 1), count($revisao), $enfileirar ? 'enfileirados para revisão' : 'ficariam na fila (use --enfileirar)'],
            ],
        );

        if ($this->option('csv')) {
            $this->gravarCsv(array_merge($automaticos, $revisao));
        }

        if ($consolidar) {
            $this->consolidar($automaticos, $consolidador);
        }

        if ($enfileirar) {
            $this->enfileirar($revisao);
        }

        if (! $consolidar && ! $enfileirar) {
            $this->warn('Somente leitura: nada foi alterado. Use --consolidar e/ou --enfileirar.');
        }

        return self::SUCCESS;
    }

    /**
     * Clientes que compartilham ao menos um traço com outro cliente.
     *
     * @return list<int>
     */
    private function comTracoCompartilhado(?string $empresa, int $limite): array
    {
        $q = DB::table('cliente_identidades as a')
            ->join('cliente_identidades as b', function ($j) {
                $j->on('a.valor', '=', 'b.valor')
                    ->on('a.tipo', '=', 'b.tipo')
                    ->on('a.empresa_id', '=', 'b.empresa_id')
                    ->on('a.cliente_id', '<>', 'b.cliente_id');
            })
            // `endereco` sozinho não indica duplicata (prédio/vila têm dezenas
            // de clientes no mesmo número) e traria ruído demais para a varredura.
            ->whereIn('a.tipo', ['telefone', 'cpf', 'cnpj', 'email', 'nome_fonetico'])
            ->when($empresa, fn ($x) => $x->where('a.empresa_id', $empresa))
            ->distinct()
            ->orderBy('a.cliente_id')
            ->pluck('a.cliente_id');

        $ids = $q->all();

        return $limite > 0 ? array_slice($ids, 0, $limite) : $ids;
    }

    /** @param list<array<string,mixed>> $pares */
    private function consolidar(array $pares, ConsolidarClientes $consolidador): void
    {
        $ok = 0;
        $falhas = 0;

        foreach ($pares as $par) {
            // O SOBREVIVENTE é o de id menor — o cadastro mais antigo, que
            // costuma ser o que carrega o histórico de pedidos.
            $principalId = min($par['a'], $par['b']);
            $absorvidoId = max($par['a'], $par['b']);

            $principal = Cliente::find($principalId);
            $absorvido = Cliente::find($absorvidoId);
            if ($principal === null || $absorvido === null) {
                continue;
            }

            try {
                $consolidador->executar($principal, $absorvido, $par['escore'], 'automatico', $par['motivos']);
                $ok++;
            } catch (\Throwable $e) {
                $falhas++;
                $this->line("  <fg=red>falhou</> #{$absorvidoId} → #{$principalId}: ".$e->getMessage());
            }
        }

        $this->info("{$ok} par(es) consolidado(s)".($falhas ? ", {$falhas} falha(s)." : '.'));
    }

    /** @param list<array<string,mixed>> $pares */
    private function enfileirar(array $pares): void
    {
        $n = 0;
        foreach ($pares as $par) {
            $empresaId = DB::table('clientes')->where('id', $par['a'])->value('empresa_id');
            if ($empresaId === null) {
                continue;
            }

            ClienteRevisao::query()->updateOrCreate(
                ['cliente_id' => $par['a'], 'candidato_id' => $par['b']],
                [
                    'empresa_id' => $empresaId,
                    'escore' => $par['escore'],
                    'tracos' => $par['motivos'],
                    'origem' => 'varredura',
                    'situacao' => 'pendente',
                ],
            );
            $n++;
        }

        $this->info("{$n} par(es) na fila de revisão.");
    }

    /** @param list<array<string,mixed>> $pares */
    private function gravarCsv(array $pares): void
    {
        $arquivo = fopen((string) $this->option('csv'), 'w');
        fputcsv($arquivo, ['id_a', 'nome_a', 'id_b', 'nome_b', 'escore', 'confianca', 'motivos']);

        foreach ($pares as $p) {
            fputcsv($arquivo, [
                $p['a'], $p['nome_a'], $p['b'], $p['nome_b'],
                $p['escore'], $p['confianca'], implode(' + ', $p['motivos']),
            ]);
        }

        fclose($arquivo);
        $this->info('CSV gravado em '.$this->option('csv'));
    }
}
