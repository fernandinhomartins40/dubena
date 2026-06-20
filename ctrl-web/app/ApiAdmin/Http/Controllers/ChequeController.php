<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Chequeemitido;
use App\Chequerecebido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F6 (SPA React) — API admin de CHEQUES (emitidos e recebidos).
 * Auditado: Chequeemitido/Chequerecebido + chequesituacaos (docs/01-vigente/IMPL_FINANCEIRO.md).
 * Cobre registro/consulta com situação. O ciclo de estorno/encontro de contas
 * (ChequeProcessor, acoplado à baixa de caixa) permanece no legado — aqui é
 * cadastro/visão consolidada. RBAC: chequeemitido / chequerecebido.
 */
class ChequeController extends Controller
{
    private function grupoId(Request $request): int
    {
        $u = $request->user();
        return (int) (optional($u->empresa)->grupo_id ?? $u->grupo_id);
    }

    private function autorizar(Request $request, string $modulo, string $acao): void
    {
        abort_unless($request->user()->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }

    /** GET /api/admin/cheques/situacoes?tipo=emitido|recebido */
    public function situacoes(Request $request)
    {
        $tipo = $request->query('tipo') === 'recebido' ? 'chequerecebido' : 'chequeemitido';
        return response()->json([
            'data' => DB::table('chequesituacaos')->where($tipo, 1)->orderBy('descricao')->get(['id', 'descricao']),
        ]);
    }

    /**
     * GET /api/admin/cheques/emitidos?q= — consulta (read).
     * Cheque emitido depende de talão (contatalao_id NOT NULL); o cadastro completo
     * permanece no fluxo legado. Aqui expomos a CONSULTA consolidada.
     */
    public function emitidos(Request $request)
    {
        $this->autorizar($request, 'chequeemitido', 'view');
        $q = trim((string) $request->query('q', ''));
        $rows = Chequeemitido::query()
            ->leftJoin('chequesituacaos as s', 's.id', '=', 'chequeemitidos.chequesituacao_id')
            ->where('chequeemitidos.empresa_id', $request->user()->empresa_id)
            ->when($q !== '', fn ($w) => $w->where('chequeemitidos.numerocheque', 'ilike', '%' . $q . '%'))
            ->orderByDesc('chequeemitidos.datavencimento')->limit(300)
            ->get(['chequeemitidos.id', 'chequeemitidos.numerocheque', 'chequeemitidos.valor',
                'chequeemitidos.dataemissao', 'chequeemitidos.datavencimento', 'chequeemitidos.datapagamento',
                's.descricao as situacao', 'chequeemitidos.chequesituacao_id']);
        return response()->json(['data' => $rows]);
    }

    // =================== RECEBIDOS ===================

    /** GET /api/admin/cheques/recebidos?q= */
    public function recebidos(Request $request)
    {
        $this->autorizar($request, 'chequerecebido', 'view');
        $q = trim((string) $request->query('q', ''));
        $rows = Chequerecebido::query()
            ->leftJoin('chequesituacaos as s', 's.id', '=', 'chequerecebidos.chequesituacao_id')
            ->where('chequerecebidos.empresa_id', $request->user()->empresa_id)
            ->when($q !== '', fn ($w) => $w->where('chequerecebidos.numerocheque', 'ilike', '%' . $q . '%'))
            ->orderByDesc('chequerecebidos.datavencimento')->limit(300)
            ->get(['chequerecebidos.id', 'chequerecebidos.numerocheque', 'chequerecebidos.valor',
                'chequerecebidos.dataemissao', 'chequerecebidos.datavencimento', 'chequerecebidos.datadeposito',
                's.descricao as situacao', 'chequerecebidos.chequesituacao_id']);
        return response()->json(['data' => $rows]);
    }

    /** POST /api/admin/cheques/recebidos · PUT .../{id} */
    public function salvarRecebido(Request $request, $id = null)
    {
        $this->autorizar($request, 'chequerecebido', $id ? 'edit' : 'create');
        $data = $request->validate([
            'numerocheque'      => 'required|string|max:50',
            'valor'             => 'required|numeric|min:0.01',
            'banco_id'          => 'required|integer', // NOT NULL no schema
            'agencia'           => 'nullable|string|max:20',
            'numeroconta'       => 'nullable|string|max:30',
            'chequesituacao_id' => 'required|integer',
            'dataemissao'       => 'required|date',
            'datavencimento'    => 'required|date',
            'datacompetencia'   => 'nullable|date',
            'observacao'        => 'nullable|string|max:255',
        ]);
        $payload = array_merge($data, [
            'grupo_id' => $this->grupoId($request), 'empresa_id' => (int) $request->user()->empresa_id,
            'datacompetencia' => $data['datacompetencia'] ?? $data['dataemissao'],
        ]);
        $ch = $id
            ? tap(Chequerecebido::where('empresa_id', $request->user()->empresa_id)->findOrFail($id))->update($payload)
            : Chequerecebido::create($payload);
        return response()->json(['data' => $ch], $id ? 200 : 201);
    }

    public function excluirRecebido(Request $request, $id)
    {
        $this->autorizar($request, 'chequerecebido', 'delete');
        try {
            DB::transaction(fn () => Chequerecebido::where('empresa_id', $request->user()->empresa_id)->findOrFail($id)->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Cheque em uso — não pode ser excluído.'], 409);
        }
        return response()->json(['message' => 'Cheque excluído.']);
    }
}
