<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Mobile\PagamentoOnlineService;
use App\Domain\Mobile\PedidoMobileService;
use App\Http\Controllers\Controller;
use App\Models\Pedido\Pedido;
use App\Models\Produto\Produto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API do app do CLIENTE — N10. Catálogo, criar pedido (matching geoloc) e pagar.
 * Tenant resolvido pelo middleware (token → empresa do usuário).
 */
class AppClienteController extends Controller
{
    public function __construct(
        private PedidoMobileService $pedidoMobile,
        private PagamentoOnlineService $pagamento,
    ) {
    }

    /** GET /app/v1/produtos — catálogo da empresa (só ativos com preço). */
    public function produtos(Request $request): JsonResponse
    {
        $produtos = Produto::query()->where('ativo', true)
            ->where('empresa_id', $request->user()->empresa_id)
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'preco_venda', 'preco_gasdopovo'])
            ->map(fn (Produto $p) => [
                'id' => $p->id,
                'descricao' => $p->descricao,
                'preco' => (float) $p->preco_venda,
            ]);

        return response()->json(['data' => $produtos]);
    }

    /** POST /app/v1/pedidos — cria pedido do app (cliente por id ou geoloc). */
    public function criarPedido(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'lat' => 'required_without:cliente_id|numeric',
            'lng' => 'required_without:cliente_id|numeric',
            'pedidosituacao_id' => 'required|integer|exists:pedidosituacoes,id',
            'observacao' => 'nullable|string',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|gt:0',
        ]);

        $user = $request->user();
        $pedido = $this->pedidoMobile->criarDoApp($user->empresa_id, $user->grupo_id, $d);

        return response()->json(['data' => ['id' => $pedido->id, 'valor_venda' => (float) $pedido->valor_venda]], 201);
    }

    /** POST /app/v1/pedidos/{id}/pagar — pagamento online (cartão). */
    public function pagar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'token' => 'required|string',
            'parcelas' => 'nullable|integer|min:1|max:12',
        ]);

        $pedido = Pedido::query()->where('empresa_id', $request->user()->empresa_id)->findOrFail($id);
        $pagamento = $this->pagamento->cobrarPedido($pedido, ['token' => $d['token'], 'parcelas' => (int) ($d['parcelas'] ?? 1)]);

        return response()->json([
            'data' => ['situacao' => $pagamento->situacao->value, 'tid' => $pagamento->tid, 'mensagem' => $pagamento->mensagem],
        ], $pagamento->situacao->aprovado() ? 201 : 402);
    }
}
