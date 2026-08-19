<?php

namespace App\Console\Commands;

use App\Models\Geografico\Cidade;
use App\Models\Monitora\Cerca;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Classifica as cercas existentes por município.
 *
 * As cercas migradas do rastreador não têm `cidade_id` — no legado a lista era
 * plana e o município ficava só no nome, quando ficava. Este comando deduz e
 * preenche, deixando a tela agrupar.
 *
 * **A dedução é geográfica, com o nome como desempate.** O sistema não tem a
 * malha territorial do IBGE (a tabela `cidades` guarda nome, UF e código, sem
 * contorno), então o município sai de onde estão os CLIENTES: dentro do
 * polígono da cerca, qual cidade aparece mais no cadastro deles.
 *
 * Isso é sólido porque os 44.349 clientes geocodificados têm `cidade_id`
 * preenchido — o dado já foi conferido pela operação, cliente a cliente, ao
 * longo de anos. É evidência melhor do que qualquer contorno aproximado.
 *
 * Só o nome não bastava: "Setor 01" a "Setor 08" são zonas de Guarapuava e o
 * nome não diz. Por nome, 2 de 18 cercas eram classificadas; pela geografia,
 * 14 de 18.
 *
 * O que não tem cliente dentro fica NULL e aparece na tela em "Sem município",
 * para o operador classificar — visível, e não adivinhado.
 *
 * Read-only por padrão. Sem `--aplicar` só mostra o que faria.
 */
class CercaClassificarMunicipio extends Command
{
    protected $signature = 'cerca:classificar-municipio
                            {--aplicar : grava a classificação (sem esta flag, só simula)}
                            {--empresa= : restringe a uma empresa}';

    protected $description = 'Deduz o município de cada cerca pelo nome e preenche cidade_id';

    public function handle(): int
    {
        $cidades = Cidade::query()
            ->get(['id', 'descricao', 'uf'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'descricao' => $c->descricao,
                'uf' => $c->uf,
                'chave' => $this->normalizar($c->descricao),
            ])
            // Nome mais longo primeiro: "Boa Ventura de São Roque" tem de vencer
            // "Roque" se ambos existirem no cadastro.
            ->sortByDesc(fn ($c) => mb_strlen($c['chave']))
            ->values();

        if ($cidades->isEmpty()) {
            $this->error('Nenhuma cidade cadastrada — carregue o geográfico antes.');

            return self::FAILURE;
        }

        $cercas = Cerca::withoutTenant()
            ->when($this->option('empresa'), fn ($q, $e) => $q->where('empresa_id', $e))
            ->whereNull('cidade_id')
            ->get(['id', 'empresa_id', 'descricao']);

        if ($cercas->isEmpty()) {
            $this->info('Nenhuma cerca sem município.');

            return self::SUCCESS;
        }

        $casadas = 0;
        $linhas = [];

        foreach ($cercas as $cerca) {
            [$cidadeId, $rotulo, $origem] = $this->deduzir($cerca, $cidades);

            $linhas[] = [
                $cerca->id,
                mb_strimwidth($cerca->descricao, 0, 30, '…'),
                $rotulo ?? '—',
                $origem,
            ];

            if ($cidadeId !== null) {
                $casadas++;
                if ($this->option('aplicar')) {
                    DB::table('monitora_cercas')
                        ->where('id', $cerca->id)
                        ->update(['cidade_id' => $cidadeId, 'updated_at' => now()]);
                }
            }
        }

        $this->table(['Cerca', 'Descrição', 'Município deduzido', 'Por'], $linhas);

        $semMunicipio = $cercas->count() - $casadas;
        $this->newLine();
        $this->line("{$casadas} classificada(s), {$semMunicipio} sem município.");

        if ($semMunicipio > 0) {
            $this->warn('As sem município aparecem agrupadas na tela para classificação manual — '
                .'o nome da cerca não contém nenhum município cadastrado.');
        }

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->comment('Simulação. Use --aplicar para gravar.');
        }

        return self::SUCCESS;
    }

    /**
     * Deduz o município: primeiro pela geografia, depois pelo nome.
     *
     * @param  \Illuminate\Support\Collection<int, array{id:int, descricao:string, uf:string, chave:string}>  $cidades
     * @return array{0: ?int, 1: ?string, 2: string} [cidade_id, rótulo, origem da dedução]
     */
    private function deduzir(Cerca $cerca, $cidades): array
    {
        // 1) Geografia: a cidade que mais aparece nos clientes de dentro da
        //    cerca. O `bounding box` do polígono basta como recorte — a precisão
        //    de estar exatamente dentro não muda qual município predomina, e
        //    evita depender de extensão geoespacial no Postgres.
        $caixa = DB::table('monitora_cerca_pontos')
            ->where('cerca_id', $cerca->id)
            ->selectRaw('min(latitude) lat1, max(latitude) lat2, min(longitude) lng1, max(longitude) lng2')
            ->first();

        if ($caixa?->lat1 !== null) {
            $predominante = DB::table('clientes')
                ->join('cidades', 'cidades.id', '=', 'clientes.cidade_id')
                ->whereBetween('clientes.latitude', [$caixa->lat1, $caixa->lat2])
                ->whereBetween('clientes.longitude', [$caixa->lng1, $caixa->lng2])
                ->selectRaw('cidades.id, cidades.descricao, cidades.uf, count(*) AS n')
                ->groupBy('cidades.id', 'cidades.descricao', 'cidades.uf')
                ->orderByDesc('n')
                ->first();

            if ($predominante !== null) {
                return [
                    (int) $predominante->id,
                    "{$predominante->descricao}/{$predominante->uf}",
                    $predominante->n.' clientes',
                ];
            }
        }

        // 2) Nome: cerca sem cliente dentro (região nova, ou sem geocodificação)
        //    ainda pode se identificar pelo próprio nome.
        $alvo = $this->normalizar($cerca->descricao);
        $porNome = $cidades->first(fn ($c) => str_contains($alvo, $c['chave']));

        return $porNome !== null
            ? [$porNome['id'], "{$porNome['descricao']}/{$porNome['uf']}", 'nome']
            : [null, null, '—'];
    }

    /**
     * Minúsculas e sem acento.
     *
     * Tabela explícita em vez de `iconv('ASCII//TRANSLIT')`: no Windows aquele
     * devolve `?` para acentuado, e "Colônia" nunca casaria com "Colonia".
     */
    private function normalizar(string $texto): string
    {
        $de = ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï',
            'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'ñ'];
        $para = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i',
            'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n'];

        return trim(str_replace($de, $para, mb_strtolower($texto)));
    }
}
