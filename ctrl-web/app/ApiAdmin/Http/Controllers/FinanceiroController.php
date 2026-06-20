<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\ApiAdmin\Services\FinanceiroService;
use App\Planoconta;
use App\Centrocusto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * F6 (SPA React) — API admin FINANCEIRO: Lançamentos (contas a receber/pagar
 * unificadas, filtros tipados) + Plano/Centro de contas. Lançamento criado via
 * financeiroProcessor (FinanceiroService, baseline). Caixa/Cheques/Boletos em
 * controllers próprios. Filtros server-side parametrizados (sem SQLi).
 */
class FinanceiroController extends Controller
{
    public function __construct(private FinanceiroService $service)
    {
    }

    private function autorizar(Request $request, string $modulo, string $acao): void
    {
        abort_unless($request->user()->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }

    private function grupoId(Request $request): int
    {
        $u = $request->user();
        return (int) (optional($u->empresa)->grupo_id ?? $u->grupo_id);
    }

    // =================== LANÇAMENTOS ===================

    /**
     * GET /api/admin/financeiro/lancamentos?pagarreceber=&status=&q=&page=
     * Consolida Contas a Receber + Pagar com filtros TIPADOS (parametrizados).
     */
    public function lancamentos(Request $request)
    {
        $this->autorizar($request, 'financeiro', 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $pagarreceber = $request->query('pagarreceber') === 'P' ? 'P' : ($request->query('pagarreceber') === 'R' ? 'R' : null);
        $status = $request->query('status'); // aberto|baixado|todos
        $q = trim((string) $request->query('q', ''));
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = DB::table('financeiroparcelas as fp')
            ->join('financeiros as f', 'f.id', '=', 'fp.financeiro_id')
            ->leftJoin('clientes as c', 'c.id', '=', 'f.cliente_id')
            ->where('fp.empresa_id', $empresaId)
            ->when($pagarreceber, fn ($w) => $w->where('fp.pagarreceber', $pagarreceber))
            ->when($status === 'aberto', fn ($w) => $w->where('fp.baixado', 0))
            ->when($status === 'baixado', fn ($w) => $w->where('fp.baixado', 1))
            ->when($q !== '', function ($w) use ($q) {
                $w->where('f.descricao', 'ilike', '%' . $q . '%')
                  ->orWhere('f.documento', 'ilike', '%' . $q . '%')
                  ->orWhere('c.nome', 'ilike', '%' . $q . '%');
            });

        $page = $query->orderByDesc('fp.datavencimento')->orderByDesc('fp.id')
            ->paginate($perPage, [
                'fp.id', 'fp.numero', 'fp.datavencimento', 'fp.valor', 'fp.valorefetivado',
                'fp.baixado', 'fp.datahorabaixa', 'fp.pagarreceber',
                'f.documento', 'f.descricao', 'c.nome as cliente',
            ]);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
            ],
        ]);
    }

    /** GET /api/admin/financeiro/lancamentos/resumo — totais por status (cards). */
    public function resumo(Request $request)
    {
        $this->autorizar($request, 'financeiro', 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $base = fn ($pr) => DB::table('financeiroparcelas')->where('empresa_id', $empresaId)->where('pagarreceber', $pr);

        return response()->json(['data' => [
            'receber_aberto' => (float) (clone $base('R'))->where('baixado', 0)->sum('valorefetivado'),
            'receber_baixado' => (float) (clone $base('R'))->where('baixado', 1)->sum('valorefetivado'),
            'pagar_aberto'   => (float) (clone $base('P'))->where('baixado', 0)->sum('valorefetivado'),
            'pagar_baixado'  => (float) (clone $base('P'))->where('baixado', 1)->sum('valorefetivado'),
        ]]);
    }

    /** POST /api/admin/financeiro/lancamentos — cria via financeiroProcessor. */
    public function criarLancamento(Request $request)
    {
        $this->autorizar($request, 'financeiro', 'create');
        $data = $request->validate([
            'cliente_id'       => 'required|integer',
            'pagarreceber'     => 'required|in:P,R',
            'descricao'        => 'nullable|string|max:255',
            'documento'        => 'nullable|string|max:50',
            'valor'            => 'required|numeric|min:0.01',
            'dataemissao'      => 'required|date',
            'datacompetencia'  => 'required|date',
            'datavencimento'   => 'required|date',
            'planoconta_id'    => 'required|integer',
            'centrocusto_id'   => 'required|integer',
            'condicaopagamento_id' => 'required|integer',
            'parcelas'         => 'nullable|array',
            'parcelas.*.datavencimento' => 'required_with:parcelas|date',
            'parcelas.*.valor' => 'required_with:parcelas|numeric',
            'rateios'          => 'nullable|array',
        ]);

        Session::put('empresa_padrao', \App\Empresa::find($request->user()->empresa_id));
        try {
            $fin = $this->service->gravar($data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['id' => $fin->id]], 201);
    }

    // =================== PLANO DE CONTAS ===================

    public function planosConta(Request $request)
    {
        $this->autorizar($request, 'planoconta', 'view');
        $rows = Planoconta::where('grupo_id', $this->grupoId($request))
            ->orderBy('codigo')->get(['id', 'codigo', 'descricao', 'pagarreceber', 'nivel']);
        return response()->json(['data' => $rows]);
    }

    public function salvarPlanoConta(Request $request, $id = null)
    {
        $this->autorizar($request, 'planoconta', $id ? 'edit' : 'create');
        $data = $request->validate([
            'descricao'    => 'required|string|max:255',
            'codigo'       => 'nullable|string|max:20',
            'pagarreceber' => 'nullable|string|max:1',
            'nivel'        => 'nullable|integer',
        ]);
        if ($id) {
            // Update: só os campos enviados (não sobrescreve NOT NULL com null).
            $pc = Planoconta::where('grupo_id', $this->grupoId($request))->findOrFail($id);
            $pc->update(array_filter([
                'descricao' => $data['descricao'],
                'codigo' => $data['codigo'] ?? null,
                'pagarreceber' => $data['pagarreceber'] ?? null,
                'nivel' => $data['nivel'] ?? null,
            ], fn ($v) => $v !== null));
            return response()->json(['data' => $pc->fresh()]);
        }
        $pc = Planoconta::create([
            'grupo_id' => $this->grupoId($request), 'empresa_id' => (int) $request->user()->empresa_id,
            'descricao' => $data['descricao'], 'codigo' => $data['codigo'] ?? '',
            'pagarreceber' => $data['pagarreceber'] ?? 'R', 'nivel' => $data['nivel'] ?? 1,
            'insumo_valor' => 0, 'provisao' => 0, 'investimento' => 0,
        ]);
        return response()->json(['data' => $pc], 201);
    }

    public function excluirPlanoConta(Request $request, $id)
    {
        $this->autorizar($request, 'planoconta', 'delete');
        try {
            DB::transaction(fn () => Planoconta::where('grupo_id', $this->grupoId($request))->findOrFail($id)->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Plano de contas em uso — não pode ser excluído.'], 409);
        }
        return response()->json(['message' => 'Plano de contas excluído.']);
    }

    // =================== CENTRO DE CUSTO ===================

    public function centrosCusto(Request $request)
    {
        $this->autorizar($request, 'centrocusto', 'view');
        $rows = Centrocusto::where('empresa_id', $request->user()->empresa_id)
            ->orderBy('codigo')->get(['id', 'codigo', 'descricao', 'nivel', 'ativo']);
        return response()->json(['data' => $rows]);
    }

    public function salvarCentroCusto(Request $request, $id = null)
    {
        $this->autorizar($request, 'centrocusto', $id ? 'edit' : 'create');
        $data = $request->validate([
            'descricao' => 'required|string|max:255',
            'codigo'    => 'nullable|string|max:20',
            'nivel'     => 'nullable|integer',
            'ativo'     => 'nullable|boolean',
        ]);
        if ($id) {
            $cc = Centrocusto::where('empresa_id', $request->user()->empresa_id)->findOrFail($id);
            $cc->update(array_filter([
                'descricao' => $data['descricao'],
                'codigo' => $data['codigo'] ?? null,
                'nivel' => $data['nivel'] ?? null,
                'ativo' => isset($data['ativo']) ? (int) (! empty($data['ativo'])) : null,
            ], fn ($v) => $v !== null));
            return response()->json(['data' => $cc->fresh()]);
        }
        $cc = Centrocusto::create([
            'grupo_id' => $this->grupoId($request), 'empresa_id' => (int) $request->user()->empresa_id,
            'descricao' => $data['descricao'], 'codigo' => $data['codigo'] ?? '',
            'nivel' => $data['nivel'] ?? 1, 'ativo' => isset($data['ativo']) ? (int) (! empty($data['ativo'])) : 1,
        ]);
        return response()->json(['data' => $cc], 201);
    }

    public function excluirCentroCusto(Request $request, $id)
    {
        $this->autorizar($request, 'centrocusto', 'delete');
        try {
            DB::transaction(fn () => Centrocusto::where('empresa_id', $request->user()->empresa_id)->findOrFail($id)->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Centro de custo em uso — não pode ser excluído.'], 409);
        }
        return response()->json(['message' => 'Centro de custo excluído.']);
    }
}
