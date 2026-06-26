<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Fiscal\MalhaFiscal;
use App\Models\Fiscal\OperacaoFiscal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuração fiscal (C12) — operações fiscais e malha (tabelas de apoio por tipo).
 * Últimos endpoints da SPA (fiscal/operacoes, fiscal/malha/{tipo}). Permissão fiscal.*.
 */
class FiscalConfigController extends Controller
{
    use AutorizaPorPermissao;

    // ── Operações fiscais ──
    public function operacoesIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');

        return response()->json(['data' => OperacaoFiscal::query()->orderBy('descricao')->get()]);
    }

    public function operacaoSalvar(Request $request, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, 'fiscal.edit');
        $d = $request->validate([
            'descricao' => 'required|string|max:255',
            'descricao_fiscal' => 'nullable|string|max:255',
            'cfop' => 'nullable|string|max:4',
            'movimenta_estoque' => 'nullable|boolean',
            'movimenta_financeiro' => 'nullable|boolean',
            'ativo' => 'nullable|boolean',
        ]);

        if ($id) {
            $op = OperacaoFiscal::query()->findOrFail($id);
            $op->update($d);

            return response()->json(['data' => $op->refresh()]);
        }

        return response()->json(['data' => OperacaoFiscal::create($d)], 201);
    }

    public function operacaoExcluir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.edit');
        OperacaoFiscal::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Operação fiscal excluída.']);
    }

    // ── Malha fiscal (genérica por tipo) ──
    public function malhaIndex(Request $request, string $tipo): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');

        $rows = MalhaFiscal::query()->where('tipo', $tipo)
            ->when(trim((string) $request->query('q', '')), fn ($q, $b) => $q->where('descricao', 'ilike', '%'.$b.'%')->orWhere('codigo', 'ilike', '%'.$b.'%'))
            ->orderBy('codigo')->orderBy('descricao')->get();

        return response()->json(['data' => $rows]);
    }

    public function malhaSalvar(Request $request, string $tipo, ?int $id = null): JsonResponse
    {
        $this->autorizar($request, 'fiscal.edit');
        $d = $request->validate([
            'codigo' => 'nullable|string|max:20',
            'descricao' => 'required|string|max:255',
            'ativo' => 'nullable|boolean',
        ]);
        $d['tipo'] = $tipo;

        if ($id) {
            $m = MalhaFiscal::query()->findOrFail($id);
            $m->update($d);

            return response()->json(['data' => $m->refresh()]);
        }

        return response()->json(['data' => MalhaFiscal::create($d)], 201);
    }

    public function malhaExcluir(Request $request, string $tipo, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.edit');
        MalhaFiscal::query()->where('tipo', $tipo)->whereKey($id)->firstOrFail()->delete();

        return response()->json(['message' => 'Registro excluído.']);
    }
}
