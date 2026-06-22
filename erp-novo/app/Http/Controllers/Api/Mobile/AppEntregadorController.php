<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Pedido\PedidoService;
use App\Http\Controllers\Controller;
use App\Models\Pedido\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API do app do ENTREGADOR — N10. Sincroniza pedidos a entregar e atualiza o
 * status (com geolocalização). Auth real do colaborador (token Sanctum).
 */
class AppEntregadorController extends Controller
{
    public function __construct(private PedidoService $pedidos)
    {
    }

    /** GET /app/v1/entregador/pedidos — pedidos do entregador autenticado. */
    public function pedidos(Request $request): JsonResponse
    {
        $user = $request->user();

        $pedidos = Pedido::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('entregador_user_id', $user->id)
            ->with(['cliente:id,nome,endereco,numero,latitude,longitude', 'situacao:id,descricao,efeito'])
            ->orderByDesc('datahora')->limit(100)->get()
            ->map(fn (Pedido $p) => [
                'id' => $p->id,
                'valor_venda' => (float) $p->valor_venda,
                'situacao' => $p->situacao?->descricao,
                'cliente' => $p->cliente?->nome,
                'endereco' => trim(($p->cliente?->endereco ?? '').', '.($p->cliente?->numero ?? '')),
                'lat' => $p->cliente?->latitude !== null ? (float) $p->cliente->latitude : null,
                'lng' => $p->cliente?->longitude !== null ? (float) $p->cliente->longitude : null,
            ]);

        return response()->json(['data' => $pedidos]);
    }

    /** POST /app/v1/entregador/pedidos/{id}/status — muda situação (registra geoloc). */
    public function atualizarStatus(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'pedidosituacao_id' => 'required|integer|exists:pedidosituacoes,id',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $user = $request->user();
        $pedido = Pedido::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('entregador_user_id', $user->id)
            ->findOrFail($id);

        $atualizado = $this->pedidos->mudarSituacao($pedido, $d['pedidosituacao_id'], $user->id);

        return response()->json(['data' => ['id' => $atualizado->id, 'situacao_id' => $atualizado->pedidosituacao_id]]);
    }
}
