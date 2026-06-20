<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Conta;
use App\Contafechamento;
use App\Contamovimento;
use App\Processors\caixaProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Carbon;

/**
 * F6 (SPA React) — API admin CAIXA/Tesouraria.
 * Auditado: caixaProcessor (motor) + CaixaController(1056). Abrir/fechar caixa,
 * consultar contas e movimentos, com preview de saldo. O motor exige autorização
 * por conta (contausers.operar) e Session('empresa_padrao')/Auth — populados aqui.
 */
class CaixaController extends Controller
{
    private function autorizar(Request $request, string $acao): void
    {
        abort_unless($request->user()->podeRecurso('caixa', $acao), 403, 'Sem permissão.');
    }

    private function contexto(Request $request): void
    {
        Session::put('empresa_padrao', \App\Empresa::find($request->user()->empresa_id));
        \Auth::login($request->user());
    }

    /** GET /api/admin/caixa/contas — contas (caixas) da empresa, com saldo e status. */
    public function contas(Request $request)
    {
        $this->autorizar($request, 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $rows = Conta::where('empresa_id', $empresaId)->where('ativo', 1)
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'saldoatual', 'fechado']);
        return response()->json(['data' => $rows]);
    }

    /** GET /api/admin/caixa/{contaId}/movimentos — últimos movimentos do caixa. */
    public function movimentos(Request $request, $contaId)
    {
        $this->autorizar($request, 'view');
        $conta = Conta::where('empresa_id', $request->user()->empresa_id)->findOrFail($contaId);

        $rows = Contamovimento::query()
            ->where('conta_id', $conta->id)
            ->orderByDesc('datahorabaixa')->orderByDesc('id')->limit(200)
            ->get(['id', 'datahorabaixa', 'valorefetivado', 'pagarreceber', 'origem', 'descricao']);
        return response()->json(['data' => $rows, 'saldo' => (float) $conta->saldoatual, 'fechado' => (int) $conta->fechado]);
    }

    /** POST /api/admin/caixa/{contaId}/abrir */
    public function abrir(Request $request, $contaId)
    {
        $this->autorizar($request, 'create');
        $conta = Conta::where('empresa_id', $request->user()->empresa_id)->findOrFail($contaId);
        $data = $request->validate(['datahoraabertura' => 'required|date']);

        $this->contexto($request);
        $proc = new caixaProcessor($conta->id);
        $proc->setDataAbertura(Carbon::parse($data['datahoraabertura'])->format('Y-m-d H:i:s'));
        if (! $proc->abrirCaixa()) {
            return response()->json(['message' => implode(' ', $proc->getErrors())], 422);
        }
        return response()->json(['message' => 'Caixa aberto.'], 201);
    }

    /** POST /api/admin/caixa/{contaId}/fechar */
    public function fechar(Request $request, $contaId)
    {
        $this->autorizar($request, 'edit');
        $conta = Conta::where('empresa_id', $request->user()->empresa_id)->findOrFail($contaId);
        $data = $request->validate(['datahorafechamento' => 'required|date']);

        $this->contexto($request);
        $proc = new caixaProcessor($conta->id);
        $proc->setDataFechamento(Carbon::parse($data['datahorafechamento'])->format('Y-m-d H:i:s'));
        if (! $proc->fecharCaixa()) {
            return response()->json(['message' => implode(' ', $proc->getErrors())], 422);
        }
        return response()->json(['message' => 'Caixa fechado.']);
    }

    /** GET /api/admin/caixa/{contaId}/fechamentos — histórico de aberturas/fechamentos. */
    public function fechamentos(Request $request, $contaId)
    {
        $this->autorizar($request, 'view');
        $conta = Conta::where('empresa_id', $request->user()->empresa_id)->findOrFail($contaId);
        $rows = Contafechamento::where('conta_id', $conta->id)
            ->orderByDesc('datahoraabertura')->limit(100)
            ->get(['id', 'datahoraabertura', 'datahorafechamento', 'saldoinicial', 'saldofinal', 'fechado']);
        return response()->json(['data' => $rows]);
    }
}
