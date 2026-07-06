<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Cobranca\PixService;
use App\Domain\Logistica\Contracts\TracadorRota;
use App\Domain\Mobile\PagamentoOnlineService;
use App\Domain\Mobile\PedidoMobileService;
use App\Http\Controllers\Api\Mobile\Concerns\ResolveClienteDoApp;
use App\Http\Controllers\Controller;
use App\Models\Pedido\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * App do cliente — PEDIDO (B-1): criar, pagar (cartão/PIX), histórico, acompanhar,
 * rota do entregador, cancelar e avaliar. Extraído do AppClienteController —
 * mesmas rotas/contrato. O cliente é sempre derivado do token (anti-IDOR).
 */
class AppPedidoController extends Controller
{
    use ResolveClienteDoApp;

    public function __construct(
        private PedidoMobileService $pedidoMobile,
        private PagamentoOnlineService $pagamento,
        private PixService $pix,
    ) {}

    /** POST /app/v1/pedidos — cria pedido do app (cliente por id ou geoloc). */
    public function criarPedido(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'lat' => 'required_without:cliente_id|numeric',
            'lng' => 'required_without:cliente_id|numeric',
            'pedidosituacao_id' => 'nullable|integer|exists:pedidosituacoes,id',
            'condicaopagamento_id' => 'nullable|integer|exists:condicaopagamentos,id',
            'gasdopovo' => 'boolean',
            'observacao' => 'nullable|string',
            'codigo_cupom' => 'nullable|string|max:40',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|gt:0',
            // Nota: preco_unitario do cliente é IGNORADO de propósito (anti-fraude F3c).
        ]);

        $user = $request->user();

        // F4 (segurança): usuário do app COM cliente vinculado só cria pedido para
        // SI MESMO — cliente_id do payload (e o matching por geoloc, que poderia
        // casar o vizinho) valem apenas para o fluxo staff/transição sem vínculo.
        $clienteDoToken = \App\Models\Cliente\Cliente::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('user_id', $user->id)
            ->value('id');
        if ($clienteDoToken !== null) {
            $d['cliente_id'] = $clienteDoToken;
        }

        $pedido = $this->pedidoMobile->criarDoApp($user->empresa_id, $user->grupo_id, $d);

        return response()->json(['data' => [
            'id' => $pedido->id,
            'valor_venda' => (float) $pedido->valor_venda,
            'valor_desconto' => (float) $pedido->valor_desconto,
        ]], 201);
    }

    /** POST /app/v1/pedidos/{id}/pagar — pagamento online (cartão). */
    public function pagar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'token' => 'required|string',
            'parcelas' => 'nullable|integer|min:1|max:12',
        ]);

        // F4 (segurança): mesmo escopo anti-IDOR do gerarPix — o cliente só paga o
        // PRÓPRIO pedido (antes, qualquer cliente da empresa disparava a cobrança).
        $cliente = $this->clienteDoUsuario($request);
        $pedido = Pedido::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->where('cliente_id', $cliente->id)
            ->findOrFail($id);
        $pagamento = $this->pagamento->cobrarPedido($pedido, ['token' => $d['token'], 'parcelas' => (int) ($d['parcelas'] ?? 1)]);

        return response()->json([
            'data' => ['situacao' => $pagamento->situacao->value, 'tid' => $pagamento->tid, 'mensagem' => $pagamento->mensagem],
        ], $pagamento->situacao->aprovado() ? 201 : 402);
    }

    /**
     * POST /app/v1/pedidos/{id}/pix — gera (ou reaproveita) a cobrança PIX do pedido (F4).
     * Devolve copia-e-cola/QR e expiração; o app faz polling em /pix/status.
     */
    public function gerarPix(Request $request, int $id): JsonResponse
    {
        $cliente = $this->clienteDoUsuario($request);
        $pedido = Pedido::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->where('cliente_id', $cliente->id)
            ->findOrFail($id);

        $cobranca = $this->pix->criarCobrancaPedido($pedido);

        return response()->json(['data' => [
            'txid' => $cobranca->txid,
            'copia_e_cola' => $cobranca->copia_e_cola,
            'qrcode' => $cobranca->qrcode,
            'valor' => (float) $cobranca->valor,
            'expira_em' => $cobranca->expira_em?->toIso8601String(),
            'situacao' => $cobranca->situacao->value,
        ]], 201);
    }

    /** GET /app/v1/pedidos/{id}/pix/status — status da cobrança PIX (polling). */
    public function statusPix(Request $request, int $id): JsonResponse
    {
        $cliente = $this->clienteDoUsuario($request);
        $pedido = Pedido::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->where('cliente_id', $cliente->id)
            ->findOrFail($id);

        $cobranca = $this->pix->statusDoPedido($pedido);

        return response()->json(['data' => [
            'situacao' => $cobranca?->situacao->value,
            'pago' => $cobranca?->situacao->value === 'CONCLUIDA',
            'pago_em' => $cobranca?->pago_em?->toIso8601String(),
        ]]);
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
            ->with([
                'situacao:id,descricao,efeito',
                'itens:id,pedido_id,produto_id,quantidade,preco_unitario',
                'itens.produto:id,descricao',
                'entregador:id,name',
                'veiculo:id,placa,descricao',
            ])
            ->findOrFail($id);

        return response()->json(['data' => [
            'id' => $pedido->id,
            'situacao' => $pedido->situacao?->descricao,
            'efeito' => $pedido->situacao?->efeito?->value,
            'valor_venda' => (float) $pedido->valor_venda,
            'datahora' => $pedido->datahora?->toIso8601String(),
            // L6 — quem está trazendo (só nome + veículo; sem dados sensíveis).
            // A posição em tempo real vem pelo canal pedido.{id}.entregador (P6).
            'entregador' => $pedido->entregador ? [
                'nome' => $pedido->entregador->name,
                'veiculo' => $pedido->veiculo ? trim(($pedido->veiculo->descricao ?? '').' '.($pedido->veiculo->placa ?? '')) : null,
            ] : null,
            'itens' => $pedido->itens->map(fn ($i) => [
                'produto_id' => $i->produto_id,
                'descricao' => $i->produto?->descricao,
                'quantidade' => (float) $i->quantidade,
                'preco_unitario' => (float) $i->preco_unitario,
                'total' => round((float) $i->quantidade * (float) $i->preco_unitario, 2),
            ]),
        ]]);
    }

    /**
     * GET /app/v1/pedidos/{id}/rota-entregador — L6: acompanhamento estilo app de
     * mobilidade. Posição AO VIVO do entregador (vem dos pings do app dele — zero
     * custo Google) + TRAÇADO pelas ruas até o cliente (Routes API com cache
     * persistente por célula: enquanto o entregador não anda ~100 m, nenhuma
     * chamada nova). ETA/distância reais.
     */
    public function rotaEntregador(Request $request, int $id): JsonResponse
    {
        $cliente = $this->clienteDoUsuario($request);
        $pedido = Pedido::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->where('cliente_id', $cliente->id)
            ->with(['entregador:id,name', 'veiculo:id,placa,descricao'])
            ->findOrFail($id);

        $vazio = [
            'entregador' => null, 'posicao' => null, 'destino' => null,
            'polyline' => null, 'distancia_km' => null, 'duracao_min' => null,
        ];

        if (! $pedido->entregador_user_id) {
            return response()->json(['data' => $vazio]);
        }

        $pos = \App\Models\Mobile\EntregadorPosicao::query()
            ->where('entregador_user_id', $pedido->entregador_user_id)
            ->first();

        $destLat = $cliente->latitude !== null ? (float) $cliente->latitude : null;
        $destLng = $cliente->longitude !== null ? (float) $cliente->longitude : null;

        $tracado = null;
        if ($pos && $destLat !== null && $destLng !== null) {
            $tracado = app(TracadorRota::class)->tracar(
                (float) $pos->latitude, (float) $pos->longitude, $destLat, $destLng,
            );
        }

        return response()->json(['data' => [
            'entregador' => [
                'nome' => $pedido->entregador?->name,
                'veiculo' => $pedido->veiculo ? trim(($pedido->veiculo->descricao ?? '').' '.($pedido->veiculo->placa ?? '')) : null,
            ],
            'posicao' => $pos ? [
                'lat' => (float) $pos->latitude,
                'lng' => (float) $pos->longitude,
                'atualizado_em' => $pos->atualizado_em?->toIso8601String(),
            ] : null,
            'destino' => $destLat !== null ? ['lat' => $destLat, 'lng' => $destLng] : null,
            'polyline' => $tracado['polyline'] ?? null,
            'distancia_km' => $tracado['distancia_km'] ?? null,
            'duracao_min' => $tracado['duracao_min'] ?? null,
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
}
