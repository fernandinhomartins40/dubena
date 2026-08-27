<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Pagamento\GasDoPovoService;
use App\Domain\Pagamento\PagamentoService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Pagamento\CartaoTransacao;
use App\Models\Pagamento\GasDoPovoBeneficio;
use App\Models\Pedido\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pagamentos (C4 resto): cartão (NSU/bandeira/parcelas) e "Gás do Povo".
 */
class PagamentoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private PagamentoService $service) {}

    // ───────── Cartão ─────────
    public function cartaoIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cartao.view');

        return response()->json(['data' => CartaoTransacao::query()->latest()->limit(200)->get()]);
    }

    public function cartaoRegistrar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cartao.create');
        $d = $request->validate([
            'pedido_id' => 'nullable|integer|exists:pedidos,id',
            'conta_id' => 'nullable|integer|exists:contas,id',
            'bandeira' => 'nullable|string|max:30',
            'tipo' => 'nullable|in:credito,debito',
            'nsu' => 'nullable|string|max:30',
            'autorizacao' => 'nullable|string|max:30',
            'parcelas' => 'nullable|integer|min:1|max:24',
            'valor_bruto' => 'required|numeric|gt:0',
            'taxa_percentual' => 'nullable|numeric|min:0|max:100',
        ]);
        $d['empresa_id'] = app(TenantContext::class)->empresaId();
        abort_if($d['empresa_id'] === null, 403, 'Tenant não resolvido.');

        return response()->json(['data' => $this->service->registrarCartao($d)], 201);
    }

    // ───────── Gás do Povo ─────────
    public function gasIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'gasdopovo.view');

        return response()->json(['data' => GasDoPovoBeneficio::query()->latest()->limit(200)->get()]);
    }

    public function gasRegistrar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'gasdopovo.create');
        $d = $request->validate([
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'nis' => 'nullable|string|max:20',
            'competencia' => 'required|string|size:7',
            'valor' => 'required|numeric|gt:0',
        ]);

        return response()->json(['data' => $this->service->registrarBeneficio($d)], 201);
    }

    public function gasSacar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'gasdopovo.edit');
        $d = $request->validate([
            'pedido_id' => 'required|integer|exists:pedidos,id',
            'conta_id' => 'nullable|integer|exists:contas,id',
        ]);
        $beneficio = GasDoPovoBeneficio::query()->findOrFail($id);

        return response()->json(['data' => $this->service->sacarBeneficio($beneficio, $d['pedido_id'], $d['conta_id'] ?? null)]);
    }

    // ───────── Gás do Povo: o programa como o legado opera ─────────
    //
    // Distinto dos benefícios acima (modelo de voucher, alimentado pela operação
    // do sistema novo): aqui é o PROGRAMA — parâmetros da empresa, quem são os
    // beneficiários e o que foi vendido subsidiado. Ver
    // `docs/02-auditoria-legado/GAS_DO_POVO_NO_LEGADO.md`.

    /** GET /gasdopovo/programa — parâmetros + resumo do período + série mensal. */
    public function gasPrograma(Request $request, GasDoPovoService $programa): JsonResponse
    {
        $this->autorizar($request, 'gasdopovo.view');

        $d = $request->validate([
            'de' => 'nullable|date',
            'ate' => 'nullable|date|after_or_equal:de',
        ]);

        $empresaId = app(TenantContext::class)->empresaId();

        return response()->json(['data' => [
            'parametros' => $programa->parametros($empresaId),
            'resumo' => $programa->resumo($empresaId, $d['de'] ?? null, $d['ate'] ?? null),
            'por_mes' => $programa->porMes($empresaId),
        ]]);
    }

    /** GET /gasdopovo/beneficiarios — os clientes marcados no cadastro. */
    public function gasBeneficiarios(Request $request): JsonResponse
    {
        $this->autorizar($request, 'gasdopovo.view');
        $q = trim((string) $request->query('q', ''));

        $clientes = Cliente::query()
            ->where('gasdopovo', true)
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('nome', 'ilike', '%'.$q.'%')
                ->orWhere('cpf', 'ilike', '%'.$q.'%')))
            ->orderBy('nome')
            ->paginate(30, ['id', 'nome', 'cpf', 'cnpj', 'ativo', 'data_ultima_compra']);

        // Envelope {data, meta} — o mesmo do resto da API. O `paginate` cru
        // devolve `total` na raiz, e a SPA lê de `meta`.
        return response()->json([
            'data' => $clientes->items(),
            'meta' => [
                'current_page' => $clientes->currentPage(),
                'last_page' => $clientes->lastPage(),
                'per_page' => $clientes->perPage(),
                'total' => $clientes->total(),
            ],
        ]);
    }

    /** GET /gasdopovo/vendas — os pedidos marcados como do programa. */
    public function gasVendas(Request $request): JsonResponse
    {
        $this->autorizar($request, 'gasdopovo.view');

        $d = $request->validate([
            'de' => 'nullable|date',
            'ate' => 'nullable|date|after_or_equal:de',
        ]);

        $pedidos = Pedido::query()
            ->where('gasdopovo', true)
            ->with(['cliente:id,nome', 'situacao:id,descricao'])
            ->when(isset($d['de']), fn ($b) => $b->whereDate('datahora', '>=', $d['de']))
            ->when(isset($d['ate']), fn ($b) => $b->whereDate('datahora', '<=', $d['ate']))
            ->orderByDesc('datahora')
            ->paginate(30);

        return response()->json([
            'data' => $pedidos->through(fn (Pedido $p) => [
                'id' => $p->id,
                'datahora' => $p->datahora?->toIso8601String(),
                'cliente' => $p->cliente?->nome,
                'situacao' => $p->situacao?->descricao,
                'valorvenda' => (float) $p->valor_venda,
            ])->items(),
            'meta' => [
                'current_page' => $pedidos->currentPage(),
                'last_page' => $pedidos->lastPage(),
                'per_page' => $pedidos->perPage(),
                'total' => $pedidos->total(),
            ],
        ]);
    }
}
