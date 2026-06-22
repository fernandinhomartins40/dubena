<?php

namespace App\Domain\Relatorio;

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
        $vendas = $this->vendas($empresaId, $inicio, $fim);
        $fin = $this->financeiro($empresaId, $inicio, $fim);

        $receita = $vendas['total'];
        $despesas = $fin['pago'];

        return [
            'receita_vendas' => $receita,
            'recebido' => $fin['recebido'],
            'despesas_pagas' => $despesas,
            'resultado' => round($receita - $despesas, 2),
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
}
