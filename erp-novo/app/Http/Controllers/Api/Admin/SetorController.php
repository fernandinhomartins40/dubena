<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Estoque\Setor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Setores (depósitos) — N3. CRUD escopado por empresa (BelongsToTenant).
 */
class SetorController extends Controller
{
    use AutorizaPorPermissao;

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        $rows = Setor::query()
            ->when(trim((string) $request->query('q', '')), fn ($q, $b) => $q->where('descricao', 'ilike', '%'.$b.'%'))
            ->orderBy('descricao')->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');

        return response()->json(['data' => Setor::create($this->validar($request))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $setor = Setor::query()->findOrFail($id);
        $setor->update($this->validar($request));

        return response()->json(['data' => $setor->refresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        Setor::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Setor excluído.']);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'descricao' => 'required|string|max:255',
            'ativo' => 'nullable|boolean',
        ]);
    }
}
