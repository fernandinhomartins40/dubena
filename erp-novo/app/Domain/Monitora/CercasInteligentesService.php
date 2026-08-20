<?php

namespace App\Domain\Monitora;

use App\Domain\Monitora\Contracts\AjustadorDeVia;
use App\Domain\Monitora\Contracts\MalhaViaria;
use App\Models\Monitora\Cerca;

/**
 * As ferramentas assistidas da aba Cercas.
 *
 * Existe porque as cercas herdadas foram desenhadas a olho: os contornos reais
 * têm segmentos de 100 a 700 m contra quarteirões de ~100 m, ou seja, as bordas
 * cortam quadra no meio. Endereço em quadra cortada cai no setor errado, e o
 * entregador vai parar na rua que não é dele.
 *
 * Três ferramentas, todas SUGESTÕES — nenhuma grava sozinha:
 *  - `quadra()`   fecha o quarteirão em volta de um clique;
 *  - `ajustar()`  encaixa um contorno existente nas ruas (a vareta mágica);
 *  - `conflitos()` acha onde duas cercas disputam o mesmo endereço.
 */
class CercasInteligentesService
{
    /**
     * Margem em volta do clique ao buscar a malha, em graus (~550 m).
     *
     * Precisa passar das ruas que fecham a quadra: com margem curta demais, a
     * rua de trás fica fora do retângulo, a face não fecha e o contorno vaza
     * para o quarteirão vizinho.
     */
    private const MARGEM_QUADRA = 0.005;

    /**
     * A partir de quantas vezes maior a outra cerca é tida como ÁREA-MÃE.
     *
     * Aninhamento não é conflito: a "Área de entrega — Guarapuava" engloba os
     * setores de propósito. O primeiro critério que tentei foi "90% da área
     * dentro da outra", e ele falhou nos dados reais — a cerca-mãe é um
     * quadrilátero folgado de 4 pontos que corta a beirada dos setores, então a
     * contenção fica em 46% a 83% e escapa do corte. Resultado: os 5 avisos
     * emitidos eram TODOS dela, e nenhum era acionável.
     *
     * O tamanho relativo é o sinal honesto: uma área 4 vezes maior que a outra
     * é envelope, não irmã disputando rua. Na base real a mãe tem 36 km de
     * perímetro contra 9 a 17 km dos setores.
     */
    private const FATOR_AREA_MAE = 4.0;

    /**
     * Fração mínima para acusar sobreposição.
     *
     * Abaixo disto é encosto de borda — vizinhas que compartilham a divisa,
     * exatamente o que o snap do editor produz de propósito.
     */
    private const FRACAO_CONFLITO = 0.1;

    /** Teto de pontos por chamada da Roads API (limite do próprio serviço). */
    private const PONTOS_POR_CHAMADA = 100;

    /**
     * Lado da grade que estima a área sobreposta.
     *
     * 60×60 = 3.600 amostras por par, resolvendo ~3% da área — folga suficiente
     * sob o limiar de 10%. Com 19 cercas são 171 pares, meio milhão de testes
     * de ponto: roda em fração de segundo e a conferência não é gesto de tela
     * cheia, é um relatório que se abre de vez em quando.
     */
    private const AMOSTRAS_POR_LADO = 60;

    public function __construct(
        private MalhaViaria $malha,
        private AjustadorDeVia $ajustador,
    ) {}

    /**
     * Contorno do quarteirão que contém o ponto.
     *
     * @return list<array{lat:float,lng:float}>|null
     */
    public function quadra(float $lat, float $lng): ?array
    {
        $m = self::MARGEM_QUADRA;
        $vias = $this->malha->vias($lat - $m, $lng - $m, $lat + $m, $lng + $m);

        if ($vias === []) {
            return null;
        }

        return (new GrafoViario($vias))->quadraEm($lat, $lng);
    }

    /**
     * Encaixa o contorno nas ruas — a vareta mágica.
     *
     * Fecha o anel antes de mandar para a API e reabre depois: sem isso o
     * trecho entre o último e o primeiro vértice fica sem ajuste, e sobra
     * justamente ali a reta atravessando quadra.
     *
     * Devolve `null` quando não houve ajuste, para a tela poder dizer que nada
     * mudou em vez de oferecer uma "sugestão" idêntica ao que já existe.
     *
     * @param  list<array{lat:float,lng:float}>  $contorno
     * @return list<array{lat:float,lng:float}>|null
     */
    public function ajustar(array $contorno): ?array
    {
        if (count($contorno) < 3) {
            return null;
        }

        $anel = [...$contorno, $contorno[0]];
        $ajustado = [];

        // Fatia em blocos dentro do teto da API, sobrepondo um ponto entre
        // blocos: o último de um é o primeiro do seguinte, e é o que dá
        // contexto para a emenda não virar em rua transversal.
        for ($i = 0; $i < count($anel) - 1; $i += self::PONTOS_POR_CHAMADA - 1) {
            $bloco = array_slice($anel, $i, self::PONTOS_POR_CHAMADA);
            if (count($bloco) < 2) {
                break;
            }

            $trecho = $this->ajustador->ajustar($bloco);
            if ($trecho === null) {
                // Sem ajuste no bloco, preserva o original: um contorno com
                // metade encaixada e metade sumida seria pior que o de partida.
                $trecho = $bloco;
            }

            // O primeiro ponto do bloco repete o último do anterior.
            if ($ajustado !== []) {
                array_shift($trecho);
            }
            $ajustado = [...$ajustado, ...$trecho];
        }

        if (count($ajustado) < 3) {
            return null;
        }

        // Reabre o anel: o polígono do banco não repete o primeiro ponto.
        if (count($ajustado) > 3) {
            array_pop($ajustado);
        }

        return array_values($ajustado);
    }

    /**
     * Pares de cercas que disputam o mesmo território.
     *
     * Ignora contenção (cerca-mãe englobando setores) e encosto de borda
     * (divisa compartilhada) — nenhum dos dois é defeito. Sobra o que precisa
     * de decisão humana: dois setores cobrindo a mesma rua.
     *
     * @param  iterable<Cerca>  $cercas
     * @return list<array{a:int,b:int,descricao_a:string,descricao_b:string,fracao:float}>
     */
    public function conflitos(iterable $cercas): array
    {
        $lista = [];
        foreach ($cercas as $c) {
            $pontos = $c->pontos->map(
                fn ($p) => ['lat' => (float) $p->latitude, 'lng' => (float) $p->longitude]
            )->all();

            if (count($pontos) >= 3) {
                $lats = array_column($pontos, 'lat');
                $lngs = array_column($pontos, 'lng');
                $lista[] = [
                    'id' => $c->id,
                    'descricao' => $c->descricao,
                    'pontos' => $pontos,
                    // Area e caixa saem do laco: sao O(n) por cerca, e dentro do
                    // laco seriam recalculadas uma vez por PAR.
                    'area' => $this->areaRelativa($pontos),
                    'caixa' => [min($lats), min($lngs), max($lats), max($lngs)],
                ];
            }
        }

        $conflitos = [];
        $total = count($lista);

        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                $a = $lista[$i];
                $b = $lista[$j];

                // Caixas que nao se tocam nao podem ter area em comum. E o
                // descarte que paga: numa praca com setores espalhados, a
                // maioria dos pares nem chega perto, e cada par sobrevivente
                // custa 3.600 testes de ponto-em-poligono.
                if ($this->caixasSeparadas($a['caixa'], $b['caixa'])) {
                    continue;
                }

                // Área-mãe englobando setor é desenho deliberado, não conflito.
                // Comparar o TAMANHO das duas separa os dois casos: setores
                // irmãos têm ordem de grandeza parecida; um envelope de cidade
                // é muitas vezes maior que qualquer setor dentro dele.
                $maiorArea = max($a['area'], $b['area']);
                $menorArea = min($a['area'], $b['area']);

                if ($menorArea <= 0.0 || $maiorArea / $menorArea >= self::FATOR_AREA_MAE) {
                    continue;
                }

                $aEmB = $this->fracaoDentro($a['pontos'], $b['pontos']);
                $bEmA = $this->fracaoDentro($b['pontos'], $a['pontos']);

                $maior = max($aEmB, $bEmA);
                if ($maior < self::FRACAO_CONFLITO) {
                    continue;
                }

                $conflitos[] = [
                    'a' => $a['id'],
                    'b' => $b['id'],
                    'descricao_a' => $a['descricao'],
                    'descricao_b' => $b['descricao'],
                    'fracao' => round($maior, 3),
                ];
            }
        }

        // Maior disputa primeiro: é a que custa entrega errada todo dia.
        usort($conflitos, fn ($x, $y) => $y['fracao'] <=> $x['fracao']);

        return $conflitos;
    }

    /**
     * As duas caixas envolventes nao se tocam?
     *
     * @param  array{0:float,1:float,2:float,3:float}  $a  [sul, oeste, norte, leste]
     * @param  array{0:float,1:float,2:float,3:float}  $b
     */
    private function caixasSeparadas(array $a, array $b): bool
    {
        return $a[2] < $b[0] || $b[2] < $a[0] || $a[3] < $b[1] || $b[3] < $a[1];
    }

    /**
     * Área do polígono em unidades relativas (fórmula do laço).
     *
     * Serve só para comparar duas cercas entre si — quem precisa de km² usa a
     * medida esférica do editor.
     *
     * @param  list<array{lat:float,lng:float}>  $poligono
     */
    private function areaRelativa(array $poligono): float
    {
        $soma = 0.0;
        $n = count($poligono);

        for ($i = 0; $i < $n; $i++) {
            $a = $poligono[$i];
            $b = $poligono[($i + 1) % $n];
            $soma += $a['lng'] * $b['lat'] - $b['lng'] * $a['lat'];
        }

        return abs($soma / 2);
    }

    /**
     * Fração da ÁREA de $a que cai dentro de $b.
     *
     * Mede área e não vértices — e a diferença não é sutil. Duas cercas
     * vizinhas compartilham os vértices da divisa de propósito (é o que o snap
     * do editor produz para não deixar buraco entre setores), e um vértice em
     * cima da linha conta como "dentro" pela regra do raio. Contando vértices,
     * duas cercas encostadas com ZERO de área comum acusavam 25% de
     * sobreposição — ou seja, todo par bem desenhado viraria alarme falso.
     *
     * A estimativa é por amostragem numa grade: calcular interseção exata de
     * polígonos exigiria um clipper completo, e aqui basta saber se a disputa é
     * relevante. A grade de 60×60 resolve ~3% da área, bem abaixo do limiar de
     * 10% que aciona o aviso.
     *
     * @param  list<array{lat:float,lng:float}>  $a
     * @param  list<array{lat:float,lng:float}>  $b
     */
    private function fracaoDentro(array $a, array $b): float
    {
        $lats = array_column($a, 'lat');
        $lngs = array_column($a, 'lng');
        $sul = min($lats);
        $norte = max($lats);
        $oeste = min($lngs);
        $leste = max($lngs);

        if ($norte <= $sul || $leste <= $oeste) {
            return 0.0;
        }

        $lado = self::AMOSTRAS_POR_LADO;
        $dentroDeA = 0;
        $dentroDeAmbas = 0;

        for ($i = 0; $i < $lado; $i++) {
            // Meio da célula (o +0,5): amostrar na borda cairia justamente em
            // cima das divisas e traria de volta o problema do vértice.
            $lat = $sul + ($norte - $sul) * (($i + 0.5) / $lado);

            for ($j = 0; $j < $lado; $j++) {
                $lng = $oeste + ($leste - $oeste) * (($j + 0.5) / $lado);

                if (! GrafoViario::contem($a, $lat, $lng)) {
                    continue;
                }
                $dentroDeA++;

                if (GrafoViario::contem($b, $lat, $lng)) {
                    $dentroDeAmbas++;
                }
            }
        }

        return $dentroDeA > 0 ? $dentroDeAmbas / $dentroDeA : 0.0;
    }
}
