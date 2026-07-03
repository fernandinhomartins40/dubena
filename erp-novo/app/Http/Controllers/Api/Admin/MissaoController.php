<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Missao\MissaoService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Missao\Missao;
use App\Models\Missao\MissaoAtribuicao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Missões (L7/L9) — administração e AUDITORIA. O operador cria os moldes de
 * missão, acompanha as execuções (visitas, trilha GPS, evidências, métricas),
 * aprova/reprova/pede revisão (com sanção auditável) e decide os ADIAMENTOS
 * (ETAPA 11). RBAC: missao.view/create/edit/aprovar.
 */
class MissaoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private MissaoService $missoes) {}

    // ── Moldes de missão ──

    /** GET /missoes — moldes da empresa. */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'missao.view');

        return response()->json(['data' => Missao::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->withCount('atribuicoes')
            ->orderByDesc('id')->get()]);
    }

    /** POST /missoes — cria o molde. */
    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'missao.create');
        $d = $this->validar($request);
        $d['empresa_id'] = $request->user()->empresa_id;
        $d['grupo_id'] = $request->user()->grupo_id;

        return response()->json(['data' => Missao::create($d)], 201);
    }

    /** PUT /missoes/{id} — atualiza o molde. */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'missao.edit');
        $missao = $this->missao($request, $id);
        $missao->update($this->validar($request));

        return response()->json(['data' => $missao->refresh()]);
    }

    /** POST /missoes/{id}/atribuir — atribuição MANUAL a um entregador. */
    public function atribuir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'missao.edit');
        $d = $request->validate(['entregador_user_id' => 'required|integer|exists:users,id']);
        $missao = $this->missao($request, $id);

        $atr = MissaoAtribuicao::create([
            'empresa_id' => $missao->empresa_id,
            'missao_id' => $missao->id,
            'entregador_user_id' => (int) $d['entregador_user_id'],
            'status' => 'atribuida',
            'automatica' => false,
        ]);

        return response()->json(['data' => ['id' => $atr->id]], 201);
    }

    // ── Auditoria das execuções (L9) ──

    /** GET /missoes/atribuicoes?status= — execuções com métricas. */
    public function atribuicoes(Request $request): JsonResponse
    {
        $this->autorizar($request, 'missao.view');

        $lista = MissaoAtribuicao::query()
            ->where('empresa_id', $request->user()->empresa_id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with(['missao:id,tipo,titulo', 'entregador:id,name'])
            ->withCount('visitas')
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn (MissaoAtribuicao $a) => [
                'id' => $a->id,
                'status' => $a->status,
                'automatica' => (bool) $a->automatica,
                'missao' => $a->missao?->titulo,
                'tipo' => $a->missao?->tipo,
                'entregador' => $a->entregador?->name,
                'visitas' => $a->visitas_count,
                'iniciada_em' => $a->iniciada_em?->toIso8601String(),
                'concluida_em' => $a->concluida_em?->toIso8601String(),
                'adiamento' => $a->adiamento_motivo ? [
                    'motivo' => $a->adiamento_motivo, 'detalhe' => $a->adiamento_detalhe,
                    'em' => $a->adiada_em?->toIso8601String(), 'aprovacao' => $a->adiamento_aprovacao,
                ] : null,
                'auditoria' => $a->auditoria_resultado ? [
                    'resultado' => $a->auditoria_resultado, 'sancao' => $a->auditoria_sancao,
                    'observacao' => $a->auditoria_observacao, 'em' => $a->auditoria_em?->toIso8601String(),
                ] : null,
            ]);

        return response()->json(['data' => $lista]);
    }

    /** GET /missoes/atribuicoes/{id} — detalhe: visitas, trilha, evidências, métricas. */
    public function detalhe(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'missao.view');
        $atr = $this->atribuicao($request, $id);
        $atr->load(['missao', 'entregador:id,name', 'visitas.evidencias', 'visitas.cliente:id,nome']);

        return response()->json(['data' => [
            'id' => $atr->id,
            'status' => $atr->status,
            'missao' => ['titulo' => $atr->missao?->titulo, 'tipo' => $atr->missao?->tipo],
            'entregador' => $atr->entregador?->name,
            'metricas' => $this->missoes->metricas($atr),
            'visitas' => $atr->visitas->map(fn ($v) => [
                'id' => $v->id,
                'status' => $v->status,
                'cliente' => $v->cliente?->nome,
                'pedido_id' => $v->pedido_id,
                'lat' => $v->latitude !== null ? (float) $v->latitude : null,
                'lng' => $v->longitude !== null ? (float) $v->longitude : null,
                'em' => $v->finalizada_em?->toIso8601String(),
                'observacao' => $v->observacao,
                'evidencias' => $v->evidencias->map(fn ($e) => ['id' => $e->id, 'tipo' => $e->tipo]),
            ]),
            'trilha' => $atr->trilha()->orderBy('registrado_em')->limit(2000)
                ->get(['latitude', 'longitude', 'registrado_em'])
                ->map(fn ($p) => ['lat' => (float) $p->latitude, 'lng' => (float) $p->longitude, 'em' => $p->registrado_em?->toIso8601String()]),
        ]]);
    }

    /** GET /missoes/evidencias/{id} — a foto (storage privado, streaming). */
    public function evidencia(Request $request, int $id): Response
    {
        $this->autorizar($request, 'missao.view');

        // MissaoEvidencia é BelongsToTenant: o global scope já filtra pela empresa
        // ATIVA (respeitando a troca de empresa via X-Empresa-Id), e a RLS é a 2ª
        // barreira. Antes filtrava por $request->user()->empresa_id (a empresa-casa
        // do usuário), o que servia arquivo errado a usuários multi-empresa — S-2.
        $ev = \App\Models\Missao\MissaoEvidencia::query()->findOrFail($id);

        abort_unless(Storage::disk('local')->exists($ev->foto_path), 404);

        return response(Storage::disk('local')->get($ev->foto_path), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($ev->foto_path) ?: 'image/jpeg',
        ]);
    }

    /** POST /missoes/atribuicoes/{id}/auditar — aprovar/reprovar/revisão (+sanção). */
    public function auditar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'missao.aprovar');
        $d = $request->validate([
            'resultado' => 'required|string|in:aprovada,reprovada,revisao',
            'sancao' => 'nullable|string|in:advertencia,bonificacao,nenhuma',
            'observacao' => 'nullable|string|max:255',
        ]);

        $atr = $this->atribuicao($request, $id);
        $atr->forceFill([
            'auditoria_resultado' => $d['resultado'],
            'auditoria_sancao' => $d['sancao'] ?? 'nenhuma',
            'auditoria_observacao' => $d['observacao'] ?? null,
            'auditoria_user_id' => $request->user()->id,
            'auditoria_em' => now(),
        ])->save();

        return response()->json(['data' => ['id' => $atr->id, 'resultado' => $atr->auditoria_resultado]]);
    }

    /** POST /missoes/atribuicoes/{id}/adiamento — decide o adiamento (ETAPA 11). */
    public function decidirAdiamento(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'missao.aprovar');
        $d = $request->validate(['decisao' => 'required|string|in:aprovado,reprovado']);

        $atr = $this->atribuicao($request, $id);
        abort_unless($atr->adiamento_aprovacao === 'pendente', 422, 'Não há adiamento pendente.');

        // Reprovado → a missão volta a ficar atribuída (o entregador deve retomar).
        $atr->forceFill([
            'adiamento_aprovacao' => $d['decisao'],
            'status' => $d['decisao'] === 'reprovado' ? 'atribuida' : 'adiada',
        ])->save();

        return response()->json(['data' => ['id' => $atr->id, 'aprovacao' => $atr->adiamento_aprovacao, 'status' => $atr->status]]);
    }

    // ── internos ──

    /** @return array<string,mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo' => 'required|string|in:'.implode(',', Missao::TIPOS),
            'titulo' => 'required|string|max:160',
            'descricao' => 'nullable|string',
            'cerca_id' => 'nullable|integer|exists:monitora_cercas,id',
            'centro_lat' => 'nullable|numeric|between:-90,90',
            'centro_lng' => 'nullable|numeric|between:-180,180',
            'raio_m' => 'nullable|integer|min:50',
            'meta_visitas' => 'nullable|integer|min:1',
            'janela_inicio' => 'nullable|date_format:H:i',
            'janela_fim' => 'nullable|date_format:H:i|after:janela_inicio',
            'exige_foto' => 'boolean',
            'ativo' => 'boolean',
        ]);
    }

    private function missao(Request $request, int $id): Missao
    {
        return Missao::query()->where('empresa_id', $request->user()->empresa_id)->findOrFail($id);
    }

    private function atribuicao(Request $request, int $id): MissaoAtribuicao
    {
        return MissaoAtribuicao::query()->where('empresa_id', $request->user()->empresa_id)->findOrFail($id);
    }
}
