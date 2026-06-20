<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F6 (SPA React) — Gestão financeira: Fechamento Mensal (DRE por plano de contas)
 * + Conciliação (extrato consolidado por conta). Auditado: Fechamentomensalgestao,
 * Conciliacao (docs/01-vigente/IMPL_FINANCEIRO.md). VISÃO NOVA: DRE calculável a
 * partir dos rateios baixados no período (substitui o relatório PPT legado).
 * Conciliação OFX/import de arquivo permanece no fluxo legado. RBAC: financeiro.
 */
class FinanceiroGestaoController extends Controller
{
    private function autorizar(Request $request, string $acao): void
    {
        abort_unless($request->user()->podeRecurso('financeiro', $acao), 403, 'Sem permissão.');
    }

    /**
     * GET /api/admin/financeiro/dre?inicio=&fim=
     * DRE simplificada: agrupa o valor efetivado das parcelas BAIXADAS no período
     * por plano de contas (via rateio), separando receitas (R) e despesas (P).
     */
    public function dre(Request $request)
    {
        $this->autorizar($request, 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $data = $request->validate(['inicio' => 'required|date', 'fim' => 'required|date']);

        // Parcelas baixadas no período, com rateio por plano de contas.
        $linhas = DB::table('financeiroparcelas as fp')
            ->join('financeiros as f', 'f.id', '=', 'fp.financeiro_id')
            ->join('financeirorateios as r', 'r.financeiro_id', '=', 'f.id')
            ->join('planocontas as pc', 'pc.id', '=', 'r.planoconta_id')
            ->where('fp.empresa_id', $empresaId)
            ->where('fp.baixado', 1)
            ->whereBetween('fp.datahorabaixa', [$data['inicio'] . ' 00:00:00', $data['fim'] . ' 23:59:59'])
            ->groupBy('pc.id', 'pc.descricao', 'fp.pagarreceber')
            ->selectRaw('pc.descricao as plano, fp.pagarreceber, SUM(r.valor) as total')
            ->orderBy('pc.descricao')
            ->get();

        $receitas = $linhas->where('pagarreceber', 'R')->map(fn ($l) => ['plano' => $l->plano, 'total' => (float) $l->total])->values();
        $despesas = $linhas->where('pagarreceber', 'P')->map(fn ($l) => ['plano' => $l->plano, 'total' => (float) $l->total])->values();
        $totalR = $receitas->sum('total');
        $totalP = $despesas->sum('total');

        return response()->json(['data' => [
            'receitas'  => $receitas,
            'despesas'  => $despesas,
            'total_receitas' => $totalR,
            'total_despesas' => $totalP,
            'resultado' => $totalR - $totalP,
        ]]);
    }

    /**
     * GET /api/admin/financeiro/conciliacao?conta_id=&inicio=&fim=
     * Extrato consolidado de uma conta no período (movimentos de caixa).
     */
    public function conciliacao(Request $request)
    {
        $this->autorizar($request, 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $data = $request->validate([
            'conta_id' => 'required|integer',
            'inicio'   => 'required|date',
            'fim'      => 'required|date',
        ]);

        // garante que a conta é da empresa
        abort_unless(
            DB::table('contas')->where(['id' => $data['conta_id'], 'empresa_id' => $empresaId])->exists(),
            404, 'Conta não encontrada.'
        );

        $movs = DB::table('contamovimentos')
            ->where('conta_id', $data['conta_id'])
            ->whereBetween('datahorabaixa', [$data['inicio'] . ' 00:00:00', $data['fim'] . ' 23:59:59'])
            ->orderBy('datahorabaixa')
            ->get(['id', 'datahorabaixa', 'valorefetivado', 'pagarreceber', 'origem', 'descricao', 'ofxuniqueid']);

        $entradas = $movs->where('pagarreceber', 'R')->sum('valorefetivado');
        $saidas = $movs->where('pagarreceber', 'P')->sum('valorefetivado');

        return response()->json(['data' => [
            'movimentos' => $movs->values(),
            'entradas'   => (float) $entradas,
            'saidas'     => (float) $saidas,
            'saldo'      => (float) ($entradas - $saidas),
        ]]);
    }
}
