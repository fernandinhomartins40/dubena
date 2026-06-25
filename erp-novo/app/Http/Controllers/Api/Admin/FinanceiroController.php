<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Financeiro\ConciliacaoContabilService;
use App\Domain\Financeiro\ConciliacaoService;
use App\Domain\Financeiro\FinanceiroService;
use App\Http\Controllers\Controller;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Financeiro (a pagar / a receber) — N5. Lançamentos por PARCELA (a granularidade
 * de cobrança), resumo, criação via FinanceiroService.
 */
class FinanceiroController extends Controller
{
    public function __construct(private FinanceiroService $service) {}

    /** GET /financeiro/lancamentos?pagarreceber=&status=&q= (parcelas) */
    public function lancamentos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');

        $q = trim((string) $request->query('q', ''));
        $parcelas = FinanceiroParcela::query()
            ->whereHas('financeiro', function (Builder $b) use ($request, $q) {
                $b->where('cancelado', false);
                if ($pr = $request->query('pagarreceber')) {
                    $b->where('pagarreceber', $pr);
                }
                if ($q !== '') {
                    $b->where(fn (Builder $w) => $w->where('documento', 'ilike', '%'.$q.'%')->orWhere('descricao', 'ilike', '%'.$q.'%'));
                }
            })
            ->when($request->query('status') === 'baixado', fn (Builder $b) => $b->where('baixado', true))
            ->when($request->query('status') === 'aberto', fn (Builder $b) => $b->where('baixado', false))
            ->with('financeiro:id,pagarreceber,documento,descricao,cliente_id')
            ->orderBy('vencimento')
            ->paginate(20);

        $data = collect($parcelas->items())->map(fn (FinanceiroParcela $p) => [
            'id' => $p->id,
            'financeiro_id' => $p->financeiro_id,
            'numero' => $p->numero,
            'datavencimento' => $p->vencimento->toDateString(),
            'valor' => (float) $p->valor,
            'valorefetivado' => (float) $p->valor_efetivado,
            'baixado' => (int) $p->baixado,
            'datahorabaixa' => $p->datahora_baixa?->toIso8601String(),
            'pagarreceber' => $p->financeiro?->pagarreceber,
            'documento' => $p->financeiro?->documento,
            'descricao' => $p->financeiro?->descricao,
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $parcelas->currentPage(),
                'last_page' => $parcelas->lastPage(),
                'per_page' => $parcelas->perPage(),
                'total' => $parcelas->total(),
            ],
        ]);
    }

    /** GET /financeiro/lancamentos/resumo */
    public function resumo(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');

        $base = FinanceiroParcela::query()->whereHas('financeiro', fn (Builder $b) => $b->where('cancelado', false));

        $aReceber = (clone $base)->whereHas('financeiro', fn (Builder $b) => $b->where('pagarreceber', 'R'))->where('baixado', false)->sum('valor');
        $aPagar = (clone $base)->whereHas('financeiro', fn (Builder $b) => $b->where('pagarreceber', 'P'))->where('baixado', false)->sum('valor');
        $vencidas = (clone $base)->where('baixado', false)->where('vencimento', '<', now()->toDateString())->count();

        return response()->json(['data' => [
            'a_receber' => round((float) $aReceber, 2),
            'a_pagar' => round((float) $aPagar, 2),
            'parcelas_vencidas' => $vencidas,
        ]]);
    }

    /** POST /financeiro/lancamentos */
    public function criar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.create');

        $d = $request->validate([
            'pagarreceber' => 'required|in:P,R',
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'planoconta_id' => 'nullable|integer|exists:planos_conta,id',
            'centrocusto_id' => 'nullable|integer|exists:centros_custo,id',
            'documento' => 'nullable|string|max:60',
            'descricao' => 'nullable|string|max:255',
            'valor' => 'required|numeric|gt:0',
            'data_emissao' => 'nullable|date',
            'num_parcelas' => 'nullable|integer|min:1|max:360',
        ]);

        $financeiro = $this->service->criar(
            array_merge($d, ['empresa_id' => $request->user()->empresa_id, 'grupo_id' => $request->user()->grupo_id]),
            numParcelas: (int) ($d['num_parcelas'] ?? 1),
        );

        return response()->json(['data' => $financeiro], 201);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'financeiro.delete');
        $this->service->cancelar(Financeiro::query()->findOrFail($id));

        return response()->json(['message' => 'Título cancelado.']);
    }

    /**
     * POST /financeiro/lancamentos/agrupar — consolida vários títulos num agrupador
     * (ex.: fechamento de convênio do mês). Expõe FinanceiroService::agrupar (F00.6).
     * Os títulos são resolvidos via query tenant-scoped (não enxerga outra empresa).
     */
    public function agrupar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $d = $request->validate([
            'titulos' => 'required|array|min:1',
            'titulos.*' => 'integer|distinct',
            'pagarreceber' => 'required|in:P,R',
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'descricao' => 'nullable|string|max:255',
            'num_parcelas' => 'nullable|integer|min:1|max:360',
        ]);

        $titulos = Financeiro::query()->whereIn('id', $d['titulos'])->get();
        abort_if($titulos->count() !== count($d['titulos']), 422, 'Um ou mais títulos não pertencem à empresa ativa.');

        $agrupador = $this->service->agrupar(
            $titulos,
            [
                'empresa_id' => $request->user()->empresa_id,
                'grupo_id' => $request->user()->grupo_id,
                'pagarreceber' => $d['pagarreceber'],
                'cliente_id' => $d['cliente_id'] ?? null,
                'descricao' => $d['descricao'] ?? 'Agrupamento de títulos',
            ],
            numParcelas: (int) ($d['num_parcelas'] ?? 1),
        );

        return response()->json(['data' => $agrupador], 201);
    }

    /** POST /financeiro/lancamentos/{id}/desagrupar — desfaz um agrupador (F00.6). */
    public function desagrupar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $this->service->desagrupar(Financeiro::query()->findOrFail($id));

        return response()->json(['message' => 'Agrupamento desfeito.']);
    }

    /** POST /financeiro/lancamentos/{id}/reparcelar — reparcela o saldo em aberto (F00.6). */
    public function reparcelar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $d = $request->validate([
            'num_parcelas' => 'required|integer|min:1|max:360',
            'data_base' => 'nullable|date',
        ]);

        $novo = $this->service->reparcelar(
            Financeiro::query()->findOrFail($id),
            (int) $d['num_parcelas'],
            $d['data_base'] ?? null,
        );

        return response()->json(['data' => $novo], 201);
    }

    /**
     * GET/POST /financeiro/conciliacao — concilia um extrato OFX com os movimentos
     * da conta no período (C8). Sem OFX (GET inicial), devolve os movimentos do ERP
     * como pendentes; com OFX (campo `ofx` ou upload `arquivo`), casa as transações.
     */
    public function conciliacao(Request $request, ConciliacaoService $service): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');
        $d = $request->validate([
            'conta_id' => 'required|integer|exists:contas,id',
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
            'ofx' => 'nullable|string',
            'arquivo' => 'nullable|file',
        ]);

        $ofx = $d['ofx'] ?? null;
        if (! $ofx && $request->hasFile('arquivo')) {
            $ofx = (string) file_get_contents($request->file('arquivo')->getRealPath());
        }

        $resultado = $service->conciliar((int) $d['conta_id'], $ofx ?? '', $d['inicio'], $d['fim']);

        return response()->json(['data' => $resultado]);
    }

    /**
     * GET /financeiro/conciliacao-contabil — concilia o financeiro do ERP com o
     * saldo contábil externo (CONSISA) por período (F08). Gate: sem CONSISA
     * configurada, devolve o lado do ERP com diferença = valor (habilitado=false).
     */
    public function conciliacaoContabil(Request $request, ConciliacaoContabilService $service): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');
        $d = $request->validate([
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
            'tipo' => 'nullable|in:P,R',
        ]);

        $resultado = $service->conciliar(
            (int) $request->user()->empresa_id,
            $d['inicio'],
            $d['fim'],
            $d['tipo'] ?? 'R',
        );

        return response()->json(['data' => $resultado]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
