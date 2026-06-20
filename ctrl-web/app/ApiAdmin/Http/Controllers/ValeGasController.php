<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Valegas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F8c (SPA React) — API admin VALE-GÁS (Gás de Bolso).
 * Auditado: Valegas/Valegasvenda + Valegasbaixar/Valegascancelar/Valegasconsulta
 * (docs/01-vigente/IMPL_SATELITES.md). Ciclo: consulta → baixar → cancelar com
 * status visível. RBAC: vendavalegas / valegas (usa 'valegas' como módulo).
 */
class ValeGasController extends Controller
{
    private function autorizar(Request $request, string $acao): void
    {
        abort_unless($request->user()->podeRecurso('vendavalegas', $acao), 403, 'Sem permissão.');
    }

    /** GET /api/admin/vale-gas?q=&status= — consulta de vales gerados. */
    public function index(Request $request)
    {
        $this->autorizar($request, 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $q = trim((string) $request->query('q', ''));

        $rows = DB::table('valegas as vg')
            ->leftJoin('valegassituacaos as s', 's.id', '=', 'vg.valegassituacao_id')
            ->leftJoin('clientes as c', 'c.id', '=', 'vg.cliente_id')
            ->leftJoin('produtos as p', 'p.id', '=', 'vg.produto_id')
            ->where('vg.empresa_id', $empresaId)
            ->when($q !== '', fn ($w) => $w->where('vg.codigo', 'ilike', '%' . $q . '%'))
            ->orderByDesc('vg.datageracao')->limit(300)
            ->get(['vg.id', 'vg.codigo', 'vg.datageracao', 'vg.databaixa', 'vg.valegassituacao_id',
                's.descricao as situacao', 'c.nome as cliente', 'p.descricao as produto']);
        return response()->json(['data' => $rows]);
    }

    /** GET /api/admin/vale-gas/situacoes */
    public function situacoes(Request $request)
    {
        $this->autorizar($request, 'view');
        return response()->json(['data' => DB::table('valegassituacaos')->orderBy('descricao')->get(['id', 'descricao'])]);
    }

    /** POST /api/admin/vale-gas/baixar — baixa pelo código (paridade Valegasbaixar). */
    public function baixar(Request $request)
    {
        $this->autorizar($request, 'edit');
        $data = $request->validate([
            'codigo'   => 'required|string|max:50',
            'situacao_id' => 'required|integer',
        ]);
        $empresaId = (int) $request->user()->empresa_id;
        $vale = Valegas::where('empresa_id', $empresaId)->where('codigo', $data['codigo'])->first();
        if (! $vale) {
            return response()->json(['message' => 'Gás de Bolso não encontrado. Verifique o código.'], 404);
        }
        $vale->update(['valegassituacao_id' => $data['situacao_id'], 'databaixa' => now()]);
        return response()->json(['message' => 'Vale-gás baixado.']);
    }

    /** POST /api/admin/vale-gas/{id}/cancelar */
    public function cancelar(Request $request, $id)
    {
        $this->autorizar($request, 'edit');
        $data = $request->validate(['situacao_id' => 'required|integer']);
        $vale = Valegas::where('empresa_id', $request->user()->empresa_id)->findOrFail($id);
        $vale->update(['valegassituacao_id' => $data['situacao_id'], 'databaixa' => now()]);
        return response()->json(['message' => 'Vale-gás cancelado.']);
    }
}
