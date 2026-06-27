<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Mobile\PagamentoOnlineService;
use App\Domain\Mobile\PedidoMobileService;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
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
    ) {}

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

    /** GET /app/v1/pedidos — histórico do cliente autenticado. */
    public function historico(Request $request): JsonResponse
    {
        $user = $request->user();
        $cliente = $this->clienteDoUsuario($request);

        return response()->json([
            'data' => $this->pedidoMobile->historico($user->empresa_id, $cliente->id),
        ]);
    }

    /** GET /app/v1/pedidos/{id} — acompanhar (status atual do pedido). */
    public function acompanhar(Request $request, int $id): JsonResponse
    {
        $cliente = $this->clienteDoUsuario($request);
        $pedido = Pedido::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->where('cliente_id', $cliente->id)
            ->with('situacao:id,descricao,efeito')
            ->findOrFail($id);

        return response()->json(['data' => [
            'id' => $pedido->id,
            'situacao' => $pedido->situacao?->descricao,
            'efeito' => $pedido->situacao?->efeito?->value,
            'valor_venda' => (float) $pedido->valor_venda,
            'datahora' => $pedido->datahora?->toIso8601String(),
        ]]);
    }

    /** POST /app/v1/pedidos/{id}/cancelar — cancela enquanto não concluído. */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $cliente = $this->clienteDoUsuario($request);
        $pedido = Pedido::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->where('cliente_id', $cliente->id)
            ->findOrFail($id);

        $atualizado = $this->pedidoMobile->cancelar($pedido);

        return response()->json(['data' => ['id' => $atualizado->id, 'situacao_id' => $atualizado->pedidosituacao_id]]);
    }

    /** POST /app/v1/pedidos/{id}/avaliar — nota 1–5 + mensagem, ou ignorado. */
    public function avaliar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'mensagem' => 'nullable|string|max:140',
            'ignorado' => 'boolean',
        ]);

        $cliente = $this->clienteDoUsuario($request);
        $pedido = Pedido::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->where('cliente_id', $cliente->id)
            ->findOrFail($id);

        $avaliacao = $this->pedidoMobile->avaliar($pedido, $d);

        return response()->json(['data' => ['id' => $avaliacao->id, 'rating' => $avaliacao->rating, 'ignorado' => $avaliacao->ignorado]], 201);
    }

    /**
     * Resolve o cliente do app a partir do cliente_id informado, sempre escopado pela
     * empresa do token (impede acessar pedidos de outra empresa). O vínculo
     * usuário-app ↔ cliente ainda não existe no schema; até lá o app envia o cliente_id
     * (mesmo modelo do criarPedido). O escopo por empresa garante o isolamento.
     */
    private function clienteDoUsuario(Request $request): Cliente
    {
        $clienteId = (int) $request->query('cliente_id', (string) $request->input('cliente_id', 0));
        if ($clienteId <= 0) {
            abort(422, 'Informe o cliente_id.');
        }

        $cliente = Cliente::query()->where('empresa_id', $request->user()->empresa_id)->find($clienteId);
        if (! $cliente) {
            abort(404, 'Cliente não localizado.');
        }

        return $cliente;
    }
}
