<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Frota\VeiculoService;
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

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
