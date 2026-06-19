<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Cidade;
use App\Bairro;
use App\Rua;
use App\Regiao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F2 (SPA React) — API admin GEOGRÁFICO (Cidade/Bairro/Rua/Região).
 * Auditado: Cidade/Bairro/Rua/RegiaoController (docs/01-vigente/IMPL_GEOGRAFICO.md).
 * REORGANIZAÇÃO: 4 telas dispersas → 1 página com abas. Regras preservadas:
 *   - unicidade: cidade(descricao+uf), bairro/rua(descricao+cidade_id) no grupo OU global;
 *   - Cidade respeita registros globais (grupo_id null);
 *   - Rua defaults: importacaocep_id=-1, empresa_id, nfecompl='Rua';
 *   - destroy com FK amigável (não 500).
 * RBAC: cidade.*, bairro.*, rua.*, regiao.*.
 */
class GeograficoController extends Controller
{
    private function grupoId(Request $request): int
    {
        $user = $request->user();
        return (int) (optional($user->empresa)->grupo_id ?? $user->grupo_id);
    }

    private function autorizar(Request $request, string $modulo, string $acao): void
    {
        abort_unless($request->user()->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }

    /** Exclusão com tratamento de FK (paridade com os controllers legados). */
    private function excluir($model, string $rotulo)
    {
        try {
            DB::transaction(fn () => $model->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => "$rotulo está em uso e não pode ser excluído(a)."], 409);
        }
        return response()->json(['message' => "$rotulo excluído(a)."]);
    }

    // =================== CIDADES ===================

    /** GET /api/admin/geo/cidades?q=&uf= — inclui cidades globais (grupo_id null). */
    public function cidades(Request $request)
    {
        $this->autorizar($request, 'cidade', 'view');
        $grupo = $this->grupoId($request);
        $q = trim((string) $request->query('q', ''));
        $uf = trim((string) $request->query('uf', ''));

        $page = Cidade::query()
            ->where(fn ($w) => $w->whereNull('grupo_id')->orWhere('grupo_id', $grupo))
            ->when($uf !== '', fn ($w) => $w->where('uf', $uf))
            ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
            ->orderBy('descricao')->orderBy('id')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return $this->paginado($page);
    }

    public function salvarCidade(Request $request, $id = null)
    {
        $this->autorizar($request, 'cidade', $id ? 'edit' : 'create');
        $grupo = $this->grupoId($request);
        $data = $request->validate([
            'descricao' => 'required|string|max:255',
            'uf'        => 'required|string|max:2',
            'cod_ibge'  => 'required|numeric',
        ]);

        // Unicidade descricao+uf no grupo OU global (getCidadesIguais).
        $existe = Cidade::whereRaw('lower(descricao) = ?', [mb_strtolower($data['descricao'])])
            ->where('uf', $data['uf'])
            ->where(fn ($w) => $w->whereNull('grupo_id')->orWhere('grupo_id', $grupo))
            ->when($id, fn ($w) => $w->where('id', '<>', $id))->exists();
        if ($existe) {
            return response()->json(['message' => 'Já existe uma cidade com este nome para este estado.'], 422);
        }

        if ($id) {
            $cidade = Cidade::where(fn ($w) => $w->whereNull('grupo_id')->orWhere('grupo_id', $grupo))->findOrFail($id);
            $cidade->update($data);
        } else {
            $cidade = Cidade::create($data + ['grupo_id' => $grupo]);
        }
        return response()->json(['data' => $cidade], $id ? 200 : 201);
    }

    public function excluirCidade(Request $request, $id)
    {
        $this->autorizar($request, 'cidade', 'delete');
        $cidade = Cidade::where('grupo_id', $this->grupoId($request))->findOrFail($id);
        return $this->excluir($cidade, 'Cidade');
    }

    // =================== BAIRROS ===================

    /** GET /api/admin/geo/bairros?q=&cidade_id= */
    public function bairros(Request $request)
    {
        $this->autorizar($request, 'bairro', 'view');
        $grupo = $this->grupoId($request);
        $q = trim((string) $request->query('q', ''));
        $cidadeId = (int) $request->query('cidade_id', 0);

        $page = Bairro::query()->with('cidade:id,descricao')->where('grupo_id', $grupo)
            ->when($cidadeId, fn ($w) => $w->where('cidade_id', $cidadeId))
            ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
            ->orderBy('descricao')->orderBy('id')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        $page->getCollection()->transform(fn ($b) => [
            'id' => $b->id, 'descricao' => $b->descricao, 'cidade_id' => $b->cidade_id,
            'cidade' => optional($b->cidade)->descricao,
        ]);
        return $this->paginado($page);
    }

    public function salvarBairro(Request $request, $id = null)
    {
        $this->autorizar($request, 'bairro', $id ? 'edit' : 'create');
        $grupo = $this->grupoId($request);
        $data = $request->validate([
            'descricao' => 'required|string|max:255',
            'cidade_id' => 'required|integer',
        ]);

        $existe = Bairro::whereRaw('lower(descricao) = ?', [mb_strtolower($data['descricao'])])
            ->where('cidade_id', $data['cidade_id'])
            ->where(fn ($w) => $w->whereNull('grupo_id')->orWhere('grupo_id', $grupo))
            ->when($id, fn ($w) => $w->where('id', '<>', $id))->exists();
        if ($existe) {
            return response()->json(['message' => 'Já existe um bairro com este nome para esta cidade.'], 422);
        }

        if ($id) {
            $bairro = Bairro::where('grupo_id', $grupo)->findOrFail($id);
            $bairro->update($data);
        } else {
            $bairro = Bairro::create($data + ['grupo_id' => $grupo]);
        }
        return response()->json(['data' => $bairro], $id ? 200 : 201);
    }

    public function excluirBairro(Request $request, $id)
    {
        $this->autorizar($request, 'bairro', 'delete');
        $bairro = Bairro::where('grupo_id', $this->grupoId($request))->findOrFail($id);
        return $this->excluir($bairro, 'Bairro');
    }

    // =================== RUAS ===================

    /** GET /api/admin/geo/ruas?q=&cidade_id= */
    public function ruas(Request $request)
    {
        $this->autorizar($request, 'rua', 'view');
        $grupo = $this->grupoId($request);
        $q = trim((string) $request->query('q', ''));
        $cidadeId = (int) $request->query('cidade_id', 0);

        $page = Rua::query()->with('cidade:id,descricao')->where('grupo_id', $grupo)
            ->when($cidadeId, fn ($w) => $w->where('cidade_id', $cidadeId))
            ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
            ->orderBy('descricao')->orderBy('id')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        $page->getCollection()->transform(fn ($r) => [
            'id' => $r->id, 'descricao' => $r->descricao, 'cidade_id' => $r->cidade_id,
            'cidade' => optional($r->cidade)->descricao, 'cep' => $r->cep, 'ativo' => $r->ativo,
        ]);
        return $this->paginado($page);
    }

    public function salvarRua(Request $request, $id = null)
    {
        $this->autorizar($request, 'rua', $id ? 'edit' : 'create');
        $grupo = $this->grupoId($request);
        $empresaId = (int) $request->user()->empresa_id;
        $data = $request->validate([
            'descricao' => 'required|string|max:255',
            'cidade_id' => 'required|integer',
            'cep'       => 'nullable|string|max:9',
            'ativo'     => 'nullable|boolean',
        ]);

        $existe = Rua::whereRaw('lower(descricao) = ?', [mb_strtolower($data['descricao'])])
            ->where('cidade_id', $data['cidade_id'])
            ->where(fn ($w) => $w->whereNull('grupo_id')->orWhere('grupo_id', $grupo))
            ->when($id, fn ($w) => $w->where('id', '<>', $id))->exists();
        if ($existe) {
            return response()->json(['message' => 'Já existe uma rua com este nome para esta cidade.'], 422);
        }

        $data['ativo'] = isset($data['ativo']) ? (int) (! empty($data['ativo'])) : 1;

        if ($id) {
            $rua = Rua::where('grupo_id', $grupo)->findOrFail($id);
            $rua->update($data);
        } else {
            // Defaults do legado (RuaController:127-130).
            $rua = Rua::create($data + [
                'grupo_id' => $grupo, 'empresa_id' => $empresaId,
                'nfecompl' => 'Rua', 'importacaocep_id' => -1,
            ]);
        }
        return response()->json(['data' => $rua], $id ? 200 : 201);
    }

    public function excluirRua(Request $request, $id)
    {
        $this->autorizar($request, 'rua', 'delete');
        $rua = Rua::where('grupo_id', $this->grupoId($request))->findOrFail($id);
        return $this->excluir($rua, 'Rua');
    }

    // =================== REGIÕES ===================

    /** GET /api/admin/geo/regioes?q= */
    public function regioes(Request $request)
    {
        $this->autorizar($request, 'regiao', 'view');
        $grupo = $this->grupoId($request);
        $q = trim((string) $request->query('q', ''));

        $page = Regiao::query()->where('grupo_id', $grupo)
            ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
            ->orderBy('descricao')->orderBy('id')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return $this->paginado($page);
    }

    public function salvarRegiao(Request $request, $id = null)
    {
        $this->autorizar($request, 'regiao', $id ? 'edit' : 'create');
        $grupo = $this->grupoId($request);
        $data = $request->validate([
            'descricao' => 'required|string|max:255',
            'ativo'     => 'nullable|boolean',
        ]);
        $data['ativo'] = isset($data['ativo']) ? (int) (! empty($data['ativo'])) : 0;

        if ($id) {
            $regiao = Regiao::where('grupo_id', $grupo)->findOrFail($id);
            $regiao->update($data);
        } else {
            $regiao = Regiao::create($data + ['grupo_id' => $grupo]);
        }
        return response()->json(['data' => $regiao], $id ? 200 : 201);
    }

    public function excluirRegiao(Request $request, $id)
    {
        $this->autorizar($request, 'regiao', 'delete');
        $regiao = Regiao::where('grupo_id', $this->grupoId($request))->findOrFail($id);
        return $this->excluir($regiao, 'Região');
    }

    // =================== util ===================

    private function paginado($page)
    {
        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }
}
