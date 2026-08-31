<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Logistica\CentralService;
use App\Domain\Logistica\DistribuidorService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Logistica\LogisticaConfig;
use App\Models\Monitora\Veiculo;
use App\Models\Pedido\Pedido;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central de Logística (L1/L3) — fila de distribuição, atribuição/redistribuição,
 * bloqueio de entregador, priorização/reagendamento e sugestões automáticas.
 * RBAC: `logistica.view` (ler) / `logistica.distribuir` (agir).
 */
class CentralController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(
        private CentralService $central,
        private DistribuidorService $distribuidor,
        private TenantContext $tenant,
    ) {}

    /** GET /central/fila — pedidos pendentes (bandeja de distribuição). */
    public function fila(Request $request): JsonResponse
    {
        $this->autorizar($request, 'logistica.view');
        $empresaId = $this->tenant->requireEmpresaId();

        $pedidos = $this->central->filaDistribuicao($empresaId, [
            'incluir_atribuidos' => $request->boolean('incluir_atribuidos'),
            'setor_id' => $request->integer('setor_id') ?: null,
        ])->map(fn (Pedido $p) => [
            'id' => $p->id,
            'cliente' => $p->cliente?->nome,
            // F3-01: ponto unico (a coluna `endereco` esta NULL em toda a base).
            'endereco' => $p->cliente?->endereco_completo,
            'lat' => $p->cliente?->latitude !== null ? (float) $p->cliente->latitude : null,
            'lng' => $p->cliente?->longitude !== null ? (float) $p->cliente->longitude : null,
            'valor_venda' => (float) $p->valor_venda,
            'urgente' => (bool) $p->entrega_urgente,
            'datahora' => $p->datahora?->toIso8601String(),
            'situacao' => $p->situacao?->descricao,
            'entregador' => $p->entregador ? ['id' => $p->entregador->id, 'nome' => $p->entregador->name] : null,
        ]);

        return response()->json(['data' => $pedidos]);
    }

    /** GET /central/entregadores — estado logístico dos entregadores. */
    public function entregadores(Request $request): JsonResponse
    {
        $this->autorizar($request, 'logistica.view');

        return response()->json(['data' => $this->central->entregadores($this->tenant->requireEmpresaId())]);
    }

    /** GET /central/pedidos/{id}/sugestoes — ranking de entregadores (L3). */
    public function sugestoes(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'logistica.view');
        $pedido = $this->pedido($request, $id);

        return response()->json(['data' => $this->distribuidor->ranquear($pedido)]);
    }

    /** POST /central/pedidos/{id}/atribuir — atribui a um entregador. */
    public function atribuir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'logistica.distribuir');
        $d = $request->validate([
            'entregador_user_id' => 'required|integer|exists:users,id',
            'veiculo_id' => ['nullable', 'integer', new ExisteNoTenant(Veiculo::class)],
            'motivo' => 'nullable|string|max:255',
        ]);

        $pedido = $this->central->atribuir(
            $this->pedido($request, $id),
            $this->tenant->requireEmpresaId(),
            (int) $d['entregador_user_id'],
            $d['veiculo_id'] ?? null,
            (int) $request->user()->id,
            false,
            $d['motivo'] ?? null,
        );

        return response()->json(['data' => ['id' => $pedido->id, 'entregador_user_id' => $pedido->entregador_user_id, 'veiculo_id' => $pedido->veiculo_id]]);
    }

    /** POST /central/pedidos/{id}/redistribuir — troca de entregador. */
    public function redistribuir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'logistica.distribuir');
        $d = $request->validate([
            'entregador_user_id' => 'required|integer|exists:users,id',
            'motivo' => 'nullable|string|max:255',
        ]);

        $pedido = $this->central->redistribuir(
            $this->pedido($request, $id),
            $this->tenant->requireEmpresaId(),
            (int) $d['entregador_user_id'],
            (int) $request->user()->id,
            $d['motivo'] ?? null,
        );

        return response()->json(['data' => ['id' => $pedido->id, 'entregador_user_id' => $pedido->entregador_user_id]]);
    }

    /** POST /central/pedidos/{id}/priorizar — marca/desmarca urgente. */
    public function priorizar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'logistica.distribuir');
        $urgente = $request->boolean('urgente', true);
        $pedido = $this->central->priorizar($this->pedido($request, $id), $urgente);

        return response()->json(['data' => ['id' => $pedido->id, 'urgente' => (bool) $pedido->entrega_urgente]]);
    }

    /** POST /central/pedidos/{id}/reagendar — nova data/hora prevista. */
    public function reagendar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'logistica.distribuir');
        $d = $request->validate(['quando' => 'required|date']);
        $pedido = $this->central->reagendar($this->pedido($request, $id), new \DateTimeImmutable($d['quando']));

        return response()->json(['data' => ['id' => $pedido->id, 'datahora' => $pedido->datahora?->toIso8601String()]]);
    }

    /** POST /central/entregadores/{id}/bloquear — bloqueia na distribuição. */
    public function bloquear(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'logistica.distribuir');
        $d = $request->validate([
            'motivo' => 'nullable|string|max:255',
            'ate' => 'nullable|date|after:now',
        ]);

        $bloqueio = $this->central->bloquearEntregador(
            $this->tenant->requireEmpresaId(),
            $id,
            (int) $request->user()->id,
            $d['motivo'] ?? null,
            isset($d['ate']) ? new \DateTimeImmutable($d['ate']) : null,
        );

        return response()->json(['data' => ['id' => $bloqueio->id]], 201);
    }

    /** DELETE /central/entregadores/{id}/bloquear — desbloqueia. */
    public function desbloquear(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'logistica.distribuir');
        $this->central->desbloquearEntregador($this->tenant->requireEmpresaId(), $id);

        return response()->json(['message' => 'Entregador desbloqueado.']);
    }

    /** GET /central/config — configuração da distribuição (modo/pesos). */
    public function config(Request $request): JsonResponse
    {
        $this->autorizar($request, 'logistica.view');
        $empresaId = $this->tenant->requireEmpresaId();
        $config = LogisticaConfig::query()->firstOrCreate(['empresa_id' => $empresaId]);

        return response()->json(['data' => $config]);
    }

    /** PUT /central/config — atualiza modo/pesos/limites. */
    public function salvarConfig(Request $request): JsonResponse
    {
        $this->autorizar($request, 'logistica.config');
        $d = $request->validate([
            'modo' => 'required|in:sugerir,auto',
            'peso_distancia' => 'nullable|numeric|min:0|max:1',
            'peso_carga' => 'nullable|numeric|min:0|max:1',
            'raio_maximo_km' => 'nullable|integer|min:1',
            'teto_carga' => 'nullable|integer|min:1',
            'ociosidade_min' => 'nullable|integer|min:1|max:480',
        ]);

        $empresaId = $this->tenant->requireEmpresaId();
        $config = LogisticaConfig::query()->updateOrCreate(['empresa_id' => $empresaId], $d);

        return response()->json(['data' => $config]);
    }

    /** Resolve o pedido tenant-scoped (anti-IDOR: global scope + empresa). */
    private function pedido(Request $request, int $id): Pedido
    {
        return Pedido::query()
            ->where('empresa_id', $this->tenant->requireEmpresaId())
            ->findOrFail($id);
    }
}
