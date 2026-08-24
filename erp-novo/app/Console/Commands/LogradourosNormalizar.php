<?php

namespace App\Console\Commands;

use App\Domain\Geografico\NormalizarLogradouros;
use App\Models\Geografico\Cidade;
use Illuminate\Console\Command;

/**
 * logradouros:normalizar — compara o cadastro manual de ruas com o oficial do CNEFE.
 *
 * O que ele revela, medido na base: "Rua 10 de Setembro" e "Rua Dez de
 * Setembro" são a MESMA via cadastrada duas vezes, e "Rua Sete de Seetembro"
 * é erro de digitação. Os três passam pela busca do sistema e o entregador
 * escolhe qualquer um.
 *
 * Por default só relata. `--aplicar` renomeia os casos de alta confiança
 * MANTENDO o id — nenhum cliente muda de rua. Fusão de duplicatas NÃO é feita
 * aqui: envolve remapear clientes e é decisão da tela de revisão.
 */
class LogradourosNormalizar extends Command
{
    protected $signature = 'logradouros:normalizar
        {cidade : Id da cidade, ou parte do nome}
        {--aplicar : Renomeia os casos prováveis para o nome oficial (o id não muda).}
        {--todos : Lista também os que já batem exatamente.}';

    protected $description = 'Compara as ruas cadastradas à mão com o cadastro oficial do CNEFE.';

    public function handle(NormalizarLogradouros $normalizador): int
    {
        $cidade = $this->encontrarCidade((string) $this->argument('cidade'));

        if ($cidade === null) {
            return self::FAILURE;
        }

        $analise = $normalizador->analisar($cidade);

        if ($analise === []) {
            $this->error("Nenhum logradouro oficial para {$cidade->descricao}.");
            $this->line('Importe primeiro:');
            $this->line("  python scripts/cnefe_importar.py --municipio {$cidade->cod_ibge}");
            $this->line('  php artisan cnefe:importar cnefe.csv --aplicar');

            return self::FAILURE;
        }

        $porSituacao = ['exato' => [], 'provavel' => [], 'ausente' => []];
        foreach ($analise as $item) {
            $porSituacao[$item['situacao']][] = $item;
        }

        $this->info(sprintf(
            '%s: %d ruas | %d conferem | %d prováveis | %d sem correspondência',
            $cidade->descricao,
            count($analise),
            count($porSituacao['exato']),
            count($porSituacao['provavel']),
            count($porSituacao['ausente']),
        ));

        if ($porSituacao['provavel'] !== []) {
            $this->newLine();
            $this->line('<comment>Prováveis correções:</comment>');
            $this->table(
                ['Cadastrado', 'Oficial (CNEFE)', 'Semelhança'],
                array_map(fn ($i) => [
                    mb_substr((string) $i['rua']->descricao, 0, 38),
                    mb_substr($i['oficial']->nome_completo, 0, 38),
                    number_format($i['similaridade'] * 100, 0).'%',
                ], array_slice($porSituacao['provavel'], 0, 30)),
            );
        }

        $duplicatas = $normalizador->duplicatas($cidade);

        if ($duplicatas !== []) {
            $this->newLine();
            $this->line('<comment>Ruas do cadastro que apontam para a MESMA via oficial:</comment>');
            $this->table(
                ['Via oficial', 'Cadastradas como'],
                array_map(fn ($g) => [
                    mb_substr($g['oficial']->nome_completo, 0, 34),
                    implode(' | ', array_map(fn ($r) => "#{$r->id} {$r->descricao}", $g['ruas'])),
                ], array_slice($duplicatas, 0, 20)),
            );
            $this->line('A FUSÃO não é feita por este comando: ela remapeia clientes e é decisão da tela de revisão.');
        }

        if ($this->option('todos') && $porSituacao['ausente'] !== []) {
            $this->newLine();
            $this->line('<comment>Sem correspondência no CNEFE (rua nova, rural, ou nome muito diferente):</comment>');
            foreach (array_slice($porSituacao['ausente'], 0, 25) as $i) {
                $this->line('  '.$i['rua']->descricao);
            }
        }

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->warn('Somente leitura: nada foi alterado. Use --aplicar para renomear os prováveis.');

            return self::SUCCESS;
        }

        $aplicadas = 0;
        foreach ($porSituacao['provavel'] as $i) {
            $normalizador->aplicar($i['rua'], $i['oficial']);
            $aplicadas++;
        }

        $this->info("{$aplicadas} rua(s) renomeada(s) para o nome oficial. Os ids não mudaram — nenhum cliente trocou de rua.");

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
}
