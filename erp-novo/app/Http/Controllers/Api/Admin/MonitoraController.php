<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Monitora\MonitoraService;
use App\Domain\Monitora\MonitoraSyncService;
use App\Domain\Monitora\RelatorioMonitoraService;
use App\Domain\Relatorio\RelatorioService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Monitora\Cerca;
use App\Models\Monitora\UltimaPosicao;
use App\Models\Monitora\Veiculo;
use App\Models\Monitora\VeiculoTipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Monitora (GPS) — N11. Veículos, ingestão de posição, última posição (mapa),
 * cercas e sync com o SGCasa (gate). Módulo isolado; RBAC monitora.*
 */
class MonitoraController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(
        private MonitoraService $service,
        private MonitoraSyncService $sync,
        private RelatorioMonitoraService $relatorio,
        private RelatorioService $exportador,
    ) {}

    public function veiculos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.view');

        return response()->json(['data' => Veiculo::query()->with(['ultimaPosicao', 'tipo'])->orderBy('placa')->get()]);
    }

    public function criarVeiculo(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.edit');
        $d = $this->validarVeiculo($request);
        $d['empresa_id'] = $request->user()->empresa_id;
        $d['grupo_id'] = $request->user()->grupo_id;

        return response()->json(['data' => Veiculo::create($d)->load('tipo')], 201);
    }

    /** PUT /monitora/veiculos/{id} — atualiza dados do veículo (tipo/motorista/km/etc.). */
    public function atualizarVeiculo(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'monitora.edit');
        $veiculo = Veiculo::query()->findOrFail($id);
        $veiculo->update($this->validarVeiculo($request));

        return response()->json(['data' => $veiculo->load('tipo')]);
    }

    /** @return array<string, mixed> */
    private function validarVeiculo(Request $request): array
    {
        return $request->validate([
            'placa' => 'required|string|max:10',
            'descricao' => 'nullable|string|max:255',
            'tipo_id' => 'nullable|integer|exists:monitora_veiculo_tipos,id',
            'motorista' => 'nullable|string|max:255',
            'km_atual' => 'nullable|integer|min:0',
            'imei' => 'nullable|string|max:30',
            'deviceid' => 'nullable|string|max:50',
            'ativo' => 'boolean',
        ]);
    }

    /** GET /monitora/tipos — tipos de veículo do grupo. */
    public function tipos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.view');

        return response()->json(['data' => VeiculoTipo::query()->orderBy('descricao')->get()]);
    }

    /** POST /monitora/tipos — cria tipo de veículo (ícone + velocidade máxima). */
    public function criarTipo(Request $request): JsonResponse
    {
        $this->autorizar($request, 'monitora.edit');
        $d = $request->validate([
            'descricao' => 'required|string|max:255',
            'icone' => 'nullable|string|max:255',
            'velocidade_maxima' => 'nullable|integer|min:0|max:300',
            'ativo' => 'boolean',
        ]);
        $d['grupo_id'] = $request->user()->grupo_id;

        return response()->json(['data' => VeiculoTipo::create($d)], 201);
    }

    /** GET /monitora/veiculos/{id}/historico?de&ate — histórico de posições (replay). */
    public function historico(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'monitora.view');
        $veiculo = Veiculo::query()->findOrFail($id);

        $d = $request->validate([
            'de' => 'nullable|date',
            'ate' => 'nullable|date|after_or_equal:de',
            'limite' => 'nullable|integer|min:1|max:5000',
        ]);

        $posicoes = $veiculo->posicoes()
            ->when($d['de'] ?? null, fn ($q, $de) => $q->where('registrado_em', '>=', $de))
            ->when($d['ate'] ?? null, fn ($q, $ate) => $q->where('registrado_em', '<=', $ate))
            ->orderBy('registrado_em')
            ->limit((int) ($d['limite'] ?? 1000))
            ->get(['latitude', 'longitude', 'velocidade', 'direcao', 'ignicao', 'registrado_em'])
            ->map(fn ($p) => [
                'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
                'velocidade' => (float) $p->velocidade,
                'direcao' => $p->direcao,
                'ignicao' => (bool) $p->ignicao,
                'registrado_em' => $p->registrado_em?->toIso8601String(),
            ]);

        return response()->json(['data' => $posicoes]);
    }

    /**
     * GET /monitora/veiculos/{id}/eventos?de&ate&formato=csv|pdf — relatório de paradas
     * e excessos de velocidade (paridade com o ReportController do legado).
     */
    public function relatorioEventos(Request $request, int $id): Response|JsonResponse
    {
        $this->autorizar($request, 'monitora.view');
        $veiculo = Veiculo::query()->with('tipo')->findOrFail($id);

        $d = $request->validate([
            'de' => 'required|date',
            'ate' => 'required|date|after_or_equal:de',
            'formato' => 'nullable|in:csv,pdf',
        ]);

        $formato = $d['formato'] ?? null;
        if ($formato !== null) {
            $linhas = $this->relatorio->linhasEventos($veiculo, $d['de'], $d['ate']);
            $titulo = "Eventos do veículo {$veiculo->placa} ({$d['de']} a {$d['ate']})";
            $nome = "eventos-{$veiculo->placa}";

            if ($formato === 'csv') {
                return response($this->exportador->csv($linhas), 200, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => "attachment; filename=\"{$nome}.csv\"",
                ]);
            }

            return response($this->exportador->pdf($linhas, $titulo), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$nome}.pdf\"",
            ]);
        }

        return response()->json(['data' => $this->relatorio->eventosVeiculo($veiculo, $d['de'], $d['ate'])]);
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
}
