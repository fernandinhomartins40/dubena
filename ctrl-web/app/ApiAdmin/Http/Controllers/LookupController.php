<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Cidade;
use App\Bairro;
use App\Telefonetipo;
use App\Segmento;
use App\Tipopessoa;
use App\Parentesco;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * S2 — lookups para selects do SPA (cidade → bairro, etc.). Listas enxutas
 * {id,label} com busca por texto, para selects assíncronos no React.
 */
class LookupController extends Controller
{
    /** GET /api/admin/lookups/cidades?q= */
    public function cidades(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        return response()->json(
            Cidade::query()
                ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
                ->orderBy('descricao')
                ->limit(30)
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'label' => $c->descricao . ($c->uf ? " / {$c->uf}" : ''), 'uf' => $c->uf])
        );
    }

    /** GET /api/admin/lookups/bairros?cidade_id=&q= */
    public function bairros(Request $request)
    {
        $cidadeId = (int) $request->query('cidade_id', 0);
        $q = trim((string) $request->query('q', ''));

        if (! $cidadeId) {
            return response()->json([]);
        }

        return response()->json(
            Bairro::query()
                ->where('cidade_id', $cidadeId)
                ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
                ->orderBy('descricao')
                ->limit(50)
                ->get()
                ->map(fn ($b) => ['id' => $b->id, 'label' => $b->descricao])
        );
    }

    /** GET /api/admin/lookups/telefone-tipos */
    public function telefoneTipos(Request $request)
    {
        return $this->porGrupoAtivo($request, Telefonetipo::query());
    }

    /** GET /api/admin/lookups/segmentos */
    public function segmentos(Request $request)
    {
        return $this->porGrupoAtivo($request, Segmento::query());
    }

    /** GET /api/admin/lookups/parentescos */
    public function parentescos(Request $request)
    {
        return $this->porGrupoAtivo($request, Parentesco::query());
    }

    /** GET /api/admin/lookups/tipo-pessoa */
    public function tipoPessoa(Request $request)
    {
        // Tipopessoa pode não ter 'ativo'/'grupo_id' consistentes; lista todos.
        return response()->json(
            Tipopessoa::query()->orderBy('descricao')->get()
                ->map(fn ($t) => ['id' => $t->id, 'label' => $t->descricao])
        );
    }

    /** GET /api/admin/lookups/contato-tipos */
    public function contatoTipos(Request $request)
    {
        return $this->porGrupoTabela($request, 'clientecontatotipos');
    }

    /** GET /api/admin/lookups/contato-situacoes */
    public function contatoSituacoes(Request $request)
    {
        return $this->porGrupoTabela($request, 'clientecontatosituacaos');
    }

    /** GET /api/admin/lookups/produtos?q= */
    public function produtos(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $empresaId = optional($request->user())->empresa_id;
        return response()->json(
            DB::table('produtos')
                ->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
                ->orderBy('descricao')->limit(30)->get(['id', 'descricao'])
                ->map(fn ($p) => ['id' => $p->id, 'label' => $p->descricao])
        );
    }

    private function porGrupoAtivo(Request $request, $query)
    {
        $grupoId = optional(optional($request->user())->empresa)->grupo_id;
        return response()->json(
            $query->where('ativo', 1)
                ->when($grupoId, fn ($w) => $w->where('grupo_id', $grupoId))
                ->orderBy('descricao')->get()
                ->map(fn ($t) => ['id' => $t->id, 'label' => $t->descricao])
        );
    }

    private function porGrupoTabela(Request $request, string $tabela)
    {
        $grupoId = optional(optional($request->user())->empresa)->grupo_id;
        return response()->json(
            DB::table($tabela)->where('ativo', 1)
                ->when($grupoId, fn ($w) => $w->where('grupo_id', $grupoId))
                ->orderBy('descricao')->get(['id', 'descricao'])
                ->map(fn ($t) => ['id' => $t->id, 'label' => $t->descricao])
        );
    }
}
