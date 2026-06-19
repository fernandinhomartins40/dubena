<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Produtoclasse;
use App\Unidademedida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F1 — Configurações do Produto (REORGANIZAÇÃO, IMPL_PRODUTO §6b):
 * produtoclasse.index + unidademedida.index, antes telas soltas, viram abas de
 * "Configurações" do módulo Produtos. CRUD simples escopado por grupo, com unicidade.
 */
class ProdutoConfigController extends Controller
{
    private function grupoId(Request $request): int
    {
        $user = $request->user();
        return (int) (optional($user->empresa)->grupo_id ?? $user->grupo_id);
    }

    private function autorizar(Request $request, string $acao): void
    {
        abort_unless($request->user()->podeRecurso('produto', $acao), 403, 'Sem permissão.');
    }

    // ---------- CLASSES (produtoclasses: descricao, tipo G/R/V/P, ativo) ----------

    public function classes(Request $request)
    {
        $this->autorizar($request, 'view');
        return response()->json([
            'data' => Produtoclasse::where('grupo_id', $this->grupoId($request))
                ->orderBy('descricao')->get(['id', 'descricao', 'tipo', 'ativo']),
        ]);
    }

    public function salvarClasse(Request $request, $id = null)
    {
        $this->autorizar($request, $id ? 'edit' : 'create');
        $grupo = $this->grupoId($request);
        $data = $request->validate([
            'descricao' => 'required|string|max:255',
            'tipo'      => 'nullable|string|max:1',
            'ativo'     => 'nullable|boolean',
        ]);

        // Unicidade descricao no grupo (getXIguais legado).
        $existe = Produtoclasse::where('grupo_id', $grupo)
            ->whereRaw('lower(descricao) = ?', [mb_strtolower($data['descricao'])])
            ->when($id, fn ($w) => $w->where('id', '<>', $id))->exists();
        if ($existe) {
            return response()->json(['message' => 'Já existe uma classe com essa descrição.'], 422);
        }

        $payload = [
            'grupo_id'  => $grupo,
            'descricao' => $data['descricao'],
            'tipo'      => $data['tipo'] ?? 'P',
            'ativo'     => isset($data['ativo']) ? (int) (! empty($data['ativo'])) : 1,
        ];
        $classe = $id
            ? tap(Produtoclasse::where('grupo_id', $grupo)->findOrFail($id))->update($payload)
            : Produtoclasse::create($payload);

        return response()->json(['data' => $classe], $id ? 200 : 201);
    }

    public function excluirClasse(Request $request, $id)
    {
        $this->autorizar($request, 'delete');
        $classe = Produtoclasse::where('grupo_id', $this->grupoId($request))->findOrFail($id);
        try {
            DB::transaction(fn () => $classe->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Classe em uso — não pode ser excluída.'], 409);
        }
        return response()->json(['message' => 'Classe excluída.']);
    }

    // ---------- UNIDADES (unidademedidas: descricao, sigla, ativo) ----------

    public function unidades(Request $request)
    {
        $this->autorizar($request, 'view');
        return response()->json([
            'data' => Unidademedida::where('grupo_id', $this->grupoId($request))
                ->orderBy('descricao')->get(['id', 'descricao', 'sigla', 'ativo']),
        ]);
    }

    public function salvarUnidade(Request $request, $id = null)
    {
        $this->autorizar($request, $id ? 'edit' : 'create');
        $grupo = $this->grupoId($request);
        $data = $request->validate([
            'descricao' => 'required|string|max:255',
            'sigla'     => 'nullable|string|max:10',
            'ativo'     => 'nullable|boolean',
        ]);

        $existe = Unidademedida::where('grupo_id', $grupo)
            ->whereRaw('lower(descricao) = ?', [mb_strtolower($data['descricao'])])
            ->when($id, fn ($w) => $w->where('id', '<>', $id))->exists();
        if ($existe) {
            return response()->json(['message' => 'Já existe uma unidade com essa descrição.'], 422);
        }

        $payload = [
            'grupo_id'  => $grupo,
            'descricao' => $data['descricao'],
            'sigla'     => $data['sigla'] ?? null,
            'ativo'     => isset($data['ativo']) ? (int) (! empty($data['ativo'])) : 1,
        ];
        $un = $id
            ? tap(Unidademedida::where('grupo_id', $grupo)->findOrFail($id))->update($payload)
            : Unidademedida::create($payload);

        return response()->json(['data' => $un], $id ? 200 : 201);
    }

    public function excluirUnidade(Request $request, $id)
    {
        $this->autorizar($request, 'delete');
        $un = Unidademedida::where('grupo_id', $this->grupoId($request))->findOrFail($id);
        try {
            DB::transaction(fn () => $un->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Unidade em uso — não pode ser excluída.'], 409);
        }
        return response()->json(['message' => 'Unidade excluída.']);
    }
}
