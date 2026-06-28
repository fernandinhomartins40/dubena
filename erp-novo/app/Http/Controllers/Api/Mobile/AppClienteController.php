<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Mobile\CatalogoMobileService;
use App\Domain\Mobile\CotacaoMobileService;
use App\Domain\Mobile\PagamentoOnlineService;
use App\Domain\Mobile\PedidoMobileService;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\EmpresaConfig;
use App\Models\Pedido\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API do app do CLIENTE — N10/F3. Catálogo, cotação (preço server-side), criar pedido
 * (matching geoloc), pagar, perfil/endereço e config. Tenant resolvido pelo middleware.
 */
class AppClienteController extends Controller
{
    public function __construct(
        private PedidoMobileService $pedidoMobile,
        private PagamentoOnlineService $pagamento,
        private CatalogoMobileService $catalogo,
        private CotacaoMobileService $cotacao,
    ) {}

    /**
     * POST /app/v1/carrinho/cotacao — preço do carrinho calculado no SERVIDOR (F3).
     * O app envia só itens (produto_id+quantidade) + condição/cupom/gp; recebe
     * subtotal, desconto e total. Nenhum preço é aceito do cliente.
     */
    public function cotar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer',
            'itens.*.quantidade' => 'required|numeric|gt:0',
            'codigo_cupom' => 'nullable|string|max:40',
            'gasdopovo' => 'boolean',
        ]);

        $user = $request->user();

        return response()->json(['data' => $this->cotacao->cotar($user->empresa_id, $user->grupo_id, $d)]);
    }

    /** GET /app/v1/config — config do app por empresa (vídeo de abertura, Gás do Povo). */
    public function config(Request $request): JsonResponse
    {
        $user = $request->user();
        $cfg = EmpresaConfig::query()->where('empresa_id', $user->empresa_id)->first();
        $dados = (array) ($cfg?->dados ?? []);
        $app = (array) ($dados['app'] ?? []);

        return response()->json(['data' => [
            'gaspovo_ativo' => (bool) ($app['gaspovo_ativo'] ?? false),
            'video' => $app['video'] ?? null, // { url, titulo } ou null
            'tempo_entrega_min' => $cfg?->tempoentrega,
        ]]);
    }

    /** GET /app/v1/perfil/endereco — endereço (inline) do cliente do token. */
    public function obterEndereco(Request $request): JsonResponse
    {
        $cliente = $this->clienteDoUsuario($request);

        return response()->json(['data' => $this->serializarEndereco($cliente)]);
    }

    /** PUT /app/v1/perfil/endereco — atualiza o endereço (inline) do cliente do token. */
    public function atualizarEndereco(Request $request): JsonResponse
    {
        $d = $request->validate([
            'endereco' => 'required|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:120',
            'ponto_referencia' => 'nullable|string|max:160',
            'cep' => 'nullable|string|max:12',
            'uf' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $cliente = $this->clienteDoUsuario($request);
        $cliente->fill($d)->save();

        return response()->json(['data' => $this->serializarEndereco($cliente->refresh())]);
    }

    /** @return array<string,mixed> */
    private function serializarEndereco(Cliente $c): array
    {
        return [
            'endereco' => $c->endereco,
            'numero' => $c->numero,
            'complemento' => $c->complemento,
            'ponto_referencia' => $c->ponto_referencia,
            'cep' => $c->cep,
            'uf' => $c->uf,
            'latitude' => $c->latitude !== null ? (float) $c->latitude : null,
            'longitude' => $c->longitude !== null ? (float) $c->longitude : null,
        ];
    }

    /** GET /app/v1/produtos — catálogo da empresa (só ativos com preço). */
    public function produtos(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->catalogo->produtos($request->user()->empresa_id)]);
    }

    /** GET /app/v1/init — pacote de abertura do app: produtos + condições de pagamento. */
    public function init(Request $request): JsonResponse
    {
        $user = $request->user();
        $apenasGp = $request->boolean('gasdopovo');

        return response()->json(['data' => $this->catalogo->init($user->empresa_id, $user->grupo_id, $apenasGp)]);
    }

    /** GET /app/v1/cupom?codigo= — valida um cupom (promoção com código) vigente. */
    public function cupom(Request $request): JsonResponse
    {
        $codigo = (string) $request->query('codigo', '');
        $promo = $this->catalogo->validarCupom($request->user()->grupo_id, $codigo);

        return response()->json(['data' => [
            'codigo' => $promo->codigo,
            'descricao' => $promo->descricao,
            'desconto_percentual' => (float) $promo->desconto_percentual,
        ]]);
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
            'codigo_cupom' => 'nullable|string|max:40',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|gt:0',
        ]);

        $user = $request->user();
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
     * Resolve o cliente do app. Após a F1 (auth real), o caminho seguro é DERIVAR o
     * cliente do token (cliente.user_id) — sem aceitar cliente_id do cliente, fechando
     * o IDOR. Mantém fallback por cliente_id (escopado pela empresa do token) só para
     * compat durante a transição de clientes ainda não vinculados a um usuário.
     */
    private function clienteDoUsuario(Request $request): Cliente
    {
        $user = $request->user();

        $cliente = Cliente::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('user_id', $user->id)
            ->first();
        if ($cliente) {
            return $cliente;
        }

        // Fallback (transição): cliente_id informado, ainda escopado pela empresa.
        $clienteId = (int) $request->query('cliente_id', (string) $request->input('cliente_id', 0));
        if ($clienteId <= 0) {
            abort(422, 'Cliente do app não vinculado. Refaça o login.');
        }

        $cliente = Cliente::query()->where('empresa_id', $user->empresa_id)->find($clienteId);
        if (! $cliente) {
            abort(404, 'Cliente não localizado.');
        }

        return $cliente;
    }
}
