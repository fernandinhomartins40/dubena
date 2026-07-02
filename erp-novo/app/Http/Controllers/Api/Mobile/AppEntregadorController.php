<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Logistica\JornadaService;
use App\Domain\Mobile\EntregaService;
use App\Domain\Mobile\PedidoMobileService;
use App\Domain\Mobile\RastreamentoService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Http\Controllers\Controller;
use App\Models\Logistica\Jornada;
use App\Models\Monitora\Veiculo;
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
        private JornadaService $jornadas,
        private \App\Domain\Logistica\RoteirizadorService $roteirizador,
    ) {}

    /** GET /app/v1/entregador/rota — rota otimizada das entregas ativas (L5). */
    public function rota(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $this->roteirizador->rotaDoEntregador($user->empresa_id, $user->id)]);
    }

    // ── Jornada (L4) ──

    /** GET /app/v1/entregador/veiculos — veículos ativos da empresa (seleção no início da jornada). */
    public function veiculos(Request $request): JsonResponse
    {
        $veiculos = Veiculo::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->where('ativo', true)
            ->orderBy('placa')
            ->get(['id', 'placa', 'descricao', 'km_atual'])
            ->map(fn (Veiculo $v) => [
                'id' => $v->id, 'placa' => $v->placa, 'descricao' => $v->descricao, 'km_atual' => $v->km_atual,
            ]);

        return response()->json(['data' => $veiculos]);
    }

    /** GET /app/v1/entregador/jornada — jornada ativa (ou null). */
    public function jornadaAtual(Request $request): JsonResponse
    {
        $jornada = $this->jornadas->jornadaAtiva($request->user()->id);

        return response()->json(['data' => $this->serializarJornada($jornada)]);
    }

    /** POST /app/v1/entregador/jornada/iniciar — abre a jornada com veículo + checklist. */
    public function iniciarJornada(Request $request): JsonResponse
    {
        $d = $request->validate([
            'veiculo_id' => 'nullable|integer|exists:monitora_veiculos,id',
            'km_inicial' => 'nullable|integer|min:0',
            'checklist' => 'nullable|array',
        ]);

        $jornada = $this->jornadas->iniciar($request->user(), $d['veiculo_id'] ?? null, $d['checklist'] ?? null, $d['km_inicial'] ?? null);

        return response()->json(['data' => $this->serializarJornada($jornada)], 201);
    }

    /** POST /app/v1/entregador/jornada/encerrar — fecha a jornada (km final opcional). */
    public function encerrarJornada(Request $request): JsonResponse
    {
        $d = $request->validate(['km_final' => 'nullable|integer|min:0']);
        $jornada = $this->jornadas->exigirJornadaAtiva($request->user()->id);
        $encerrada = $this->jornadas->encerrar($jornada, $d['km_final'] ?? null);

        return response()->json(['data' => $this->serializarJornada($encerrada)]);
    }

    /** GET /app/v1/entregador/dashboard — resumo do dia. */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $hoje = now()->startOfDay();

        $base = Pedido::query()->where('empresa_id', $user->empresa_id)->where('entregador_user_id', $user->id);

        $pendentes = (clone $base)->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::PENDENTE->value))->count();
        $concluidosHoje = (clone $base)
            ->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::CONCLUIDO->value))
            ->where('datahora_acao', '>=', $hoje)->count();

        $jornada = $this->jornadas->jornadaAtiva($user->id);

        return response()->json(['data' => [
            'em_servico' => $jornada !== null,
            'jornada' => $this->serializarJornada($jornada),
            'pendentes' => $pendentes,
            'concluidos_hoje' => $concluidosHoje,
        ]]);
    }

    private function serializarJornada(?Jornada $j): ?array
    {
        if ($j === null) {
            return null;
        }
        $j->loadMissing('veiculo:id,placa,descricao');

        return [
            'id' => $j->id,
            'status' => $j->status,
            'iniciada_em' => $j->iniciada_em?->toIso8601String(),
            'encerrada_em' => $j->encerrada_em?->toIso8601String(),
            'km_inicial' => $j->km_inicial,
            'km_final' => $j->km_final,
            'veiculo' => $j->veiculo ? ['id' => $j->veiculo->id, 'placa' => $j->veiculo->placa, 'descricao' => $j->veiculo->descricao] : null,
        ];
    }

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

        // L4 — a posição só conta durante a JORNADA (evita GPS "fantasma" fora do
        // expediente; a Central só vê quem está de fato em serviço).
        $this->jornadas->exigirJornadaAtiva($user->id);

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
