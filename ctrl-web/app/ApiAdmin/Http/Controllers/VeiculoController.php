<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Veiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F8b (SPA React) — API admin FROTA/VEÍCULOS.
 * Auditado: Veiculo + Veiculoabastecimento/Veiculotrocaoleo/Veiculopneu
 * (docs/01-vigente/IMPL_SATELITES.md). Ficha + timeline de manutenção
 * (abastecimentos, trocas de óleo, pneus). RBAC: veiculo.*.
 */
class VeiculoController extends Controller
{
    private function grupoId(Request $request): int
    {
        $u = $request->user();
        return (int) (optional($u->empresa)->grupo_id ?? $u->grupo_id);
    }

    private function autorizar(Request $request, string $acao): void
    {
        abort_unless($request->user()->podeRecurso('veiculo', $acao), 403, 'Sem permissão.');
    }

    private function escopo($query, Request $request)
    {
        return $query->where('empresa_id', $request->user()->empresa_id);
    }

    /** GET /api/admin/veiculos?q= */
    public function index(Request $request)
    {
        $this->autorizar($request, 'view');
        $q = trim((string) $request->query('q', ''));
        $rows = $this->escopo(Veiculo::query(), $request)
            ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%')->orWhere('placa', 'ilike', '%' . $q . '%'))
            ->orderBy('descricao')->limit(300)
            ->get(['id', 'placa', 'descricao', 'kmatual', 'veiculotipo_id', 'tipocombustivel_id']);
        return response()->json(['data' => $rows]);
    }

    /** GET /api/admin/veiculos/{id} */
    public function show(Request $request, $id)
    {
        $this->autorizar($request, 'view');
        $v = $this->escopo(Veiculo::query(), $request)->findOrFail($id);
        $data = $v->toArray();
        $data['tipo_label'] = $v->veiculotipo_id ? DB::table('veiculotipos')->where('id', $v->veiculotipo_id)->value('descricao') : null;
        $data['combustivel_label'] = $v->tipocombustivel_id ? DB::table('tipocombustivels')->where('id', $v->tipocombustivel_id)->value('descricao') : null;
        return response()->json(['data' => $data]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'placa'            => 'required|string|max:10',
            'descricao'        => 'required|string|max:255',
            'veiculotipo_id'   => 'required|integer',
            'tipocombustivel_id' => 'required|integer',
            'colaborador_id'   => 'nullable|integer',
            'kminicial'        => 'nullable|numeric',
            'kmatual'          => 'nullable|numeric',
            'kmtrocaoleo'      => 'nullable|numeric',
            'kmultimatrocaoleo' => 'nullable|numeric',
            'pneus'            => 'nullable|integer',
            'pneusvidautilkm'  => 'nullable|numeric',
            'alertasdiasantes' => 'nullable|integer',
        ]);
    }

    private function comDefaults(array $data, Request $request): array
    {
        // colunas NOT NULL numéricas → default 0 quando ausentes
        foreach (['kminicial', 'kmatual', 'kmtrocaoleo', 'kmultimatrocaoleo', 'pneus', 'pneusvidautilkm', 'alertasdiasantes'] as $k) {
            $data[$k] = $data[$k] ?? 0;
        }
        $data['grupo_id'] = $this->grupoId($request);
        $data['empresa_id'] = (int) $request->user()->empresa_id;
        return $data;
    }

    public function store(Request $request)
    {
        $this->autorizar($request, 'create');
        $data = $this->comDefaults($this->validar($request), $request);
        $v = DB::transaction(fn () => Veiculo::create($data));
        return response()->json(['data' => ['id' => $v->id]], 201);
    }

    public function update(Request $request, $id)
    {
        $this->autorizar($request, 'edit');
        $v = $this->escopo(Veiculo::query(), $request)->findOrFail($id);
        DB::transaction(fn () => $v->update($this->validar($request)));
        return response()->json(['data' => ['id' => $v->id]]);
    }

    public function destroy(Request $request, $id)
    {
        $this->autorizar($request, 'delete');
        $v = $this->escopo(Veiculo::query(), $request)->findOrFail($id);
        try {
            DB::transaction(fn () => $v->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Veículo em uso — não pode ser excluído.'], 409);
        }
        return response()->json(['message' => 'Veículo excluído.']);
    }

    // ---- Timeline de manutenção (consulta) ----

    public function abastecimentos(Request $request, $id)
    {
        $this->autorizar($request, 'view');
        $this->escopo(Veiculo::query(), $request)->findOrFail($id);
        $rows = DB::table('veiculoabastecimentos')->where('veiculo_id', $id)
            ->orderByDesc('data')->limit(200)
            ->get(['id', 'data', 'kmatual', 'kmrodado', 'totallitros', 'mediaconsumo']);
        return response()->json(['data' => $rows]);
    }

    public function trocasOleo(Request $request, $id)
    {
        $this->autorizar($request, 'view');
        $this->escopo(Veiculo::query(), $request)->findOrFail($id);
        $rows = DB::table('veiculotrocaoleos')->where('veiculo_id', $id)
            ->orderByDesc('data')->limit(200)
            ->get(['id', 'data', 'kmtrocaoleo', 'kmultimatrocaoleo', 'oleoproximatroca']);
        return response()->json(['data' => $rows]);
    }

    public function pneus(Request $request, $id)
    {
        $this->autorizar($request, 'view');
        $this->escopo(Veiculo::query(), $request)->findOrFail($id);
        $rows = DB::table('veiculopneus')->where('veiculo_id', $id)
            ->orderByDesc('data')->limit(200)
            ->get(['id', 'data', 'km', 'vidautilkm', 'valor', 'quantidade', 'medidapneus']);
        return response()->json(['data' => $rows]);
    }
}
