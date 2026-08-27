<?php

namespace App\Domain\Satelite;

use App\Models\Cliente\Cliente;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\ComodatoAvaliacao;
use App\Models\Satelite\ComodatoConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vigilância do comodato: o vasilhame emprestado está rodando?
 *
 * **A suspeita que originou isto.** Cliente com comodato grande pode estar
 * enchendo o botijão da revenda na concorrência. O vasilhame é patrimônio caro
 * parado na casa de alguém; se ele não volta para reabastecer aqui, ou virou
 * ativo ocioso, ou está financiando o concorrente.
 *
 * **A métrica.** GIRO = quanto o cliente comprou do produto compatível na janela
 * ÷ vasilhames em poder dele. Mede quantas vezes cada casco emprestado foi
 * reabastecido. Medido em produção:
 *
 *     BRASILCOMP    12 vasilhames, 300 compras/180d → 25,0x
 *     REPINHO       48 vasilhames, 1077            → 22,4x
 *     RESIDENCIAL   25 vasilhames,   92            →  3,7x
 *     MULTI PLY     13 vasilhames,   17            →  1,3x
 *     J ALEI        60 vasilhames,    0            →  0,0x  (parado desde 2022)
 *
 * **Por que a régua é adaptativa.** Hospital, condomínio, restaurante e
 * revendedor consomem em ritmos incomparáveis. Um corte fixo puniria quem sempre
 * consumiu pouco e — pior — deixaria passar quem caiu de 20x para 8x, que é o
 * sinal real de desvio. Por isso cada cliente é medido contra o SEU PRÓPRIO
 * histórico (`baseline_giro`), com um piso absoluto para quem não tem histórico.
 *
 * **O que este serviço NÃO faz.** Não acusa ninguém. Desvio de vasilhame não se
 * prova por estatística: um cliente pode ter trocado de fornecedor legitimamente,
 * fechado o negócio, ou entrado em férias coletivas. O serviço produz uma FILA
 * DE AVERIGUAÇÃO ordenada por risco patrimonial — quem a equipe visita primeiro.
 */
class VigilanciaComodatoService
{
    public function __construct(private VinculoVasilhame $vinculo)
    {
    }

    /**
     * Avalia todos os clientes com comodato ativo de uma empresa.
     *
     * @return list<ComodatoAvaliacao>
     */
    public function avaliarEmpresa(int $empresaId, ?Carbon $referencia = null): array
    {
        $config = ComodatoConfig::daEmpresa($empresaId);
        $referencia ??= now();

        // Catálogo da empresa avaliada, não o global: o produto que enche o
        // vasilhame de um tenant nunca é o de outro, e carregar todos só
        // convidaria o par a cruzar a fronteira.
        $catalogo = Produto::query()
            ->where('ativo', true)
            ->where('empresa_id', $empresaId)
            ->get();
        $resultado = [];

        foreach ($this->clientesComComodato($empresaId, $config) as $linha) {
            $avaliacao = $this->avaliarCliente($linha, $config, $catalogo, $referencia);

            if ($avaliacao !== null) {
                $resultado[] = $avaliacao;
            }
        }

        return $resultado;
    }

    /**
     * Clientes com posse relevante, já filtrados.
     *
     * **Fornecedor fica de fora.** A distribuidora aparece com 5.633 vasilhames
     * em comodato — mas é o comodato DELA para a revenda, direção oposta.
     * Alertar sobre ele seria o maior falso positivo da base.
     *
     * @return Collection<int,object>
     */
    private function clientesComComodato(int $empresaId, ComodatoConfig $config): Collection
    {
        // Agregado em PHP e não em SQL porque `array_agg` é do Postgres e a
        // suíte roda em sqlite — ver CLAUDE.md. O volume é pequeno por
        // natureza: são os clientes COM comodato, não a base inteira.
        $linhas = DB::table('comodatos as c')
            ->join('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->where('c.empresa_id', $empresaId)
            ->whereIn('c.situacao', ['ATIVO', 'PARCIAL'])
            // O que decide é o SENTIDO do comodato, não a flag `fornecedor` do
            // cadastro. Medido em produção: 42 comodatos estavam fora da
            // vigilância por essa flag, e 38 daqueles clientes compraram no
            // último ano — são clientes comuns que um dia emitiram nota para a
            // revenda (`RESIDENCIAL VÓ ZULMA`, `EDIFÍCIO MILANO`, restaurantes).
            // Filtrar por ela cegava a vigilância para 6.255 vasilhames.
            ->where('c.sentido', Comodato::CONCEDIDO)
            ->get(['c.cliente_id', 'c.produto_id', 'c.quantidade', 'c.quantidade_devolvida', 'c.data_emprestimo']);

        return $linhas
            ->groupBy('cliente_id')
            ->map(fn (Collection $g, $clienteId) => (object) [
                'cliente_id' => (int) $clienteId,
                'em_posse' => round($g->sum(fn ($r) => (float) $r->quantidade - (float) $r->quantidade_devolvida), 3),
                'desde' => $g->min('data_emprestimo'),
                'produtos' => $g->pluck('produto_id')->unique()->values()->all(),
            ])
            ->filter(fn ($r) => $r->em_posse >= (float) $config->posse_minima_vigiada)
            ->values();
    }

    /**
     * @param  Collection<int,Produto>  $catalogo
     */
    private function avaliarCliente(
        object $linha,
        ComodatoConfig $config,
        Collection $catalogo,
        Carbon $referencia,
    ): ?ComodatoAvaliacao {
        $cliente = Cliente::query()->find($linha->cliente_id);

        if ($cliente === null) {
            return null;
        }

        $emPosse = (float) $linha->em_posse;
        $compraveis = $this->idsCompraveis($linha->produtos, $catalogo);

        $janela = $this->consumo($cliente->id, $compraveis, $referencia->copy()->subDays($config->dias_janela), $referencia);

        // Baseline: os 12 meses ANTERIORES à janela, do próprio cliente. É o que
        // transforma "compra pouco" em "comprava mais e parou".
        $baseFim = $referencia->copy()->subDays($config->dias_janela);
        $baseInicio = $baseFim->copy()->subDays(365);
        $base = $this->consumo($cliente->id, $compraveis, $baseInicio, $baseFim);

        $giro = $emPosse > 0 ? round($janela['quantidade'] / $emPosse, 3) : 0.0;

        // Baseline normalizado para a mesma duração da janela — comparar 365
        // dias com 180 diria que todo mundo caiu pela metade.
        $baselineGiro = null;
        if ($base['pedidos'] >= 3 && $emPosse > 0) {
            $porDia = $base['quantidade'] / 365;
            $baselineGiro = round(($porDia * $config->dias_janela) / $emPosse, 3);
        }

        $variacao = ($baselineGiro !== null && $baselineGiro > 0)
            ? round((($baselineGiro - $giro) / $baselineGiro) * 100, 3)
            : null;

        $diasSemCompra = $janela['ultima'] !== null
            ? (int) Carbon::parse($janela['ultima'])->diffInDays($referencia)
            : null;

        [$classificacao, $motivo] = $this->classificar(
            $giro, $baselineGiro, $variacao, $diasSemCompra, $janela['pedidos'], $emPosse, $config,
        );

        return ComodatoAvaliacao::updateOrCreate(
            ['cliente_id' => $cliente->id, 'referencia' => $referencia->toDateString()],
            [
                'empresa_id' => $cliente->empresa_id,
                'grupo_id' => $cliente->grupo_id,
                'em_posse' => $emPosse,
                'comprado_janela' => $janela['quantidade'],
                'dias_janela' => $config->dias_janela,
                'pedidos_janela' => $janela['pedidos'],
                'giro' => $giro,
                'baseline_giro' => $baselineGiro,
                'variacao' => $variacao,
                'dias_sem_compra' => $diasSemCompra,
                'classificacao' => $classificacao,
                'motivo' => $motivo,
            ],
        );
    }

    /**
     * Ids de produto que contam como reabastecimento dos vasilhames em posse.
     *
     * @param  mixed  $produtosDoComodato  array Postgres ("{98,299}")
     * @return list<int>
     */
    private function idsCompraveis(mixed $produtosDoComodato, Collection $catalogo): array
    {
        $ids = is_string($produtosDoComodato)
            ? array_filter(explode(',', trim($produtosDoComodato, '{}')), 'strlen')
            : (array) $produtosDoComodato;

        $compraveis = [];

        foreach ($ids as $produtoId) {
            $vasilhame = $catalogo->firstWhere('id', (int) $produtoId);

            if ($vasilhame === null) {
                continue;
            }

            $compraveis = array_merge($compraveis, $this->vinculo->idsDeCompra($vasilhame, $catalogo));
        }

        return array_values(array_unique($compraveis));
    }

    /**
     * Consumo do cliente num intervalo.
     *
     * @param  list<int>  $produtos
     * @return array{quantidade:float, pedidos:int, ultima:?string}
     */
    private function consumo(int $clienteId, array $produtos, Carbon $de, Carbon $ate): array
    {
        if ($produtos === []) {
            return ['quantidade' => 0.0, 'pedidos' => 0, 'ultima' => null];
        }

        // Query builder, não SQL cru: `::date` do Postgres não existe em sqlite.
        $r = DB::table('pedidoitens as i')
            ->join('pedidos as p', 'p.id', '=', 'i.pedido_id')
            ->where('p.cliente_id', $clienteId)
            ->where('p.datahora', '>=', $de->toDateTimeString())
            ->where('p.datahora', '<', $ate->toDateTimeString())
            ->whereIn('i.produto_id', $produtos)
            ->selectRaw('coalesce(sum(i.quantidade), 0) as quantidade')
            ->selectRaw('count(distinct p.id) as pedidos')
            ->selectRaw('max(p.datahora) as ultima')
            ->first();

        return [
            'quantidade' => (float) ($r->quantidade ?? 0),
            'pedidos' => (int) ($r->pedidos ?? 0),
            'ultima' => $r->ultima ?? null,
        ];
    }

    /**
     * O veredito e o porquê.
     *
     * A ordem importa: o caso mais grave vence, e o motivo devolvido é o que a
     * equipe vai ler antes de bater na porta do cliente.
     *
     * @return array{0:string, 1:string}
     */
    private function classificar(
        float $giro,
        ?float $baseline,
        ?float $variacao,
        ?int $diasSemCompra,
        int $pedidos,
        float $emPosse,
        ComodatoConfig $config,
    ): array {
        $qtd = $this->num($emPosse);

        // Nunca comprou nada na janela com vasilhame na mão. É o caso J ALEI:
        // 60 vasilhames, zero compras desde 2022.
        if ($pedidos === 0) {
            return ['CRITICO', "{$qtd} vasilhame(s) em poder do cliente e nenhuma compra em {$config->dias_janela} dias."];
        }

        if ($diasSemCompra !== null && $diasSemCompra >= $config->dias_sem_compra_alerta) {
            $nivel = $diasSemCompra >= $config->dias_sem_compra_alerta * 2 ? 'CRITICO' : 'ATENCAO';

            return [$nivel, "Sem comprar há {$diasSemCompra} dias, com {$qtd} vasilhame(s) em poder do cliente."];
        }

        // Queda contra o próprio histórico: o sinal mais forte que existe aqui,
        // porque o cliente é comparado consigo mesmo.
        if ($variacao !== null && $variacao >= $config->queda_critica) {
            return ['CRITICO', sprintf(
                'Compra caiu %s%% ante o próprio histórico (giro %s contra %s habitual).',
                $this->num($variacao), $this->num($giro), $this->num((float) $baseline),
            )];
        }

        if ($giro <= $config->giro_critico) {
            return ['CRITICO', sprintf(
                'Giro de %sx: %s vasilhame(s) renderam apenas %s recarga(s) em %d dias.',
                $this->num($giro), $qtd, $this->num($giro * $emPosse), $config->dias_janela,
            )];
        }

        if ($variacao !== null && $variacao >= $config->queda_atencao) {
            return ['ATENCAO', sprintf(
                'Compra caiu %s%% ante o próprio histórico (giro %s contra %s habitual).',
                $this->num($variacao), $this->num($giro), $this->num((float) $baseline),
            )];
        }

        if ($giro < $config->giro_minimo) {
            return ['ATENCAO', sprintf(
                'Giro de %sx está abaixo do mínimo de %sx para %s vasilhame(s).',
                $this->num($giro), $this->num((float) $config->giro_minimo), $qtd,
            )];
        }

        return ['OK', sprintf('Giro de %sx em %d dias — proporcional aos %s vasilhame(s).',
            $this->num($giro), $config->dias_janela, $qtd)];
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }
}
