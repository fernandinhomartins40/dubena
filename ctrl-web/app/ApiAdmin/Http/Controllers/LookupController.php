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

    /** GET /api/admin/lookups/produto-classes */
    public function produtoClasses(Request $request)
    {
        return $this->porGrupoAtivo($request, \App\Produtoclasse::query());
    }

    /** GET /api/admin/lookups/unidades */
    public function unidades(Request $request)
    {
        $grupoId = optional(optional($request->user())->empresa)->grupo_id;
        return response()->json(
            \App\Unidademedida::query()->where('ativo', 1)
                ->when($grupoId, fn ($w) => $w->where('grupo_id', $grupoId))
                ->orderBy('descricao')->get()
                ->map(fn ($u) => ['id' => $u->id, 'label' => $u->descricao . ($u->sigla ? " ({$u->sigla})" : '')])
        );
    }

    /** GET /api/admin/lookups/nf-grupos-fiscais */
    public function nfGruposFiscais(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        return response()->json(
            \App\Nfgrupofiscal::query()->where('ativo', 1)
                ->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->orderBy('descricao')->get()
                ->map(fn ($g) => ['id' => $g->id, 'label' => $g->descricao])
        );
    }

    /** GET /api/admin/lookups/ipis */
    public function ipis(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        return response()->json(
            \App\Nfipi::query()
                ->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->orderBy('codigo')->get()
                ->map(fn ($i) => ['id' => $i->id, 'label' => trim(($i->codigo ?? '') . ' - ' . ($i->descricao ?? ''), ' -')])
        );
    }

    /** GET /api/admin/lookups/estados */
    public function estados(Request $request)
    {
        return response()->json(
            \App\Estado::query()->orderBy('descricao')->get()
                ->map(fn ($e) => ['id' => $e->cod_ibge, 'label' => $e->descricao, 'uf' => $e->uf])
        );
    }

    /** GET /api/admin/lookups/produtos-vasilhame — produtos cuja classe é vasilhame (tipo 'V'). */
    public function produtosVasilhame(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        $grupoId = optional(optional($request->user())->empresa)->grupo_id;
        $classes = \App\Produtoclasse::where('ativo', 1)
            ->when($grupoId, fn ($w) => $w->where('grupo_id', $grupoId))
            ->where('tipo', 'V')->pluck('id');
        return response()->json(
            DB::table('produtos')->where('ativo', 1)
                ->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->whereIn('produtoclasse_id', $classes)
                ->orderBy('descricao')->get(['id', 'descricao'])
                ->map(fn ($p) => ['id' => $p->id, 'label' => $p->descricao])
        );
    }

    /** GET /api/admin/lookups/produtos-ressarcimento — produtos cuja classe é ressarcimento (tipo 'R'). */
    public function produtosRessarcimento(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        return response()->json(
            DB::table('produtos')->join('produtoclasses', 'produtos.produtoclasse_id', '=', 'produtoclasses.id')
                ->where('produtos.ativo', 1)
                ->when($empresaId, fn ($w) => $w->where('produtos.empresa_id', $empresaId))
                ->where('produtoclasses.tipo', 'R')
                ->orderBy('produtos.descricao')->get(['produtos.id', 'produtos.descricao'])
                ->map(fn ($p) => ['id' => $p->id, 'label' => $p->descricao])
        );
    }

    /** GET /api/admin/lookups/tipo-glp — lista fixa (ProdutoController::tipoGlp). */
    public function tipoGlp()
    {
        $tipos = [
            1 => 'GLP P02', 2 => 'GLP P08', 3 => 'GLP P13',
            4 => 'GLP P20', 5 => 'GLP P45', 6 => 'GLP P90',
        ];
        return response()->json(
            collect($tipos)->map(fn ($l, $id) => ['id' => $id, 'label' => $l])->values()
        );
    }

    /** GET /api/admin/lookups/planos-conta */
    public function planosConta(Request $request)
    {
        $grupoId = optional(optional($request->user())->empresa)->grupo_id;
        $q = trim((string) $request->query('q', ''));
        return response()->json(
            DB::table('planocontas')->when($grupoId, fn ($w) => $w->where('grupo_id', $grupoId))
                ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
                ->orderBy('descricao')->limit(50)->get(['id', 'descricao', 'codigo'])
                ->map(fn ($p) => ['id' => $p->id, 'label' => trim(($p->codigo ? $p->codigo . ' - ' : '') . $p->descricao)])
        );
    }

    /** GET /api/admin/lookups/centros-custo */
    public function centrosCusto(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        $q = trim((string) $request->query('q', ''));
        return response()->json(
            DB::table('centrocustos')->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
                ->orderBy('descricao')->limit(50)->get(['id', 'descricao', 'codigo'])
                ->map(fn ($c) => ['id' => $c->id, 'label' => trim(($c->codigo ? $c->codigo . ' - ' : '') . $c->descricao)])
        );
    }

    /** GET /api/admin/lookups/contas */
    public function contas(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        return response()->json(
            DB::table('contas')->where('ativo', 1)->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->orderBy('descricao')->get(['id', 'descricao'])
                ->map(fn ($c) => ['id' => $c->id, 'label' => $c->descricao])
        );
    }

    /** GET /api/admin/lookups/setores */
    public function setores(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        return response()->json(
            DB::table('setors')->where('ativo', 1)->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->orderBy('descricao')->get(['id', 'descricao'])
                ->map(fn ($s) => ['id' => $s->id, 'label' => $s->descricao])
        );
    }

    /** GET /api/admin/lookups/regioes */
    public function regioes(Request $request)
    {
        return $this->porGrupoAtivo($request, \App\Regiao::query());
    }

    /** GET /api/admin/lookups/cargos */
    public function cargos(Request $request)
    {
        $grupoId = optional(optional($request->user())->empresa)->grupo_id;
        return response()->json(
            DB::table('cargos')->where('ativo', 1)
                ->when($grupoId, fn ($w) => $w->where('grupo_id', $grupoId))
                ->orderBy('descricao')->get(['id', 'descricao'])
                ->map(fn ($c) => ['id' => $c->id, 'label' => $c->descricao])
        );
    }

    /** GET /api/admin/lookups/pedido-situacoes */
    public function pedidoSituacoes(Request $request)
    {
        $grupoId = optional(optional($request->user())->empresa)->grupo_id;
        return response()->json(
            DB::table('pedidosituacaos')->where('ativo', 1)
                ->when($grupoId, fn ($w) => $w->where('grupo_id', $grupoId))
                ->orderBy('id')->get(['id', 'descricao'])
                ->map(fn ($s) => ['id' => $s->id, 'label' => $s->descricao])
        );
    }

    /** GET /api/admin/lookups/ruas?cidade_id=&q= */
    public function ruas(Request $request)
    {
        $grupoId = optional(optional($request->user())->empresa)->grupo_id;
        $cidadeId = (int) $request->query('cidade_id', 0);
        $q = trim((string) $request->query('q', ''));
        if (! $cidadeId) {
            return response()->json([]);
        }
        return response()->json(
            DB::table('ruas')->where('cidade_id', $cidadeId)
                ->when($grupoId, fn ($w) => $w->where('grupo_id', $grupoId))
                ->when($q !== '', fn ($w) => $w->where('descricao', 'ilike', '%' . $q . '%'))
                ->orderBy('descricao')->limit(50)->get(['id', 'descricao'])
                ->map(fn ($r) => ['id' => $r->id, 'label' => $r->descricao])
        );
    }

    /** GET /api/admin/lookups/pedido-operacoes */
    public function pedidoOperacoes(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        return response()->json(
            DB::table('pedidooperacaos')->where('ativo', 1)
                ->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->orderBy('descricao')->get(['id', 'descricao'])
                ->map(fn ($o) => ['id' => $o->id, 'label' => $o->descricao])
        );
    }

    /** GET /api/admin/lookups/colaboradores */
    public function colaboradores(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        $q = trim((string) $request->query('q', ''));
        return response()->json(
            DB::table('colaboradors')->where('ativo', 1)
                ->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->when($q !== '', fn ($w) => $w->where('nome', 'ilike', '%' . $q . '%'))
                ->orderBy('nome')->limit(30)->get(['id', 'nome'])
                ->map(fn ($c) => ['id' => $c->id, 'label' => $c->nome])
        );
    }

    /** GET /api/admin/lookups/veiculo-tipos */
    public function veiculoTipos(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        return response()->json(
            DB::table('veiculotipos')->where('ativo', 1)
                ->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->orderBy('descricao')->get(['id', 'descricao'])
                ->map(fn ($t) => ['id' => $t->id, 'label' => $t->descricao])
        );
    }

    /** GET /api/admin/lookups/combustiveis */
    public function combustiveis(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        return response()->json(
            DB::table('tipocombustivels')->where('ativo', 1)
                ->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->orderBy('descricao')->get(['id', 'descricao'])
                ->map(fn ($t) => ['id' => $t->id, 'label' => $t->descricao])
        );
    }

    /** GET /api/admin/lookups/clientes-fornecedores?q= */
    public function clientesFornecedores(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        $q = trim((string) $request->query('q', ''));
        return response()->json(
            DB::table('clientes')
                ->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->when($q !== '', fn ($w) => $w->where('nome', 'ilike', '%' . $q . '%'))
                ->orderBy('nome')->limit(30)->get(['id', 'nome'])
                ->map(fn ($c) => ['id' => $c->id, 'label' => $c->nome])
        );
    }

    /** GET /api/admin/lookups/condicoes-pagamento */
    public function condicoesPagamento(Request $request)
    {
        $empresaId = optional($request->user())->empresa_id;
        return response()->json(
            DB::table('condicaopagamentos')->where('ativo', 1)
                ->when($empresaId, fn ($w) => $w->where('empresa_id', $empresaId))
                ->orderBy('descricao')->get(['id', 'descricao'])
                ->map(fn ($c) => ['id' => $c->id, 'label' => $c->descricao])
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
