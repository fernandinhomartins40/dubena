<?php

namespace App\Console\Commands;

use App\Domain\Geografico\ImportarLogradouros;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\ImportacaoLogradouro;
use Illuminate\Console\Command;

/**
 * logradouros:importar — popula ruas e bairros de uma cidade a partir da base de CEP.
 *
 * Serve ao onboarding SaaS: a revenda nova entra com a base geográfica vazia e
 * o entregador acaba digitando o mesmo logradouro de dez formas diferentes.
 * Com a cidade importada, rua e bairro viram seleção, não digitação.
 *
 * Somente leitura por default — a varredura roda e mostra o que viria, sem
 * gravar. A gravação só cria e completa: rua existente mantém o id, porque há
 * dezenas de milhares de clientes apontando para `ruas.id`.
 */
class LogradourosImportar extends Command
{
    protected $signature = 'logradouros:importar
        {cidade : Id da cidade, ou parte do nome}
        {--aplicar : Grava no banco. Sem esta flag, somente leitura.}
        {--forcar : Reimporta mesmo que a cidade já tenha sido importada.}';

    protected $description = 'Importa ruas e bairros de uma cidade a partir da base de CEP dos Correios.';

    public function handle(ImportarLogradouros $importador): int
    {
        $cidade = $this->encontrarCidade((string) $this->argument('cidade'));

        if ($cidade === null) {
            return self::FAILURE;
        }

        $anterior = ImportacaoLogradouro::withoutGrupo()
            ->where('cidade_id', $cidade->id)
            ->where('situacao', 'concluida')
            ->latest('id')
            ->first();

        if ($anterior !== null && ! $this->option('forcar')) {
            $this->warn("'{$cidade->descricao}' já foi importada em {$anterior->created_at->format('d/m/Y H:i')} ({$anterior->ruas_criadas} ruas criadas).");
            $this->line('Use --forcar para reimportar (é idempotente: completa o que faltar, não duplica).');

            return self::SUCCESS;
        }

        $this->info("Varrendo logradouros de {$cidade->descricao}/{$cidade->uf}…");
        $this->line('A fonte limita os resultados por consulta; termos que batem o teto são refinados automaticamente.');
        $this->line('A varredura leva minutos. Se for por SSH, rode desacoplado da sessão (docker exec -d / nohup):');
        $this->line('uma desconexão mata o processo e a transação inteira é desfeita — nada é gravado pela metade.');

        $barra = $this->output->createProgressBar();
        $barra->setFormat(' %current% consultas | %message%');
        $barra->setMessage('iniciando');
        $barra->start();

        $r = $importador->varrer(
            (string) $cidade->uf,
            $this->nomeParaBusca($cidade),
            function (string $termo, int $achados, int $total) use ($barra) {
                $barra->setMessage("'{$termo}' → {$achados} | {$total} logradouros únicos");
                $barra->advance();
            },
        );

        $barra->finish();
        $this->newLine(2);

        $total = count($r['logradouros']);
        $this->info("{$total} logradouros únicos em {$r['consultas']} consultas.");

        if ($r['truncados'] > 0) {
            // Ser honesto sobre a incompletude é o ponto: a alternativa é o
            // operador acreditar que a cidade está 100% importada quando não está.
            $this->warn("{$r['truncados']} termo(s) continuaram no teto após o refino — esta importação pode estar INCOMPLETA.");
        }

        if ($total === 0) {
            $this->error('Nada encontrado. Confira se o nome da cidade bate com o da base de CEP.');

            return self::FAILURE;
        }

        $amostra = array_slice(array_values($r['logradouros']), 0, 8);
        $this->table(
            ['Logradouro', 'Bairro', 'CEP'],
            array_map(fn ($i) => [mb_substr($i['logradouro'], 0, 40), mb_substr($i['bairro'], 0, 24), $i['cep']], $amostra),
        );

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->warn('Somente leitura: nada foi gravado. Use --aplicar.');

            return self::SUCCESS;
        }

        $g = $importador->gravar($cidade, $r['logradouros']);

        ImportacaoLogradouro::create([
            'grupo_id' => $cidade->grupo_id,
            'cidade_id' => $cidade->id,
            'fonte' => 'viacep',
            'ruas_criadas' => $g['ruas_criadas'],
            'bairros_criados' => $g['bairros_criados'],
            'ruas_atualizadas' => $g['ruas_atualizadas'],
            'consultas' => $r['consultas'],
            'termos_truncados' => $r['truncados'],
            'situacao' => 'concluida',
        ]);

        $this->info("Ruas criadas: {$g['ruas_criadas']} | Bairros criados: {$g['bairros_criados']} | Ruas completadas: {$g['ruas_atualizadas']}");
        $this->line('Nenhuma rua existente foi recriada — os ids apontados pelos clientes ficaram intactos.');

        return self::SUCCESS;
    }

    /** Aceita id ou parte do nome; exige desambiguação quando há mais de uma. */
    private function encontrarCidade(string $busca): ?Cidade
    {
        if (ctype_digit($busca)) {
            $c = Cidade::withoutGrupo()->find((int) $busca);
            if ($c === null) {
                $this->error("Cidade id {$busca} não existe.");
            }

            return $c;
        }

        $achadas = Cidade::withoutGrupo()
            ->whereRaw('lower(descricao) like ?', ['%'.mb_strtolower($busca).'%'])
            ->orderBy('descricao')
            ->get();

        if ($achadas->isEmpty()) {
            $this->error("Nenhuma cidade casa com '{$busca}'.");

            return null;
        }

        if ($achadas->count() > 1) {
            $this->error("'{$busca}' casa com ".$achadas->count().' cidades. Use o id:');
            $this->table(['Id', 'Cidade', 'UF'], $achadas->map(fn ($c) => [$c->id, $c->descricao, $c->uf])->all());

            return null;
        }

        return $achadas->first();
    }

    /**
     * Nome usado na consulta à base de CEP.
     *
     * A base de CEP conhece o MUNICÍPIO. A cidade do grupo pode ser um distrito
     * ("Palmeirinha (Guarapuava)"), e nesse caso o nome que a fonte reconhece é
     * o que está entre parênteses.
     */
    private function nomeParaBusca(Cidade $cidade): string
    {
        if (preg_match('/\(([^)]+)\)/', (string) $cidade->descricao, $m) === 1) {
            return trim($m[1]);
        }

        return (string) $cidade->descricao;
    }
}
