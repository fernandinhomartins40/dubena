<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Pagamento\PagamentoService;
use App\Http\Controllers\Controller;
use App\Models\Pagamento\CartaoTransacao;
use App\Models\Pagamento\GasDoPovoBeneficio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pagamentos (C4 resto): cartão (NSU/bandeira/parcelas) e "Gás do Povo".
 */
class PagamentoController extends Controller
{
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

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
