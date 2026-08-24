<?php

namespace App\Console\Commands;

use App\Domain\Geografico\ColisaoDeNome;
use App\Domain\Geografico\NormalizarCidades;
use App\Models\Cliente\Cliente;
use Illuminate\Console\Command;

/**
 * cidades:normalizar — acerta as cidades cadastradas à mão contra o IBGE.
 *
 * A conciliação de CÓDIGO (`ibge:sincronizar`) resolveu o vínculo, mas não o
 * nome. Sobrou na base:
 *   "MATELANDIA", "Lidianopolis"   → sem acento
 *   "Jaraguá do Siul"              → digitação
 *   "Rua Palhoça"                  → o "Rua" entrou no nome da cidade
 *   "CAMPO LARGO"/SC c/ cód. de Fraiburgo → vínculo ERRADO
 *
 * `--nomes` acerta a grafia (seguro: mesmo município, só o texto muda).
 * O vínculo suspeito NUNCA é corrigido em lote — trocar o município de uma
 * cidade move clientes de praça, e a decisão é do dono.
 */
class CidadesNormalizar extends Command
{
    protected $signature = 'cidades:normalizar
        {--nomes : Corrige a grafia dos nomes divergentes (mesmo município).}
        {--todas : Mostra também as que já estão corretas e os distritos.}';

    protected $description = 'Compara as cidades cadastradas à mão com o catálogo oficial do IBGE.';

    public function handle(NormalizarCidades $normalizador): int
    {
        $analise = $normalizador->analisar();

        if ($analise === []) {
            $this->error('Nenhuma cidade cadastrada.');

            return self::FAILURE;
        }

        $por = ['ok' => [], 'nome_divergente' => [], 'vinculo_suspeito' => [],
            'distrito' => [], 'sugestao_uf' => [], 'sem_correspondencia' => [], 'sem_vinculo' => []];

        foreach ($analise as $i) {
            $por[$i['situacao']][] = $i;
        }

        $this->info(sprintf(
            '%d cidades | %d corretas | %d nome torto | %d vínculo suspeito | %d distritos | %d sem correspondência',
            count($analise),
            count($por['ok']),
            count($por['nome_divergente']),
            count($por['vinculo_suspeito']),
            count($por['distrito']),
            count($por['sugestao_uf']) + count($por['sem_correspondencia']) + count($por['sem_vinculo']),
        ));

        if ($por['nome_divergente'] !== []) {
            $this->newLine();
            $this->line('<comment>Nome divergente (mesmo município — seguro corrigir):</comment>');
            $this->table(
                ['Id', 'Cadastrado', 'Oficial', 'UF', 'Clientes'],
                array_map(fn ($i) => [
                    $i['cidade']->id,
                    mb_substr((string) $i['cidade']->descricao, 0, 30),
                    mb_substr($i['oficial']->nome, 0, 30),
                    $i['oficial']->uf,
                    $this->clientes($i['cidade']->id),
                ], $por['nome_divergente']),
            );
        }

        if ($por['vinculo_suspeito'] !== []) {
            $this->newLine();
            $this->line('<error>VÍNCULO SUSPEITO — o nome não corresponde ao município vinculado:</error>');
            $this->table(
                ['Id', 'Cadastrado', 'UF', 'Vinculado a', 'Deveria ser', 'Clientes'],
                array_map(fn ($i) => [
                    $i['cidade']->id,
                    mb_substr((string) $i['cidade']->descricao, 0, 24),
                    $i['cidade']->uf,
                    $i['oficial']->nome.'/'.$i['oficial']->uf,
                    $i['sugerido']->nome.'/'.$i['sugerido']->uf,
                    $this->clientes($i['cidade']->id),
                ], $por['vinculo_suspeito']),
            );
            $this->line('NÃO corrigido em lote: trocar o município move clientes de praça. Use a tela ou decida caso a caso.');
        }

        $semVinculo = array_merge($por['sugestao_uf'], $por['sem_correspondencia'], $por['sem_vinculo']);

        if ($semVinculo !== []) {
            $this->newLine();
            $this->line('<comment>Sem vínculo com o catálogo:</comment>');
            $this->table(
                ['Id', 'Cadastrado', 'UF', 'Sugestão do catálogo', 'Clientes'],
                array_map(fn ($i) => [
                    $i['cidade']->id,
                    mb_substr((string) $i['cidade']->descricao, 0, 26),
                    $i['cidade']->uf,
                    $i['sugerido'] !== null
                        ? $i['sugerido']->nome.'/'.$i['sugerido']->uf.' ('.$i['sugerido']->cod_ibge.')'
                        : '— revisar à mão',
                    $this->clientes($i['cidade']->id),
                ], $semVinculo),
            );
        }

        $duplicatas = $normalizador->duplicatas();

        if ($duplicatas !== []) {
            $this->newLine();
            $this->line('<comment>Cidades que apontam para o MESMO município (distritos já excluídos):</comment>');
            $this->table(
                ['Município oficial', 'Cadastradas como'],
                array_map(fn ($g) => [
                    $g['oficial']->nome.'/'.$g['oficial']->uf,
                    implode(' | ', array_map(
                        fn ($c) => "#{$c->id} {$c->descricao} (".$this->clientes($c->id).' cli)',
                        $g['cidades'],
                    )),
                ], $duplicatas),
            );
        }

        if ($this->option('todas') && $por['distrito'] !== []) {
            $this->newLine();
            $this->line('<comment>Distritos/localidades (nome próprio dentro do município — corretos):</comment>');
            foreach ($por['distrito'] as $i) {
                $this->line("  #{$i['cidade']->id} {$i['cidade']->descricao} → {$i['oficial']->nome}");
            }
        }

        if (! $this->option('nomes')) {
            $this->newLine();
            $this->warn('Somente leitura: nada foi alterado. Use --nomes para corrigir a grafia.');

            return self::SUCCESS;
        }

        $corrigidas = 0;
        $colisoes = [];

        foreach ($por['nome_divergente'] as $i) {
            try {
                $normalizador->corrigirNome($i['cidade'], $i['oficial']);
                $corrigidas++;
            } catch (ColisaoDeNome $e) {
                // Uma colisão não pode abortar as outras correções: cada cidade
                // é independente, e as que dão para acertar devem ser acertadas.
                $colisoes[] = $e;
            }
        }

        $this->info("{$corrigidas} nome(s) corrigido(s). Os ids não mudaram — nenhum cliente trocou de cidade.");

        if ($colisoes !== []) {
            $this->newLine();
            $this->line('<comment>Não corrigidas — o nome oficial já pertence a outro registro:</comment>');
            $this->table(
                ['Manter', 'Duplicata', 'Nome oficial', 'Clientes na duplicata'],
                array_map(fn (ColisaoDeNome $c) => [
                    "#{$c->existente->id} {$c->existente->descricao}",
                    "#{$c->cidade->id} {$c->cidade->descricao}",
                    $c->oficial->nome.'/'.$c->oficial->uf,
                    $this->clientes($c->cidade->id),
                ], $colisoes),
            );
            $this->line('São a mesma cidade em dois registros. A fusão move clientes entre eles — decisão do dono.');
        }

        return self::SUCCESS;
    }

    private function clientes(int $cidadeId): int
    {
        return Cliente::withoutTenant()->where('cidade_id', $cidadeId)->count();
    }
}
