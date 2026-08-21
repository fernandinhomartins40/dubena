<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Venda\CentralVendasService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Venda\PedidoSolicitacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central de Vendas (F3) — a fila de solicitações do campo e a decisão sobre elas.
 *
 * RBAC: `venda.solicitacao.view` (ler) / `venda.aprovar` (decidir) /
 * `venda.faturar` (faturar). Separadas de propósito: o atendente que triagem a
 * fila não precisa ser quem aprova desconto, e aprovar não é faturar.
 */
class CentralVendasController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private CentralVendasService $central) {}

    /** GET /central-vendas/solicitacoes — a fila. */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'venda.view');

        $solicitacoes = $this->central->fila((int) $request->user()->empresa_id, [
            'situacao' => $request->string('situacao')->toString() ?: null,
            'solicitante_user_id' => $request->integer('solicitante_user_id') ?: null,
        ]);

        return response()->json(['data' => $solicitacoes]);
    }

    /** GET /central-vendas/solicitacoes/{id} — detalhe + análise de alçada. */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'venda.view');
        $s = $this->localizar($request, $id);

        return response()->json([
            'data' => $s->load(['cliente:id,nome', 'solicitante:id,name', 'pedido:id']),
            // O atendente decide melhor sabendo se o vendedor pedia algo fora do
            // normal ou apenas o que não podia conceder sozinho.
            'alcada' => $this->central->analiseDeAlcada($s),
        ]);
    }

    /** POST /central-vendas/solicitacoes/{id}/aprovar */
    public function aprovar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'venda.aprovar');
        $d = $request->validate([
            'desconto_aprovado' => 'nullable|numeric|min:0',
            'motivo' => 'nullable|string|max:500',
        ]);

        $s = $this->central->aprovar(
            $this->localizar($request, $id),
            $request->user(),
            isset($d['desconto_aprovado']) ? (float) $d['desconto_aprovado'] : null,
            $d['motivo'] ?? null,
        );

        return response()->json(['data' => $s->load('pedido:id')]);
    }

    /** POST /central-vendas/solicitacoes/{id}/recusar */
    public function recusar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'venda.aprovar');
        $d = $request->validate(['motivo' => 'nullable|string|max:500']);

        $s = $this->central->recusar($this->localizar($request, $id), $request->user(), $d['motivo'] ?? null);

        return response()->json(['data' => $s]);
    }

    /** POST /central-vendas/solicitacoes/{id}/faturar */
    public function faturar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'venda.faturar');

        $s = $this->central->faturar($this->localizar($request, $id), $request->user());

        return response()->json(['data' => $s->load('pedido:id,pedidosituacao_id')]);
    }

    /**
     * Anti-IDOR: a solicitação tem de ser da empresa do token. A RLS já barra no
     * banco, mas achar aqui devolve 404 em vez de um erro de baixo nível.
     */
    private function localizar(Request $request, int $id): PedidoSolicitacao
    {
        return PedidoSolicitacao::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id);
    }
}
