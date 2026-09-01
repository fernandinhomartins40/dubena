<?php

namespace App\Console\Commands;

use App\Etl\Support\SnapshotDaFonte;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * F7-03 — tira o retrato da fonte legada, e compara com o anterior.
 *
 * ## Para que serve na prática
 *
 * Roda-se **antes do ensaio** e **de novo antes do cutover**. A segunda execução
 * responde a pergunta que decide se o ensaio ainda vale:
 *
 * > *A fonte mudou desde que validamos a conversão?*
 *
 * Se mudou — 300 clientes editados no sábado, uma coluna que sumiu — o ensaio
 * validou outra coisa, e seguir seria converter às cegas.
 *
 * ## Por que ler-só, sempre
 *
 * O comando não escreve nada na fonte, e não é isso que o `--dry-run` de outros
 * comandos protege: ele **é** read-only por construção. Registra o retrato no
 * banco novo, na tabela `conversao_snapshots`.
 */
class ConversaoSnapshot extends Command
{
    protected $signature = 'conversao:snapshot
        {--sistema=oracle : o sistema de origem}
        {--conexao=legado : a conexão de leitura}
        {--tabela=* : limita a estas tabelas; sem isto, todas as da conexão}
        {--comparar : compara com o retrato anterior e REPROVA se a fonte mudou}';

    protected $description = 'Retrato da fonte legada: schema, contagens, hashes e watermark (F7-03). Não escreve na fonte.';

    public function handle(SnapshotDaFonte $snapshot): int
    {
        $sistema = (string) $this->option('sistema');
        $conexao = (string) $this->option('conexao');

        try {
            $fonte = DB::connection($conexao);
            $fonte->getPdo();
        } catch (\Throwable $e) {
            $this->error("Não consegui abrir a conexão '{$conexao}': ".$e->getMessage());

            return self::FAILURE;
        }

        $tabelas = $this->tabelasAlvo($fonte);

        if ($tabelas === []) {
            // Lista vazia imprimindo sucesso é o defeito que esta base persegue
            // desde a F7: o registry vazio que dizia "ETL concluído". Zero
            // tabelas não é um snapshot limpo — é um snapshot que não aconteceu.
            $this->error('Nenhuma tabela encontrada na fonte. Snapshot de zero tabelas não é evidência.');

            return self::FAILURE;
        }

        $this->info("Lendo {$sistema} via conexão '{$conexao}' — ".count($tabelas).' tabela(s).');

        $tirados = 0;
        $falhas = [];

        foreach ($tabelas as $tabela) {
            $id = $snapshot->registrar(
                fonte: $fonte,
                sistemaOrigem: $sistema,
                tabela: $tabela,
                watermarkColuna: $this->watermarkDe($fonte, $tabela),
            );

            $id === null ? $falhas[] = $tabela : $tirados++;
        }

        $this->line("{$tirados} retrato(s) registrado(s).");

        if ($falhas !== []) {
            // Tabela que não foi lida NÃO pode passar como "sem mudança": é
            // ausência de dado, e tratá-la como estabilidade é o erro que
            // liberaria o cutover às cegas.
            $this->warn('Não consegui ler: '.implode(', ', $falhas));
            $this->line('Tabela sem retrato não é tabela sem mudança — ela fica FORA da comparação.');
        }

        if (! $this->option('comparar')) {
            return $falhas === [] ? self::SUCCESS : self::FAILURE;
        }

        return $this->compararComOAnterior($snapshot, $sistema, $tabelas, $falhas);
    }

    /**
     * @param  list<string>  $tabelas
     * @param  list<string>  $falhas
     */
    private function compararComOAnterior(
        SnapshotDaFonte $snapshot,
        string $sistema,
        array $tabelas,
        array $falhas,
    ): int {
        $mudancas = [];
        $semAnterior = [];

        foreach ($tabelas as $tabela) {
            // Sem retrato anterior não há comparação — e "não comparei" NÃO é
            // "não mudou". Deixar isto passar como sucesso faria a PRIMEIRA
            // execução, a única que por definição não tem com o que comparar,
            // imprimir "a fonte não mudou" e liberar o cutover.
            //
            // É a mesma família de defeito que esta base já pagou duas vezes:
            // registry vazio dizendo "ETL concluído", teste que varria zero
            // arquivos e passava. Ausência precisa ser afirmada, nunca inferida
            // do vazio.
            if (! $snapshot->temComparacao($sistema, $tabela)) {
                $semAnterior[] = $tabela;

                continue;
            }

            foreach ($snapshot->diferencas($sistema, $tabela) as $d) {
                $mudancas[] = $d;
            }
        }

        $this->newLine();

        if ($semAnterior !== []) {
            $this->warn(count($semAnterior).' tabela(s) sem retrato anterior: '.implode(', ', array_slice($semAnterior, 0, 10)));
            $this->line('Primeira leitura não compara com nada. Rode de novo antes do cutover.');

            return self::FAILURE;
        }

        if ($mudancas === [] && $falhas === []) {
            $this->info('A fonte não mudou desde o retrato anterior.');

            return self::SUCCESS;
        }

        foreach ($mudancas as $m) {
            $this->line('  '.$m);
        }

        $this->newLine();
        $this->error('A fonte MUDOU desde o retrato anterior — o ensaio validou outro estado.');

        return self::FAILURE;
    }

    /**
     * A coluna de corte, quando a tabela tem uma.
     *
     * Procurada por nome, entre as que o legado usa. Nulo quando não há: é
     * melhor não ter watermark que inventar uma coluna que não existe e falhar
     * na leitura.
     */
    private function watermarkDe(ConnectionInterface $fonte, string $tabela): ?string
    {
        $candidatas = ['updated_at', 'dataalteracao', 'data_alteracao', 'dtalteracao', 'id'];

        try {
            $existentes = array_map(
                fn ($c) => strtolower((string) $c['name']),
                $fonte->getSchemaBuilder()->getColumns($tabela),
            );
        } catch (\Throwable) {
            return null;
        }

        foreach ($candidatas as $c) {
            if (in_array($c, $existentes, true)) {
                return $c;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function tabelasAlvo(ConnectionInterface $fonte): array
    {
        $pedidas = (array) $this->option('tabela');

        if ($pedidas !== []) {
            return array_values(array_map('strval', $pedidas));
        }

        try {
            return array_values(array_map(
                fn ($t) => (string) $t['name'],
                $fonte->getSchemaBuilder()->getTables(),
            ));
        } catch (\Throwable) {
            return [];
        }
    }
}
