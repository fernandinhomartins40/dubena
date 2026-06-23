<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Grupos (redes) — C1. Entidade-topo do tenant; administra-se com permissão
 * 'grupo.*'. Não é escopado (o grupo é o próprio escopo).
 */
class GrupoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'grupo.view');

        $rows = Grupo::query()
            ->when(trim((string) $request->query('q', '')), fn ($q, $b) => $q->where('descricao', 'ilike', '%'.$b.'%'))
            ->orderBy('descricao')->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'grupo.create');

        $grupo = Grupo::create($this->validar($request));

        return response()->json(['data' => $grupo], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'grupo.edit');

        $grupo = Grupo::query()->findOrFail($id);
        $grupo->update($this->validar($request));

        return response()->json(['data' => $grupo->refresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'grupo.delete');

        Grupo::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Grupo excluído.']);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'descricao' => 'required|string|max:255',
            'ativo' => 'nullable|boolean',
        ]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
