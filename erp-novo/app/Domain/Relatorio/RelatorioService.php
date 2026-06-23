<?php

namespace App\Domain\Relatorio;

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
        return DB::table('clientes')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('datanascimento')
            ->whereRaw('cast(strftime(\'%m\', datanascimento) as integer) = ?', [$mes])
            ->orderByRaw('strftime(\'%d\', datanascimento)')
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
    public function comissoes(int $empresaId, string $inicio, string $fim): array
    {
        return DB::table('colaboradores as co')
            ->leftJoin('colaborador_comissoes as cc', 'cc.colaborador_id', '=', 'co.id')
            ->where('co.empresa_id', $empresaId)
            ->where('co.ativo', true)
            ->groupBy('co.id', 'co.nome')
            ->selectRaw('co.nome as colaborador, count(cc.id) as regras, coalesce(avg(cc.percentual),0) as percentual_medio')
            ->orderBy('co.nome')
            ->get()
            ->map(fn ($r) => [
                'colaborador' => $r->colaborador,
                'regras' => (int) $r->regras,
                'percentual_medio' => round((float) $r->percentual_medio, 2),
            ])->all();
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
}
