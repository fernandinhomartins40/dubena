<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Mobile\EntregaService;
use App\Domain\Mobile\PedidoMobileService;
use App\Domain\Mobile\RastreamentoService;
use App\Domain\Pedido\PedidoService;
use App\Http\Controllers\Controller;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API do app do ENTREGADOR — N10/P6/P7. Sincroniza pedidos, atualiza status,
 * transmite a POSIÇÃO em tempo real e fecha o ciclo da entrega (aceite/recusa,
 * ocorrência, comprovação com foto/assinatura, conclusão). Auth real (Sanctum).
 */
class AppEntregadorController extends Controller
{
    public function __construct(
        private PedidoService $pedidos,
        private PedidoMobileService $pedidoMobile,
        private RastreamentoService $rastreamento,
        private EntregaService $entrega,
    ) {}

    /** Resolve um pedido do entregador autenticado (anti-IDOR: empresa + entregador). */
    private function pedidoDoEntregador(Request $request, int $id): Pedido
    {
        $user = $request->user();

        return Pedido::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('entregador_user_id', $user->id)
            ->findOrFail($id);
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

        // Segurança (fix da auditoria §6): a situação destino tem de ser do MESMO
        // grupo do pedido. O `exists` da validação não garante isso — sem este
        // check, o entregador poderia mover seu pedido para uma situação de outra
        // rede (id válido, grupo errado).
        $situacaoOk = PedidoSituacao::query()
            ->where('id', $d['pedidosituacao_id'])
            ->where('grupo_id', $pedido->grupo_id)
            ->exists();
        abort_unless($situacaoOk, 422, 'Situação inválida para este pedido.');

        $atualizado = $this->pedidos->mudarSituacao($pedido, $d['pedidosituacao_id'], $user->id);

        // Notifica o cliente (push) sobre a nova situação (F5).
        $this->pedidoMobile->notificarStatus($atualizado->load(['cliente', 'situacao']));

        return response()->json(['data' => ['id' => $atualizado->id, 'situacao_id' => $atualizado->pedidosituacao_id]]);
    }

    /**
     * POST /app/v1/entregador/posicao — ping de geolocalização (P6). Persiste o
     * snapshot e publica a posição nos pedidos ATIVOS do entregador (tempo real).
     */
    public function posicao(Request $request): JsonResponse
    {
        $d = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'velocidade' => 'nullable|numeric|min:0',
            'direcao' => 'nullable|integer|min:0|max:359',
        ]);

        $user = $request->user();
        $notificados = $this->rastreamento->registrarPing($user->empresa_id, $user->id, $d);

        return response()->json(['data' => ['pedidos_notificados' => $notificados]]);
    }

    // ── Ciclo da entrega (P7) ──

    /** POST /app/v1/entregador/pedidos/{id}/aceitar — aceite da corrida. */
    public function aceitar(Request $request, int $id): JsonResponse
    {
        $pedido = $this->entrega->aceitar($this->pedidoDoEntregador($request, $id));

        return response()->json(['data' => ['id' => $pedido->id]]);
    }

    /** POST /app/v1/entregador/pedidos/{id}/recusar — recusa (gera ocorrência, desvincula). */
    public function recusar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['motivo' => 'nullable|string|max:255']);
        $pedido = $this->pedidoDoEntregador($request, $id);
        $ocorrencia = $this->entrega->recusar($pedido, $request->user()->id, $d['motivo'] ?? null);

        return response()->json(['data' => ['ocorrencia_id' => $ocorrencia->id]], 201);
    }

    /** POST /app/v1/entregador/pedidos/{id}/ocorrencia — registra imprevisto (+ foto). */
    public function ocorrencia(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'tipo' => 'required|string|max:40',
            'descricao' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'foto' => 'nullable|image|max:8192',
        ]);

        $pedido = $this->pedidoDoEntregador($request, $id);
        $ocorrencia = $this->entrega->registrarOcorrencia($pedido, $request->user()->id, $d, $request->file('foto'));

        return response()->json(['data' => ['id' => $ocorrencia->id, 'tipo' => $ocorrencia->tipo]], 201);
    }

    /** POST /app/v1/entregador/pedidos/{id}/concluir — comprovação + conclui a entrega. */
    public function concluir(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'recebido_por' => 'nullable|string|max:160',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'foto' => 'nullable|image|max:8192',
            'assinatura' => 'nullable|image|max:4096',
        ]);

        $pedido = $this->pedidoDoEntregador($request, $id);
        $comprovacao = $this->entrega->concluir(
            $pedido, $request->user()->id, $d, $request->file('foto'), $request->file('assinatura'),
        );

        // Notifica o cliente (push) que o pedido foi entregue.
        $this->pedidoMobile->notificarStatus($pedido->fresh()->load(['cliente', 'situacao']));

        return response()->json(['data' => ['comprovacao_id' => $comprovacao->id, 'concluido' => true]], 201);
    }
}
