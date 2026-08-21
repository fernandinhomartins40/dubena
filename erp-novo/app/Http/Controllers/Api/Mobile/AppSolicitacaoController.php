<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Venda\CentralVendasService;
use App\Domain\Venda\ExtratoRemuneracaoService;
use App\Http\Controllers\Controller;
use App\Models\Venda\PedidoSolicitacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solicitação de venda pelo app (F4) — o caminho do franqueado/industrial.
 *
 * O vendedor em campo monta o pedido e pede o desconto; a Central decide. Nada
 * aqui move estoque ou financeiro: a solicitação é rascunho até o aceite.
 *
 * **O preço vem do servidor, como no resto do app.** O contrato aceita
 * `produto_id` e `quantidade`; o `preco_unitario` é resolvido a partir do
 * cadastro. Aceitar preço do cliente reproduziria o buraco do legado, onde
 * `MobileRepository::getPreco:602` devolve o valor que o app mandou
 * (`if ($isAppNf) return $preco`) e o vendedor define a própria margem.
 */
class AppSolicitacaoController extends Controller
{
    public function __construct(private CentralVendasService $central) {}

    /** GET /app/v1/entregador/solicitacoes — as próprias solicitações. */
    public function index(Request $request): JsonResponse
    {
        $solicitacoes = PedidoSolicitacao::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->where('solicitante_user_id', $request->user()->id)
            ->with(['cliente:id,nome', 'pedido:id'])
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['data' => $solicitacoes]);
    }

    /** POST /app/v1/entregador/solicitacoes — pede à Central. */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cliente_id' => 'required|integer|exists:clientes,id',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|gt:0',
            'condicaopagamento_id' => 'nullable|integer|exists:condicaopagamentos,id',
            'setor_id' => 'nullable|integer|exists:setores,id',
            'desconto_solicitado' => 'nullable|numeric|min:0',
            'justificativa' => 'nullable|string|max:500',
            'observacao' => 'nullable|string|max:255',
        ]);

        $s = $this->central->solicitar($request->user(), $d);

        return response()->json(['data' => $s], 201);
    }

    /**
     * GET /app/v1/entregador/extrato — quanto ganhei no período (F5).
     *
     * Sempre do PRÓPRIO usuário: o extrato sai de `$request->user()->id`, nunca
     * de um id do payload. Deixar o app escolher de quem é o extrato exporia o
     * ganho de um colega — e é o tipo de coisa que o legado permitia
     * (NfwebController::savePedido:329 confia no `colaborador_id` do corpo).
     */
    public function extrato(Request $request, ExtratoRemuneracaoService $extrato): JsonResponse
    {
        $d = $request->validate([
            'inicio' => 'nullable|date',
            'fim' => 'nullable|date|after_or_equal:inicio',
        ]);

        // Padrão: mês corrente — o recorte que o franqueado quer ver ao abrir.
        $inicio = $d['inicio'] ?? now()->startOfMonth()->toDateString();
        $fim = $d['fim'] ?? now()->toDateString();

        return response()->json([
            'data' => $extrato->doColaborador(
                (int) $request->user()->empresa_id,
                (int) $request->user()->id,
                $inicio,
                $fim,
            ),
        ]);
    }

    /** POST /app/v1/entregador/solicitacoes/{id}/cancelar — desistiu na porta. */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $s = PedidoSolicitacao::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id);

        return response()->json(['data' => $this->central->cancelar($s, $request->user())]);
    }
}
