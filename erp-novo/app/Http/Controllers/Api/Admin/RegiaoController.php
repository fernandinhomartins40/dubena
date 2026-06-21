<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Regiao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Regiões de atendimento (escopo por grupo, automático via BelongsToGrupo) — N1.
 */
class RegiaoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'empresa.view');

        $rows = Regiao::query()
            ->when(trim((string) $request->query('q', '')), fn ($q, $b) => $q->where('descricao', 'ilike', '%'.$b.'%'))
            ->orderBy('descricao')->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        $dados = $this->validar($request);

        return response()->json(['data' => Regiao::create($dados)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        $regiao = Regiao::query()->findOrFail($id);
        $regiao->update($this->validar($request));

        return response()->json(['data' => $regiao->refresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'empresa.edit');
        Regiao::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Região excluída.']);
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
