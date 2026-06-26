<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Monitora\MonitoraService;
use App\Domain\Monitora\MonitoraSyncService;
use App\Http\Controllers\Controller;
use App\Models\Monitora\Cerca;
use App\Models\Monitora\UltimaPosicao;
use App\Models\Monitora\Veiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Monitora (GPS) — N11. Veículos, ingestão de posição, última posição (mapa),
 * cercas e sync com o SGCasa (gate). Módulo isolado; RBAC monitora.*
 */
class MonitoraController extends Controller
{
    public function __construct(
        private MonitoraService $service,
        private MonitoraSyncService $sync,
    ) {
    }

    public function veiculos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.view');

        return response()->json(['data' => Veiculo::query()->with('ultimaPosicao')->orderBy('placa')->get()]);
    }

    public function criarVeiculo(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.edit');
        $d = $request->validate([
            'placa' => 'required|string|max:10',
            'descricao' => 'nullable|string|max:255',
            'imei' => 'nullable|string|max:30',
        ]);
        $d['empresa_id'] = $request->user()->empresa_id;
        $d['grupo_id'] = $request->user()->grupo_id;

        return response()->json(['data' => Veiculo::create($d)], 201);
    }

    /** POST /monitora/veiculos/{id}/posicoes — ingestão de posição (rastreador/app). */
    public function ingerirPosicao(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'monitora.edit');
        $veiculo = Veiculo::query()->findOrFail($id);

        $d = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'velocidade' => 'nullable|numeric|gte:0',
            'direcao' => 'nullable|integer|min:0|max:359',
            'ignicao' => 'nullable|boolean',
            'registrado_em' => 'nullable|date',
        ]);

        $posicao = $this->service->registrarPosicao($veiculo, $d);

        return response()->json(['data' => $posicao], 201);
    }

    /** GET /monitora/ultimas-posicoes — snapshot para o mapa. */
    public function ultimasPosicoes(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.view');

        $rows = UltimaPosicao::query()
            ->whereHas('veiculo', fn ($q) => $q->where('empresa_id', $request->user()->empresa_id))
            ->with('veiculo:id,placa,descricao')->get()
            ->map(fn (UltimaPosicao $u) => [
                'veiculo_id' => $u->veiculo_id,
                'placa' => $u->veiculo?->placa,
                'latitude' => (float) $u->latitude,
                'longitude' => (float) $u->longitude,
                'velocidade' => (float) $u->velocidade,
                'ignicao' => (bool) $u->ignicao,
                'registrado_em' => $u->registrado_em?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function cercas(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.view');

        return response()->json(['data' => Cerca::query()->with('pontos')->orderBy('descricao')->get()]);
    }

    /** POST /monitora/cercas — cria cerca POLIGONAL (descrição + cor/setor + vértices). */
    public function criarCerca(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.edit');
        $d = $this->validarCerca($request);

        $cerca = DB::transaction(function () use ($d, $request) {
            $cerca = Cerca::create([
                'empresa_id' => $request->user()->empresa_id,
                'grupo_id' => $request->user()->grupo_id,
                'descricao' => $d['descricao'],
                'cor' => $d['cor'] ?? null,
                'setor_id' => $d['setor_id'] ?? null,
                'ativo' => $d['ativo'] ?? true,
            ]);
            $this->salvarPontos($cerca, $d['pontos']);

            return $cerca;
        });

        return response()->json(['data' => $cerca->load('pontos')], 201);
    }

    /** PUT /monitora/cercas/{id} — atualiza a cerca e regrava os vértices. */
    public function atualizarCerca(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'monitora.edit');
        $cerca = Cerca::query()->findOrFail($id);
        $d = $this->validarCerca($request);

        DB::transaction(function () use ($cerca, $d) {
            $cerca->update([
                'descricao' => $d['descricao'],
                'cor' => $d['cor'] ?? null,
                'setor_id' => $d['setor_id'] ?? null,
                'ativo' => $d['ativo'] ?? true,
            ]);
            $cerca->pontos()->delete();
            $this->salvarPontos($cerca, $d['pontos']);
        });

        return response()->json(['data' => $cerca->load('pontos')]);
    }

    /** DELETE /monitora/cercas/{id} — remove a cerca (vértices em cascata). */
    public function excluirCerca(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'monitora.edit');
        Cerca::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Cerca excluída.']);
    }

    /**
     * Validação compartilhada. Polígono = ao menos 3 vértices {latitude, longitude}.
     *
     * @return array<string, mixed>
     */
    private function validarCerca(Request $request): array
    {
        return $request->validate([
            'descricao' => 'required|string|max:255',
            'cor' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'setor_id' => 'nullable|integer|exists:setores,id',
            'ativo' => 'boolean',
            'pontos' => 'required|array|min:3',
            'pontos.*.latitude' => 'required|numeric|between:-90,90',
            'pontos.*.longitude' => 'required|numeric|between:-180,180',
        ]);
    }

    /** @param  list<array{latitude: mixed, longitude: mixed}>  $pontos */
    private function salvarPontos(Cerca $cerca, array $pontos): void
    {
        $cerca->pontos()->createMany(
            array_map(fn (array $p, int $i) => [
                'latitude' => $p['latitude'],
                'longitude' => $p['longitude'],
                'ordem' => $i,
            ], $pontos, array_keys($pontos)),
        );
    }

    /** POST /monitora/sync — dispara o sync com o SGCasa (gate). */
    public function sincronizar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.edit');
        $n = $this->sync->sincronizar((int) $request->user()->empresa_id);

        return response()->json(['message' => "Sync concluído: {$n} posição(ões).", 'ingeridas' => $n]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
