<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Financeiro\CentroCusto;
use App\Models\Financeiro\PlanoConta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plano de contas e centros de custo (árvores) — N5. Escopo por grupo.
 */
class FinanceiroCadastroController extends Controller
{
    use AutorizaPorPermissao;

    // ── Plano de contas ──
    public function planosIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');

        return response()->json(['data' => PlanoConta::query()->orderBy('codigo')->orderBy('descricao')->get()]);
    }

    public function planoSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $d = $request->validate([
            'pai_id' => 'nullable|integer|exists:planos_conta,id',
            'codigo' => 'nullable|string|max:30',
            'descricao' => 'required|string|max:255',
            'pagarreceber' => 'required|in:P,R',
            'nivel' => 'nullable|integer|min:1',
            'ativo' => 'nullable|boolean',
        ]);

        if ($id) {
            $p = PlanoConta::query()->findOrFail($id);
            $p->update($d);

            return response()->json(['data' => $p->refresh()]);
        }

        return response()->json(['data' => PlanoConta::create($d)], 201);
    }

    public function planoExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'financeiro.delete');
        PlanoConta::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Plano de conta excluído.']);
    }

    // ── Centros de custo ──
    public function centrosIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');

        return response()->json(['data' => CentroCusto::query()->orderBy('codigo')->orderBy('descricao')->get()]);
    }

    public function centroSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $d = $request->validate([
            'pai_id' => 'nullable|integer|exists:centros_custo,id',
            'codigo' => 'nullable|string|max:30',
            'descricao' => 'required|string|max:255',
            'nivel' => 'nullable|integer|min:1',
            'ativo' => 'nullable|boolean',
        ]);

        if ($id) {
            $c = CentroCusto::query()->findOrFail($id);
            $c->update($d);

            return response()->json(['data' => $c->refresh()]);
        }

        return response()->json(['data' => CentroCusto::create($d)], 201);
    }

    public function centroExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'financeiro.delete');
        CentroCusto::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Centro de custo excluído.']);
    }
}
