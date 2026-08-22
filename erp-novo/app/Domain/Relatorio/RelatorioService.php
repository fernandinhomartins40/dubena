<?php

namespace App\Domain\Relatorio;

use App\Domain\Rh\ComissaoService;
use App\Models\Rh\ColaboradorComissao;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RelatorioService (N12) — relatórios como Query Services PARAMETRIZADOS, com
 * agregação no SQL (sem N+1, sem SQLi, sem TO_CHAR Oracle). Escopo por empresa
 * passado explicitamente (estes métodos rodam fora do request, em export/cron).
 */
class RelatorioService
{
    public function __construct(private ComissaoService $comissao) {}

    /**
     * Expressão SQL para "dia do mês" de uma coluna de data, no dialeto do driver
     * ativo. Evita `strftime` (SQLite-only). Usado em ordenação de aniversariantes.
     */
    private function diaDoMesSql(string $coluna): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => "extract(day from {$coluna})",
            'sqlite' => "cast(strftime('%d', {$coluna}) as integer)",
            default => "day({$coluna})", // mysql/mariadb
        };
    }

    /**
     * Vendas por período: total e contagem por dia (pedidos concretizados).
     *
     * @return array{periodo:array{inicio:string,fim:string}, total:float, quantidade:int, por_dia:array<int,array{dia:string,total:float,quantidade:int}>}
     */
    public function vendas(int $empresaId, string $inicio, string $fim): array
    {
        $dtInicio = Carbon::parse($inicio)->startOfDay();
        $dtFim = Carbon::parse($fim)->endOfDay();

        $base = DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->where('estoque_movimentado', true)
            ->whereBetween('datahora', [$dtInicio, $dtFim]);

        $porDia = (clone $base)
            ->selectRaw('date(datahora) as dia, sum(valor_venda) as total, count(*) as quantidade')
            ->groupByRaw('date(datahora)')
            ->orderByRaw('date(datahora)')
            ->get()
            ->map(fn ($r) => ['dia' => (string) $r->dia, 'total' => round((float) $r->total, 2), 'quantidade' => (int) $r->quantidade])
            ->all();

        return [
            'periodo' => ['inicio' => $dtInicio->toDateString(), 'fim' => $dtFim->toDateString()],
            'total' => round((float) (clone $base)->sum('valor_venda'), 2),
            'quantidade' => (int) (clone $base)->count(),
            'por_dia' => $porDia,
        ];
    }

    /**
     * Resumo do dashboard (contadores rápidos da operação). Escopo por empresa
     * (clientes/pedidos/financeiro) e por grupo (produtos). Tudo COUNT no SQL.
     *
     * @return array{clientes:int, produtos:int, pedidos:int, financeiro:int}
     */
    public function dashboardResumo(int $empresaId): array
    {
        return [
            'clientes' => (int) DB::table('clientes')->where('empresa_id', $empresaId)->count(),
            'produtos' => (int) DB::table('produtos')->where('empresa_id', $empresaId)->count(),
            'pedidos' => (int) DB::table('pedidos')->where('empresa_id', $empresaId)->count(),
            // "financeiro" no card = títulos a receber em aberto.
            'financeiro' => (int) DB::table('financeiroparcelas as fp')
                ->join('financeiros as f', 'f.id', '=', 'fp.financeiro_id')
                ->where('f.empresa_id', $empresaId)
                ->where('f.cancelado', false)
                ->where('f.pagarreceber', 'R')
                ->where('fp.baixado', false)
                ->count(),
        ];
    }

    /**
     * Posição financeira: a receber/a pagar em aberto e vencido por período.
     *
     * @return array<string,float|int>
     */
    public function financeiro(int $empresaId, string $inicio, string $fim): array
    {
        $dtInicio = Carbon::parse($inicio)->toDateString();
        $dtFim = Carbon::parse($fim)->toDateString();

        $parcelas = DB::table('financeiroparcelas as fp')
            ->join('financeiros as f', 'f.id', '=', 'fp.financeiro_id')
            ->where('f.empresa_id', $empresaId)
            ->where('f.cancelado', false)
            ->whereBetween('fp.vencimento', [$dtInicio, $dtFim]);

        $aReceber = (clone $parcelas)->where('f.pagarreceber', 'R')->where('fp.baixado', false)->sum('fp.valor');
        $aPagar = (clone $parcelas)->where('f.pagarreceber', 'P')->where('fp.baixado', false)->sum('fp.valor');
        $recebido = (clone $parcelas)->where('f.pagarreceber', 'R')->where('fp.baixado', true)->sum('fp.valor_efetivado');
        $pago = (clone $parcelas)->where('f.pagarreceber', 'P')->where('fp.baixado', true)->sum('fp.valor_efetivado');

        return [
            'a_receber' => round((float) $aReceber, 2),
            'a_pagar' => round((float) $aPagar, 2),
            'recebido' => round((float) $recebido, 2),
            'pago' => round((float) $pago, 2),
            'saldo_previsto' => round((float) $aReceber - (float) $aPagar, 2),
        ];
    }

    /**
     * DRE simplificado do período: receita de vendas, recebido/pago, resultado.
     * (A DRE contábil completa por plano de contas entra por extensão.)
     *
     * @return array<string,float>
     */
    public function dre(int $empresaId, string $inicio, string $fim): array
    {
        $dtInicio = Carbon::parse($inicio)->toDateString();
        $dtFim = Carbon::parse($fim)->toDateString();

        // Receitas/despesas REALIZADAS no período, agrupadas por plano de conta
        // (parcelas baixadas). Shape consumido pela SPA: receitas[]/despesas[]
        // com {plano,total} + totais + resultado.
        $agrupar = function (string $pagarReceber) use ($empresaId, $dtInicio, $dtFim) {
            return DB::table('financeiroparcelas as fp')
                ->join('financeiros as f', 'f.id', '=', 'fp.financeiro_id')
                ->leftJoin('planos_conta as pc', 'pc.id', '=', 'f.planoconta_id')
                ->where('f.empresa_id', $empresaId)
                ->where('f.cancelado', false)
                ->where('f.pagarreceber', $pagarReceber)
                ->where('fp.baixado', true)
                ->whereBetween('fp.datahora_baixa', [$dtInicio.' 00:00:00', $dtFim.' 23:59:59'])
                ->groupBy('pc.descricao')
                ->selectRaw('coalesce(pc.descricao, ?) as plano, sum(fp.valor_efetivado) as total', ['Sem plano'])
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => ['plano' => (string) $r->plano, 'total' => round((float) $r->total, 2)])
                ->all();
        };

        $receitas = $agrupar('R');
        $despesas = $agrupar('P');
        $totalReceitas = round(array_sum(array_column($receitas, 'total')), 2);
        $totalDespesas = round(array_sum(array_column($despesas, 'total')), 2);

        return [
            'receitas' => $receitas,
            'despesas' => $despesas,
            'total_receitas' => $totalReceitas,
            'total_despesas' => $totalDespesas,
            'resultado' => round($totalReceitas - $totalDespesas, 2),
        ];
    }

    /**
     * Estoque atual: itens abaixo do mínimo (reposição) por setor.
     *
     * @return list<array<string,mixed>>
     */
    public function estoqueBaixo(int $empresaId): array
    {
        return DB::table('estoquesaldos as es')
            ->join('produtos as p', 'p.id', '=', 'es.produto_id')
            ->join('setores as s', 's.id', '=', 'es.setor_id')
            ->where('es.empresa_id', $empresaId)
            ->whereNotNull('es.quantidade_minima')
            ->whereColumn('es.quantidade', '<', 'es.quantidade_minima')
            ->select('p.descricao as produto', 's.descricao as setor', 'es.quantidade', 'es.quantidade_minima')
            ->orderBy('p.descricao')
            ->get()
            ->map(fn ($r) => [
                'produto' => $r->produto,
                'setor' => $r->setor,
                'quantidade' => (float) $r->quantidade,
                'quantidade_minima' => (float) $r->quantidade_minima,
            ])->all();
    }

    /**
     * Fechamentos de caixa no período (saldo inicial/final por conta).
     *
     * @return list<array<string,mixed>>
     */
    public function fechamentosCaixa(int $empresaId, string $inicio, string $fim): array
    {
        return DB::table('contafechamentos as cf')
            ->join('contas as c', 'c.id', '=', 'cf.conta_id')
            ->where('cf.empresa_id', $empresaId)
            ->whereBetween('cf.abertura', [Carbon::parse($inicio)->startOfDay(), Carbon::parse($fim)->endOfDay()])
            ->select('c.descricao as conta', 'cf.abertura', 'cf.fechamento', 'cf.saldo_inicial', 'cf.saldo_final', 'cf.aberto')
            ->orderByDesc('cf.abertura')
            ->get()
            ->map(fn ($r) => [
                'conta' => $r->conta,
                'abertura' => (string) $r->abertura,
                'fechamento' => $r->fechamento ? (string) $r->fechamento : null,
                'saldo_inicial' => round((float) $r->saldo_inicial, 2),
                'saldo_final' => $r->saldo_final !== null ? round((float) $r->saldo_final, 2) : null,
                'aberto' => (bool) $r->aberto,
            ])->all();
    }

    /**
     * Converte linhas (array de arrays associativos) em CSV (separador ';' — Excel
     * BR). Cabeçalho a partir das chaves da primeira linha.
     *
     * @param  list<array<string,mixed>>  $linhas
     */
    public function csv(array $linhas): string
    {
        if ($linhas === []) {
            return '';
        }

        $saida = fopen('php://temp', 'r+');
        fputcsv($saida, array_keys($linhas[0]), ';');
        foreach ($linhas as $linha) {
            fputcsv($saida, array_map(fn ($v) => is_bool($v) ? ($v ? '1' : '0') : $v, $linha), ';');
        }
        rewind($saida);
        $csv = (string) stream_get_contents($saida);
        fclose($saida);

        return $csv;
    }

    /**
     * Gera um PDF tabular (dompdf) a partir de linhas associativas. Cabeçalho =
     * chaves da 1ª linha. Retorna os bytes do PDF.
     *
     * @param  list<array<string,mixed>>  $linhas
     */
    public function pdf(array $linhas, string $titulo): string
    {
        $cols = $linhas === [] ? [] : array_keys($linhas[0]);
        $th = implode('', array_map(fn ($c) => '<th>'.e((string) $c).'</th>', $cols));
        $trs = '';
        foreach ($linhas as $l) {
            $tds = implode('', array_map(fn ($c) => '<td>'.e((string) ($l[$c] ?? '')).'</td>', $cols));
            $trs .= "<tr>{$tds}</tr>";
        }
        $html = '<html><head><meta charset="utf-8"><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#1e293b}'
            .'h1{font-size:16px;margin:0 0 12px}table{width:100%;border-collapse:collapse}'
            .'th,td{border:1px solid #cbd5e1;padding:5px 7px;text-align:left}'
            .'th{background:#2a54ad;color:#fff;font-size:10px;text-transform:uppercase}'
            .'tr:nth-child(even){background:#f1f5f9}</style></head><body>'
            ."<h1>{$titulo}</h1>"
            .($linhas === [] ? '<p>Sem dados no período.</p>' : "<table><thead><tr>{$th}</tr></thead><tbody>{$trs}</tbody></table>")
            .'</body></html>';

        return Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output();
    }

    // ───────────────────── Relatórios adicionais (C8) ─────────────────────

    /**
     * Clientes aniversariantes do mês (1–12). Usa clientes.datanascimento.
     *
     * @return list<array<string,mixed>>
     */
    public function clientesAniversariantes(int $empresaId, int $mes): array
    {
        // Filtro/ordenação por mês e dia do aniversário de forma AGNÓSTICA de banco:
        // `whereMonth`/`orderByRaw(day(...))` do query builder traduzem para a função
        // de data do driver ativo (Postgres/MySQL/SQLite) — sem `strftime` (SQLite-only).
        return DB::table('clientes')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('datanascimento')
            ->whereMonth('datanascimento', $mes)
            ->orderByRaw($this->diaDoMesSql('datanascimento'))
            ->get(['nome', 'datanascimento', 'cpf'])
            ->map(fn ($r) => [
                'nome' => $r->nome,
                'nascimento' => (string) $r->datanascimento,
                'cpf' => $r->cpf,
            ])->all();
    }

    /**
     * Vale-gás por situação (emitidos/utilizados/cancelados) no período.
     *
     * @return list<array<string,mixed>>
     */
    public function valeGas(int $empresaId, string $inicio, string $fim): array
    {
        return DB::table('vale_gas')
            ->where('empresa_id', $empresaId)
            ->whereBetween('created_at', [Carbon::parse($inicio)->startOfDay(), Carbon::parse($fim)->endOfDay()])
            ->groupBy('situacao')
            ->selectRaw('situacao, count(*) as quantidade, sum(valor) as total')
            ->get()
            ->map(fn ($r) => ['situacao' => $r->situacao, 'quantidade' => (int) $r->quantidade, 'total' => round((float) $r->total, 2)])
            ->all();
    }

    /**
     * Comodatos em aberto (vasilhame emprestado não devolvido).
     *
     * @return list<array<string,mixed>>
     */
    public function comodatos(int $empresaId): array
    {
        return DB::table('comodatos as c')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->leftJoin('produtos as p', 'p.id', '=', 'c.produto_id')
            ->where('c.empresa_id', $empresaId)
            ->where('c.situacao', '!=', 'DEVOLVIDO')
            ->get(['cl.nome as cliente', 'p.descricao as produto', 'c.quantidade', 'c.quantidade_devolvida', 'c.situacao', 'c.data_emprestimo'])
            ->map(fn ($r) => [
                'cliente' => $r->cliente,
                'produto' => $r->produto,
                'pendente' => (float) $r->quantidade - (float) $r->quantidade_devolvida,
                'situacao' => $r->situacao,
                'desde' => (string) $r->data_emprestimo,
            ])->all();
    }

    /**
     * Comissões por colaborador no período (pedidos concluídos × regra de comissão).
     * Visão consolidada; o cálculo fino por item está no ComissaoService.
     *
     * @return list<array<string,mixed>>
     */
    /**
     * Comissão REAL por colaborador no período (F10): aplica a matemática fina do
     * ComissaoService (percentual/repasse, exceção por segmento, app×balcão) sobre
     * os itens dos pedidos concretizados de cada entregador — em vez da média
     * simplificada de % das regras (que a auditoria apontou como divergente §5).
     *
     * @return list<array<string,mixed>>
     */
    public function comissoes(int $empresaId, string $inicio, string $fim): array
    {
        $dtInicio = Carbon::parse($inicio)->startOfDay();
        $dtFim = Carbon::parse($fim)->endOfDay();

        // Itens vendidos no período por entregador (pedidos concretizados).
        $itens = DB::table('pedidoitens as pi')
            ->join('pedidos as p', 'p.id', '=', 'pi.pedido_id')
            ->where('p.empresa_id', $empresaId)
            ->where('p.estoque_movimentado', true)
            ->whereNotNull('p.entregador_user_id')
            ->whereBetween('p.datahora', [$dtInicio, $dtFim])
            ->selectRaw('p.entregador_user_id, p.setor_id, pi.produto_id, pi.quantidade, pi.preco_unitario,
                         pi.valor_total as valor_venda, pi.desconto as valor_desconto')
            ->get();

        if ($itens->isEmpty()) {
            return [];
        }

        // Regras de comissão da empresa (com exceções), indexadas p/ casamento.
        $regras = ColaboradorComissao::withoutTenant()
            ->where('empresa_id', $empresaId)->where('ativo', true)
            ->with('excecoes')
            ->get();

        // Nome do colaborador a partir do user_id do entregador.
        $nomes = DB::table('colaboradores')->where('empresa_id', $empresaId)
            ->pluck('nome', 'user_id');

        // Agrupa itens por entregador, casando cada item com a regra (produto+setor).
        $porEntregador = [];
        foreach ($itens as $it) {
            $regra = $regras->first(fn ($r) => ($r->produto_id === null || (int) $r->produto_id === (int) $it->produto_id)
                && ($r->setor_id === null || (int) $r->setor_id === (int) $it->setor_id));
            if (! $regra) {
                continue;
            }
            $porEntregador[$it->entregador_user_id][] = [[
                'quantidade' => $it->quantidade,
                'preco_unitario' => $it->preco_unitario,
                'valor_venda' => $it->valor_venda,
                'valor_desconto' => $it->valor_desconto,
            ], $regra];
        }

        $linhas = [];
        foreach ($porEntregador as $userId => $itensComRegra) {
            $total = $this->comissao->totalColaborador($itensComRegra);
            $linhas[] = [
                'colaborador' => $nomes[$userId] ?? "user {$userId}",
                'itens' => count($itensComRegra),
                'comissao_percentual' => $total['percentual'],
                'comissao_repasse' => $total['repasse'],
                'comissao_total' => $total['total'],
            ];
        }

        usort($linhas, fn ($a, $b) => $b['comissao_total'] <=> $a['comissao_total']);

        return $linhas;
    }

    /**
     * Movimentação de caixa no período (entradas/saídas por conta).
     *
     * @return list<array<string,mixed>>
     */
    public function movimentacaoCaixa(int $empresaId, string $inicio, string $fim): array
    {
        return DB::table('contamovimentos as cm')
            ->join('contas as c', 'c.id', '=', 'cm.conta_id')
            ->where('c.empresa_id', $empresaId)
            ->whereBetween('cm.datahora', [Carbon::parse($inicio)->startOfDay(), Carbon::parse($fim)->endOfDay()])
            ->groupBy('c.descricao')
            ->selectRaw('c.descricao as conta, '
                .'sum(case when cm.valor > 0 then cm.valor else 0 end) as entradas, '
                .'sum(case when cm.valor < 0 then -cm.valor else 0 end) as saidas')
            ->get()
            ->map(fn ($r) => [
                'conta' => $r->conta,
                'entradas' => round((float) $r->entradas, 2),
                'saidas' => round((float) $r->saidas, 2),
                'saldo' => round((float) $r->entradas - (float) $r->saidas, 2),
            ])->all();
    }

    // ───────────────────── Relatórios adicionais (F10) ─────────────────────

    /** Vendas por entregador no período (pedidos concretizados). */
    public function vendasPorEntregador(int $empresaId, string $inicio, string $fim): array
    {
        [$di, $df] = [Carbon::parse($inicio)->startOfDay(), Carbon::parse($fim)->endOfDay()];

        return DB::table('pedidos as p')
            ->leftJoin('colaboradores as co', 'co.user_id', '=', 'p.entregador_user_id')
            ->where('p.empresa_id', $empresaId)->where('p.estoque_movimentado', true)
            ->whereBetween('p.datahora', [$di, $df])
            ->groupByRaw('p.entregador_user_id, co.nome')
            ->selectRaw('coalesce(co.nome, \'(sem entregador)\') as entregador, count(p.id) as pedidos, sum(p.valor_venda) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['entregador' => $r->entregador, 'pedidos' => (int) $r->pedidos, 'total' => round((float) $r->total, 2)])
            ->all();
    }

    /** Vendas por operação (PDV/Disk/convênio) no período. */
    public function vendasPorOperacao(int $empresaId, string $inicio, string $fim): array
    {
        [$di, $df] = [Carbon::parse($inicio)->startOfDay(), Carbon::parse($fim)->endOfDay()];

        return DB::table('pedidos as p')
            ->leftJoin('pedidooperacoes as op', 'op.id', '=', 'p.pedidooperacao_id')
            ->where('p.empresa_id', $empresaId)->where('p.estoque_movimentado', true)
            ->whereBetween('p.datahora', [$di, $df])
            ->groupByRaw('p.pedidooperacao_id, op.descricao')
            ->selectRaw('coalesce(op.descricao, \'(sem operação)\') as operacao, count(p.id) as pedidos, sum(p.valor_venda) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['operacao' => $r->operacao, 'pedidos' => (int) $r->pedidos, 'total' => round((float) $r->total, 2)])
            ->all();
    }

    /** Vendas por produto (quantidade e valor) no período. */
    public function vendasPorProduto(int $empresaId, string $inicio, string $fim): array
    {
        [$di, $df] = [Carbon::parse($inicio)->startOfDay(), Carbon::parse($fim)->endOfDay()];

        return DB::table('pedidoitens as pi')
            ->join('pedidos as p', 'p.id', '=', 'pi.pedido_id')
            ->leftJoin('produtos as pr', 'pr.id', '=', 'pi.produto_id')
            ->where('p.empresa_id', $empresaId)->where('p.estoque_movimentado', true)
            ->whereBetween('p.datahora', [$di, $df])
            ->groupByRaw('pi.produto_id, pr.descricao')
            ->selectRaw('coalesce(pr.descricao, \'?\') as produto, sum(pi.quantidade) as quantidade, sum(pi.valor_total) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['produto' => $r->produto, 'quantidade' => round((float) $r->quantidade, 3), 'total' => round((float) $r->total, 2)])
            ->all();
    }

    /** NF-e emitidas (autorizadas) no período. */
    public function nfEmitidas(int $empresaId, string $inicio, string $fim): array
    {
        [$di, $df] = [Carbon::parse($inicio)->startOfDay(), Carbon::parse($fim)->endOfDay()];

        return DB::table('notas_fiscais as n')
            ->leftJoin('clientes as c', 'c.id', '=', 'n.cliente_id')
            ->where('n.empresa_id', $empresaId)
            ->whereBetween('n.emitida_em', [$di, $df])
            ->orderBy('n.numero')
            ->get(['n.numero', 'n.serie', 'n.modelo', 'n.chave', 'n.situacao', 'n.valor_total', 'n.emitida_em', 'c.nome as cliente'])
            ->map(fn ($r) => [
                'numero' => $r->numero, 'serie' => $r->serie, 'modelo' => $r->modelo,
                'cliente' => $r->cliente, 'situacao' => $r->situacao,
                'valor_total' => round((float) $r->valor_total, 2),
                'emitida_em' => (string) $r->emitida_em,
            ])->all();
    }

    /** NF de entrada (recebidas) no período. */
    public function nfRecebidas(int $empresaId, string $inicio, string $fim): array
    {
        return DB::table('nf_recebidas')
            ->where('empresa_id', $empresaId)
            ->whereBetween('data_emissao', [$inicio, $fim])
            ->orderBy('data_emissao')
            ->get(['numero', 'serie', 'emitente_nome', 'data_emissao', 'valor_total', 'situacao'])
            ->map(fn ($r) => [
                'numero' => $r->numero, 'serie' => $r->serie, 'emitente' => $r->emitente_nome,
                'data_emissao' => (string) $r->data_emissao, 'valor_total' => round((float) $r->valor_total, 2),
                'situacao' => $r->situacao,
            ])->all();
    }

    /** Promoções do grupo e adesão (clientes por promoção). */
    public function promocoes(int $empresaId): array
    {
        $grupoId = (int) DB::table('empresas')->where('id', $empresaId)->value('grupo_id');

        return DB::table('promocoes')
            ->where('grupo_id', $grupoId)
            ->orderByDesc('inicio')
            ->get(['descricao', 'inicio', 'fim', 'desconto_percentual', 'ativo'])
            ->map(fn ($r) => [
                'promocao' => $r->descricao,
                'inicio' => (string) $r->inicio, 'fim' => (string) $r->fim,
                'desconto_percentual' => round((float) $r->desconto_percentual, 2),
                'ativa' => (bool) $r->ativo ? 'Sim' : 'Não',
            ])->all();
    }

    /** Frota: veículos e total abastecido (litros/valor). */
    public function veiculos(int $empresaId): array
    {
        return DB::table('veiculos as v')
            ->leftJoin('veiculo_abastecimentos as a', 'a.veiculo_id', '=', 'v.id')
            ->where('v.empresa_id', $empresaId)
            ->groupByRaw('v.id, v.placa, v.descricao, v.km_atual')
            ->selectRaw('v.placa, v.descricao, v.km_atual, coalesce(sum(a.litros),0) as litros, coalesce(sum(a.valor_total),0) as valor')
            ->orderBy('v.placa')
            ->get()
            ->map(fn ($r) => [
                'placa' => $r->placa, 'veiculo' => $r->descricao, 'km_atual' => (int) $r->km_atual,
                'litros' => round((float) $r->litros, 3), 'valor_abastecido' => round((float) $r->valor, 2),
            ])->all();
    }

    // ────────────── Relatórios PRÉ-GO-LIVE (triagem F4, §5) ──────────────

    /**
     * Fluxo de caixa PROJETADO, dia a dia.
     *
     * Por que não bastava o relatório `financeiro` que já existia: aquele dá a
     * POSIÇÃO do período (total a receber, total a pagar, saldo previsto). O
     * fluxo responde outra pergunta, que é a da rotina financeira diária —
     * *"em que dia o dinheiro acaba?"*. Um saldo previsto positivo no mês pode
     * esconder um vermelho no dia 12, quando vence a folha e o recebimento só
     * entra no dia 20.
     *
     * Por isso a coluna que importa é o **saldo acumulado**, e por isso a série
     * parte do saldo REAL das contas hoje (`contas.saldo_atual`), não de zero:
     * um fluxo que começa em zero mostra falta de caixa onde há dinheiro em
     * conta, e o operador para de confiar no relatório.
     *
     * Só entram parcelas EM ABERTO: as baixadas já estão dentro do saldo atual,
     * e contá-las de novo seria dobrar o dinheiro.
     *
     * @return list<array<string,mixed>>
     */
    public function fluxoCaixa(int $empresaId, string $inicio, string $fim): array
    {
        $dtInicio = Carbon::parse($inicio)->startOfDay();
        $dtFim = Carbon::parse($fim)->startOfDay();

        // Ponto de partida: o que existe em caixa/banco agora. Contas fechadas
        // ficam de fora — o saldo delas não está disponível para pagar nada.
        $saldo = (float) DB::table('contas')
            ->where('empresa_id', $empresaId)
            ->where('fechado', false)
            ->sum('saldo_atual');

        $porDia = DB::table('financeiroparcelas as fp')
            ->join('financeiros as f', 'f.id', '=', 'fp.financeiro_id')
            ->where('f.empresa_id', $empresaId)
            ->where('f.cancelado', false)
            ->where('fp.baixado', false)
            // Comparar a DATA, não o texto: `vencimento` guarda datetime, e um
            // whereBetween contra 'AAAA-MM-DD' descartaria o último dia do
            // período (00:00:00 do dia seguinte já é maior que a string do fim).
            ->whereDate('fp.vencimento', '>=', $dtInicio->toDateString())
            ->whereDate('fp.vencimento', '<=', $dtFim->toDateString())
            ->groupBy('fp.vencimento')
            ->selectRaw('fp.vencimento, '
                ."sum(case when f.pagarreceber = 'R' then fp.valor else 0 end) as receber, "
                ."sum(case when f.pagarreceber = 'P' then fp.valor else 0 end) as pagar")
            ->get()
            ->keyBy(fn ($r) => (string) Carbon::parse($r->vencimento)->toDateString());

        $linhas = [];

        // Percorre TODOS os dias do período, inclusive os sem movimento: o dia
        // vazio é informação — mostra o saldo se sustentando (ou não) sem
        // entrada nenhuma.
        for ($dia = $dtInicio->copy(); $dia->lte($dtFim); $dia->addDay()) {
            $chave = $dia->toDateString();
            $r = $porDia->get($chave);

            $receber = round((float) ($r->receber ?? 0), 2);
            $pagar = round((float) ($r->pagar ?? 0), 2);
            $saldo = round($saldo + $receber - $pagar, 2);

            $linhas[] = [
                'data' => $dia->format('d/m/Y'),
                'a_receber' => $receber,
                'a_pagar' => $pagar,
                'resultado_dia' => round($receber - $pagar, 2),
                'saldo_acumulado' => $saldo,
                // A coluna que o financeiro procura de olho: o dia em que vira.
                'situacao' => $saldo < 0 ? 'NEGATIVO' : 'OK',
            ];
        }

        return $linhas;
    }

    /**
     * Clientes sem compra há N dias — a lista de quem parou de comprar.
     *
     * É a base da venda ativa: no disk-gás o cliente não avisa que trocou de
     * fornecedor, ele simplesmente deixa de ligar. Sem esta lista a perda é
     * invisível até aparecer no faturamento do mês, quando já é tarde para
     * recuperar.
     *
     * O corte padrão é 60 dias porque o giro típico de um P13 doméstico fica
     * entre 30 e 45 dias: quem passou de 60 não está atrasado, está comprando
     * de outro. O parâmetro existe porque o giro varia por perfil (comércio
     * consome mais rápido que residência).
     *
     * Clientes que NUNCA compraram entram na lista — são cadastro morto ou
     * venda perdida na origem, e nos dois casos o comercial precisa vê-los.
     *
     * @return list<array<string,mixed>>
     */
    public function clientesSemCompra(int $empresaId, int $dias = 60): array
    {
        $corte = Carbon::now()->subDays(max(1, $dias))->startOfDay();

        return DB::table('clientes as c')
            ->leftJoin('cidades as ci', 'ci.id', '=', 'c.cidade_id')
            // O logradouro vem da FK rua_id: a coluna `endereco` esta NULL em
            // toda a base, e sem este join o relatorio saía só com o numero.
            ->leftJoin('ruas as ru', 'ru.id', '=', 'c.rua_id')
            ->where('c.empresa_id', $empresaId)
            ->where('c.cliente', true)
            ->where('c.ativo', true)
            ->where(fn ($q) => $q
                ->whereNull('c.data_ultima_compra')
                ->orWhere('c.data_ultima_compra', '<', $corte->toDateString()))
            ->orderByRaw('c.data_ultima_compra asc nulls last')
            ->limit(5000)
            ->get(['c.id', 'c.nome', 'c.data_ultima_compra', 'ci.descricao as cidade', 'c.endereco', 'c.numero', 'ru.descricao as rua'])
            ->map(function ($r) {
                $ultima = $r->data_ultima_compra !== null ? Carbon::parse($r->data_ultima_compra) : null;

                return [
                    'cliente' => $r->nome,
                    'cidade' => $r->cidade ?? '',
                    // Logradouro vem da FK rua_id; a coluna `endereco` esta vazia.
                    'endereco' => trim(((string) ($r->endereco ?: $r->rua)).' '.((string) $r->numero)),
                    'ultima_compra' => $ultima?->format('d/m/Y') ?? 'Nunca comprou',
                    // Dias parado é o que ordena a abordagem do comercial.
                    'dias_sem_comprar' => $ultima !== null ? $ultima->diffInDays(Carbon::now()) : null,
                ];
            })->all();
    }
}
