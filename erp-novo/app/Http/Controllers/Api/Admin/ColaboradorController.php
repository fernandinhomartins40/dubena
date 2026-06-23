<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Rh\ColaboradorService;
use App\Http\Controllers\Controller;
use App\Models\Rh\Colaborador;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Colaboradores (RH) — C5. CRUD escopado por empresa + sub-recursos
 * (família, recessos, comissões). Permissão 'colaborador.*'.
 */
class ColaboradorController extends Controller
{
    public function __construct(private ColaboradorService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');
        $q = trim((string) $request->query('q', ''));

        $page = Colaborador::query()
            ->with('cargo:id,descricao')
            ->when($q !== '', fn (Builder $b) => $b->where('nome', 'ilike', '%'.$q.'%'))
            ->orderBy('nome')
            ->paginate(20);

        $page->getCollection()->transform(fn (Colaborador $c) => $this->linha($c));

        return response()->json($page);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');
        $c = Colaborador::query()->with('cargo:id,descricao')->findOrFail($id);

        return response()->json(['data' => array_merge($this->linha($c), [
            'cpf' => $c->cpf, 'rg' => $c->rg, 'telefone' => $c->telefone,
            'cargo_id' => $c->cargo_id, 'entregador' => $c->entregador,
            'data_nascimento' => $c->data_nascimento?->toDateString(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'colaborador.create');
        $colaborador = $this->service->criar($this->validar($request));

        return response()->json(['data' => $this->linha($colaborador)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $colaborador = Colaborador::query()->findOrFail($id);

        return response()->json(['data' => $this->linha($this->service->atualizar($colaborador, $this->validar($request)))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.delete');
        $this->service->excluir(Colaborador::query()->findOrFail($id));

        return response()->json(['message' => 'Colaborador excluído.']);
    }

    // ── Sub-recursos ──
    public function familia(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');

        return response()->json(['data' => Colaborador::query()->findOrFail($id)->familias()->get()]);
    }

    public function addFamilia(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $colaborador = Colaborador::query()->findOrFail($id);
        $d = $request->validate([
            'nome' => 'required|string|max:255',
            'parentesco' => 'nullable|string|max:40',
            'data_nascimento' => 'nullable|date',
        ]);
        $this->service->adicionarFamiliar($colaborador, $d);

        return response()->json(['message' => 'Familiar adicionado.'], 201);
    }

    public function delFamilia(Request $request, int $id, int $famId): JsonResponse
    {
        $this->autorizar($request, 'colaborador.edit');
        $this->service->removerFamiliar(Colaborador::query()->findOrFail($id), $famId);

        return response()->json(['message' => 'Familiar removido.']);
    }

    public function recessos(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');

        return response()->json(['data' => Colaborador::query()->findOrFail($id)->recessos()->orderByDesc('inicio')->get()]);
    }

    public function comissoes(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'colaborador.view');

        return response()->json(['data' => Colaborador::query()->findOrFail($id)->comissoes()->with('excecoes')->get()]);
    }

    /** @return array<string,mixed> */
    private function linha(Colaborador $c): array
    {
        return [
            'id' => $c->id,
            'nome' => $c->nome,
            'cpf' => $c->cpf,
            'dataadmissao' => $c->data_admissao?->toDateString(),
            'datadesligamento' => $c->data_desligamento?->toDateString(),
            'cargo' => $c->cargo?->descricao,
        ];
    }

    /** @return array<string,mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'nome' => 'required|string|max:255',
            'cpf' => 'nullable|string|max:11',
            'rg' => 'nullable|string|max:20',
            'cargo_id' => 'nullable|integer|exists:cargos,id',
            'data_nascimento' => 'nullable|date',
            'data_admissao' => 'nullable|date',
            'data_desligamento' => 'nullable|date',
            'telefone' => 'nullable|string|max:20',
            'entregador' => 'nullable|boolean',
            'ativo' => 'nullable|boolean',
        ]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
