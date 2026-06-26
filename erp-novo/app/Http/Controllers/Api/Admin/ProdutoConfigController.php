<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Produto\ProdutoClasse;
use App\Models\Produto\UnidadeMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configurações de produto (classes e unidades de medida) — C1.
 * Escopo por grupo (BelongsToGrupo nos models). Permissão 'produto.config'.
 */
class ProdutoConfigController extends Controller
{
    use AutorizaPorPermissao;

    // ── Classes ──
    public function classesIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'produto.config');

        return response()->json(['data' => ProdutoClasse::query()->orderBy('descricao')->get()]);
    }

    public function classeSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, 'produto.config');
        $d = $request->validate([
            'descricao' => 'required|string|max:255',
            'ativo' => 'nullable|boolean',
        ]);

        if ($id) {
            $c = ProdutoClasse::query()->findOrFail($id);
            $c->update($d);

            return response()->json(['data' => $c->refresh()]);
        }

        return response()->json(['data' => ProdutoClasse::create($d)], 201);
    }

    public function classeExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'produto.config');
        ProdutoClasse::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Classe excluída.']);
    }

    // ── Unidades de medida ──
    public function unidadesIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'produto.config');

        return response()->json(['data' => UnidadeMedida::query()->orderBy('descricao')->get()]);
    }

    public function unidadeSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, 'produto.config');
        $d = $request->validate([
            'descricao' => 'required|string|max:255',
            'sigla' => 'nullable|string|max:6',
            'ativo' => 'nullable|boolean',
        ]);

        if ($id) {
            $u = UnidadeMedida::query()->findOrFail($id);
            $u->update($d);

            return response()->json(['data' => $u->refresh()]);
        }

        return response()->json(['data' => UnidadeMedida::create($d)], 201);
    }

    public function unidadeExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'produto.config');
        UnidadeMedida::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Unidade excluída.']);
    }
}
