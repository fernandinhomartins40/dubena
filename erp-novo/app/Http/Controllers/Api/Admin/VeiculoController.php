<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Frota\VeiculoService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Frota\Veiculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Veículos (Frota) — C6. CRUD escopado por empresa + sub-recursos
 * (abastecimentos, trocas de óleo, pneus) e indicadores (consumo, alerta de óleo).
 * Permissão 'veiculo.*'.
 */
class VeiculoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private VeiculoService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'veiculo.view');
        $q = trim((string) $request->query('q', ''));

        $veiculos = Veiculo::query()
            ->when($q !== '', fn (Builder $b) => $b->where('placa', 'ilike', '%'.$q.'%')->orWhere('descricao', 'ilike', '%'.$q.'%'))
            ->orderBy('descricao')->get()
            ->map(fn (Veiculo $v) => $this->linha($v));

        return response()->json(['data' => $veiculos]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.view');
        $v = Veiculo::query()->findOrFail($id);

        return response()->json(['data' => array_merge($this->linha($v), [
            'renavam' => $v->renavam,
            'km_troca_oleo' => $v->km_troca_oleo,
            'km_ultima_troca_oleo' => $v->km_ultima_troca_oleo,
            'consumo_medio' => $this->service->consumoMedio($v),
            'alerta_oleo' => $this->service->alertaTrocaOleo($v),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'veiculo.create');

        return response()->json(['data' => $this->linha($this->service->criar($this->validar($request)))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.edit');
        $v = Veiculo::query()->findOrFail($id);

        return response()->json(['data' => $this->linha($this->service->atualizar($v, $this->validar($request)))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.delete');
        $this->service->excluir(Veiculo::query()->findOrFail($id));

        return response()->json(['message' => 'Veículo excluído.']);
    }

    // ── Sub-recursos ──
    public function abastecimentos(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.view');

        return response()->json(['data' => Veiculo::query()->findOrFail($id)->abastecimentos()->orderByDesc('km')->get()]);
    }

    public function registrarAbastecimento(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.edit');
        $v = Veiculo::query()->findOrFail($id);
        $d = $request->validate([
            'data' => 'nullable|date',
            'km' => 'required|integer|min:0',
            'litros' => 'required|numeric|gt:0',
            'valor_litro' => 'nullable|numeric|gte:0',
            'valor_total' => 'nullable|numeric|gte:0',
            'tanque_cheio' => 'nullable|boolean',
        ]);

        return response()->json(['data' => $this->service->abastecer($v, $d)], 201);
    }

    public function trocasOleo(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.view');

        return response()->json(['data' => Veiculo::query()->findOrFail($id)->trocasOleo()->orderByDesc('km')->get()]);
    }

    public function pneus(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.view');

        return response()->json(['data' => Veiculo::query()->findOrFail($id)->pneus()->get()]);
    }

    // ── Escrita de trocas de óleo e pneus (T4.7) ──
    //
    // Eram só leitura no novo; o legado tem `Route::resource` completo para os
    // dois. Sem POST, o operador da frota não lança a troca que acabou de fazer
    // — e o alerta de "troca vencida" fica aceso para sempre.

    public function registrarTrocaOleo(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.edit');
        $veiculo = Veiculo::query()->findOrFail($id);

        $dados = $request->validate([
            'data' => 'nullable|date',
            'km' => 'required|integer|min:0',
            'valor' => 'nullable|numeric|min:0',
            'observacao' => 'nullable|string|max:255',
        ]);

        // Pelo service: é ele que aplica a regra do hodômetro e atualiza o
        // `km_ultima_troca_oleo`, que é o que zera o alerta.
        return response()->json(
            ['data' => $this->service->registrarTrocaOleo($veiculo, $dados)],
            201,
        );
    }

    public function excluirTrocaOleo(Request $request, int $id, int $trocaId): JsonResponse
    {
        $this->autorizar($request, 'veiculo.edit');
        Veiculo::query()->findOrFail($id)->trocasOleo()->findOrFail($trocaId)->delete();

        return response()->json(['data' => ['excluido' => true]]);
    }

    public function registrarPneu(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.edit');
        $veiculo = Veiculo::query()->findOrFail($id);

        return response()->json(
            ['data' => $veiculo->pneus()->create($this->validarPneu($request))],
            201,
        );
    }

    public function atualizarPneu(Request $request, int $id, int $pneuId): JsonResponse
    {
        $this->autorizar($request, 'veiculo.edit');
        // findOrFail sobre a relação: o pneu tem de ser DESTE veículo.
        $pneu = Veiculo::query()->findOrFail($id)->pneus()->findOrFail($pneuId);
        $pneu->update($this->validarPneu($request));

        return response()->json(['data' => $pneu->fresh()]);
    }

    public function excluirPneu(Request $request, int $id, int $pneuId): JsonResponse
    {
        $this->autorizar($request, 'veiculo.edit');
        Veiculo::query()->findOrFail($id)->pneus()->findOrFail($pneuId)->delete();

        return response()->json(['data' => ['excluido' => true]]);
    }

    /** @return array<string,mixed> */
    private function validarPneu(Request $request): array
    {
        return $request->validate([
            'posicao' => 'required|string|max:30',
            'marca' => 'nullable|string|max:60',
            'data_instalacao' => 'nullable|date',
            'km_instalacao' => 'nullable|integer|min:0',
        ]);
    }

    // ── Entrada/saída de pátio (F12) ──
    public function entradasSaidas(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.view');

        return response()->json(['data' => Veiculo::query()->findOrFail($id)->entradasSaidas()->orderByDesc('datahora')->get()]);
    }

    public function registrarEntradaSaida(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.edit');
        $v = Veiculo::query()->findOrFail($id);
        $d = $request->validate([
            'tipo' => 'required|in:SAIDA,ENTRADA',
            'datahora' => 'nullable|date',
            'km' => 'nullable|integer|min:0',
            'colaborador_id' => 'nullable|integer|exists:colaboradores,id',
            'observacao' => 'nullable|string|max:255',
        ]);
        $reg = $v->entradasSaidas()->create(array_merge($d, ['datahora' => $d['datahora'] ?? now()]));
        // Mantém km_atual do veículo coerente quando o km informado for maior.
        if (! empty($d['km']) && (int) $d['km'] > (int) $v->km_atual) {
            $v->update(['km_atual' => (int) $d['km']]);
        }

        return response()->json(['data' => $reg], 201);
    }

    // ── Documentos do veículo (F12) ──
    public function documentos(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.view');

        return response()->json(['data' => Veiculo::query()->findOrFail($id)->documentos()->orderBy('vencimento')->get()]);
    }

    public function registrarDocumento(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'veiculo.edit');
        $v = Veiculo::query()->findOrFail($id);
        $d = $request->validate([
            'tipo' => 'required|string|max:60',
            'numero' => 'nullable|string|max:60',
            'emissao' => 'nullable|date',
            'vencimento' => 'nullable|date',
            'observacao' => 'nullable|string|max:255',
        ]);

        return response()->json(['data' => $v->documentos()->create($d)], 201);
    }

    /** @return array<string,mixed> */
    private function linha(Veiculo $v): array
    {
        return [
            'id' => $v->id,
            'placa' => $v->placa,
            'descricao' => $v->descricao,
            'kmatual' => (int) $v->km_atual,
            'veiculotipo_id' => $v->veiculotipo_id,
            'tipocombustivel_id' => $v->tipocombustivel_id,
        ];
    }

    /** @return array<string,mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'placa' => 'required|string|max:10',
            'descricao' => 'required|string|max:255',
            'renavam' => 'nullable|string|max:20',
            'veiculotipo_id' => 'nullable|integer|exists:veiculo_tipos,id',
            'tipocombustivel_id' => 'nullable|integer|exists:tipo_combustiveis,id',
            'km_atual' => 'nullable|integer|min:0',
            'km_troca_oleo' => 'nullable|integer|min:0',
            'km_ultima_troca_oleo' => 'nullable|integer|min:0',
            'ativo' => 'nullable|boolean',
        ]);
    }
}
