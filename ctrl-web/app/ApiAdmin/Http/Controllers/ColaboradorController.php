<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Colaborador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F8a (SPA React) — API admin RH/COLABORADORES.
 * Auditado: Colaborador + Colaboradorfamilia (scaffold) + Colaboradorcomissao + Recessos
 * (docs/01-vigente/IMPL_SATELITES.md). Ficha com sub-recursos: família, comissões, recessos.
 * RBAC: colaborador.*.
 */
class ColaboradorController extends Controller
{
    private function grupoId(Request $request): int
    {
        $u = $request->user();
        return (int) (optional($u->empresa)->grupo_id ?? $u->grupo_id);
    }

    private function autorizar(Request $request, string $acao): void
    {
        abort_unless($request->user()->podeRecurso('colaborador', $acao), 403, 'Sem permissão.');
    }

    private function escopo($query, Request $request)
    {
        return $query->where('empresa_id', $request->user()->empresa_id);
    }

    /** GET /api/admin/colaboradores?q=&page= */
    public function index(Request $request)
    {
        $this->autorizar($request, 'view');
        $q = trim((string) $request->query('q', ''));
        $page = $this->escopo(Colaborador::query(), $request)
            ->leftJoin('cargos', 'colaboradors.cargo_id', '=', 'cargos.id')
            ->when($q !== '', fn ($w) => $w->where('colaboradors.nome', 'ilike', '%' . $q . '%'))
            ->orderBy('colaboradors.nome')
            ->paginate(min((int) $request->query('per_page', 20), 100), [
                'colaboradors.id', 'colaboradors.nome', 'colaboradors.cpf', 'colaboradors.dataadmissao',
                'colaboradors.datadesligamento', 'cargos.descricao as cargo',
            ]);
        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    /** GET /api/admin/colaboradores/{id} */
    public function show(Request $request, $id)
    {
        $this->autorizar($request, 'view');
        $c = $this->escopo(Colaborador::query(), $request)->findOrFail($id);
        $data = $c->toArray();
        $data['cidade_label'] = optional(\App\Cidade::find($c->cidade_id))->descricao;
        $data['bairro_label'] = $c->bairro_id ? optional(\App\Bairro::find($c->bairro_id))->descricao : null;
        $data['cargo_label'] = $c->cargo_id ? DB::table('cargos')->where('id', $c->cargo_id)->value('descricao') : null;
        return response()->json(['data' => $data]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nome'           => 'required|string|max:255',
            'email'          => 'nullable|email|max:255',
            'cpf'            => 'nullable|string|max:14',
            'rg'             => 'nullable|string|max:20',
            'datanascimento' => 'nullable|date',
            'dataadmissao'   => 'nullable|date',
            'datadesligamento' => 'nullable|date',
            'sexo'           => 'nullable|string|max:1',
            'cargo_id'       => 'nullable|integer',
            'estadocivil_id' => 'nullable|integer',
            'numero'         => 'required|string|max:10',
            'cidade_id'      => 'required|integer',
            'bairro_id'      => 'required|integer',
            'cep'            => 'nullable|string|max:9',
        ]);
    }

    public function store(Request $request)
    {
        $this->autorizar($request, 'create');
        $data = $this->validar($request);
        $data['grupo_id'] = $this->grupoId($request);
        $data['empresa_id'] = (int) $request->user()->empresa_id;
        $c = DB::transaction(fn () => Colaborador::create($data));
        return response()->json(['data' => ['id' => $c->id]], 201);
    }

    public function update(Request $request, $id)
    {
        $this->autorizar($request, 'edit');
        $c = $this->escopo(Colaborador::query(), $request)->findOrFail($id);
        DB::transaction(fn () => $c->update($this->validar($request)));
        return response()->json(['data' => ['id' => $c->id]]);
    }

    public function destroy(Request $request, $id)
    {
        $this->autorizar($request, 'delete');
        $c = $this->escopo(Colaborador::query(), $request)->findOrFail($id);
        try {
            DB::transaction(fn () => $c->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'Colaborador em uso — não pode ser excluído.'], 409);
        }
        return response()->json(['message' => 'Colaborador excluído.']);
    }

    // ---- Sub-recurso: FAMÍLIA (scaffold vazio no legado, reescrito) ----

    public function familia(Request $request, $id)
    {
        $this->autorizar($request, 'view');
        $this->escopo(Colaborador::query(), $request)->findOrFail($id);
        $rows = DB::table('colaboradorfamilias as f')
            ->leftJoin('parentescos as p', 'p.id', '=', 'f.parentesco_id')
            ->where('f.colaborador_id', $id)->where('f.ativo', 1)
            ->orderBy('f.nome')->get(['f.id', 'f.nome', 'f.datanascimento', 'p.descricao as parentesco']);
        return response()->json(['data' => $rows]);
    }

    public function addFamilia(Request $request, $id)
    {
        $this->autorizar($request, 'edit');
        $c = $this->escopo(Colaborador::query(), $request)->findOrFail($id);
        $data = $request->validate([
            'nome' => 'required|string|max:255', 'parentesco_id' => 'required|integer', 'datanascimento' => 'nullable|date',
        ]);
        $novoId = DB::table('colaboradorfamilias')->insertGetId([
            'grupo_id' => $c->grupo_id, 'empresa_id' => $c->empresa_id, 'colaborador_id' => $c->id,
            'parentesco_id' => $data['parentesco_id'], 'nome' => $data['nome'],
            'datanascimento' => $data['datanascimento'] ?? null, 'ativo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['id' => $novoId], 201);
    }

    public function delFamilia(Request $request, $id, $famId)
    {
        $this->autorizar($request, 'edit');
        $this->escopo(Colaborador::query(), $request)->findOrFail($id);
        DB::table('colaboradorfamilias')->where('colaborador_id', $id)->where('id', $famId)->delete();
        return response()->json(['message' => 'Familiar removido.']);
    }

    // ---- Sub-recurso: RECESSOS ----

    public function recessos(Request $request, $id)
    {
        $this->autorizar($request, 'view');
        $this->escopo(Colaborador::query(), $request)->findOrFail($id);
        // Recessos ligam-se ao colaborador via recesso_id? No legado é por colaborador; lista por empresa+colaborador.
        $rows = DB::table('recessos')->where('recesso_id', $id)
            ->orderByDesc('datainicio')->get(['id', 'descricao', 'datainicio', 'datafinal', 'tipo_id']);
        return response()->json(['data' => $rows]);
    }

    // ---- Sub-recurso: COMISSÕES ----

    public function comissoes(Request $request, $id)
    {
        $this->autorizar($request, 'view');
        $this->escopo(Colaborador::query(), $request)->findOrFail($id);
        $rows = DB::table('colaboradorcomissaos as cc')
            ->leftJoin('produtos as pr', 'pr.id', '=', 'cc.produto_id')
            ->where('cc.colaborador_id', $id)->where('cc.ativo', 1)
            ->get(['cc.id', 'cc.percentual', 'cc.empresavalor', 'cc.datainicio', 'cc.datafim', 'pr.descricao as produto']);
        return response()->json(['data' => $rows]);
    }
}
