<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Produto;
use App\ApiAdmin\Services\ProdutoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * F1 (SPA React) — API admin do PRODUTO (página completa, abas).
 *
 * Auditado de ProdutoController legado (docs/01-vigente/IMPL_PRODUTO.md).
 * Escopado por empresa (support vê todas). Reusa App\Produto; regras de
 * negócio (GLP/origens/inativar/decimal) no ProdutoService. RBAC produto.*.
 */
class ProdutoController extends Controller
{
    public function __construct(private ProdutoService $service)
    {
    }

    private function escopo($query, Request $request)
    {
        $user = $request->user();
        if ((string) $user->support === '1') {
            return $query;
        }
        return $query->where('empresa_id', $user->empresa_id);
    }

    /** GET /api/admin/produtos?q=&page=&per_page= */
    public function index(Request $request)
    {
        $this->autorizar($request, 'produto.view');

        $q = trim((string) $request->query('q', ''));
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = $this->escopo(Produto::query(), $request)
            ->leftJoin('produtoclasses as pc', 'produtos.produtoclasse_id', '=', 'pc.id')
            ->select(
                'produtos.id', 'produtos.descricao', 'produtos.precovenda', 'produtos.ativo',
                'produtos.nfepermite', 'produtos.produtoclasse_id', 'pc.descricao as classe'
            );

        if ($q !== '') {
            $like = '%' . str_replace(' ', '%', $q) . '%';
            $query->where(function ($w) use ($like, $q) {
                $w->where('produtos.descricao', 'ilike', $like)
                  ->orWhere('produtos.ean', 'ilike', $like);
                if (ctype_digit($q)) {
                    $w->orWhere('produtos.id', (int) $q);
                }
            });
        }

        $page = $query->orderBy('produtos.descricao')->orderBy('produtos.id')->paginate($perPage);

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

    /** GET /api/admin/produtos/{id} */
    public function show(Request $request, $id)
    {
        $this->autorizar($request, 'produto.view');
        $produto = $this->escopo(Produto::query(), $request)->findOrFail($id);
        $produto->load(['origens', 'origens.uf']);

        $extra = [
            'classe_label'      => optional(\App\Produtoclasse::find($produto->produtoclasse_id))->descricao,
            'unidade_label'     => optional(\App\Unidademedida::find($produto->unidademedida_id))->descricao,
            'grupofiscal_label' => $produto->nfgrupofiscal_id ? optional(\App\Nfgrupofiscal::find($produto->nfgrupofiscal_id))->descricao : null,
            'vasilhame_label'   => $produto->produtoretornavel_id ? optional(Produto::find($produto->produtoretornavel_id))->descricao : null,
            'origens'           => $produto->origens->map(fn ($o) => [
                'id'        => $o->id,
                'indimport' => (int) $o->indimport,
                'cuforig'   => (int) $o->cuforig,
                'porig'     => (float) $o->porig,
                'uf'        => optional($o->uf)->uf,
            ])->values(),
        ];

        return response()->json(['data' => array_merge($produto->toArray(), $extra)]);
    }

    /** POST /api/admin/produtos */
    public function store(Request $request)
    {
        $this->autorizar($request, 'produto.create');
        $payload = $this->validar($request);
        $user = $request->user();
        $grupoId = (int) (optional($user->empresa)->grupo_id ?? $user->grupo_id);
        $empresaId = (int) $user->empresa_id;

        try {
            $dados = $this->service->prepararDados($payload, $grupoId, $empresaId);
            $origens = $this->service->normalizarOrigens($payload['origens'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $produto = DB::transaction(function () use ($dados, $origens) {
            $p = Produto::create($dados);
            $this->service->salvarOrigens($p, $origens);
            return $p;
        });

        return response()->json(['data' => $produto->fresh()], 201);
    }

    /** PUT /api/admin/produtos/{id} */
    public function update(Request $request, $id)
    {
        $this->autorizar($request, 'produto.edit');
        $produto = $this->escopo(Produto::query(), $request)->findOrFail($id);
        $payload = $this->validar($request);
        $grupoId = (int) $produto->grupo_id;
        $empresaId = (int) $produto->empresa_id;

        try {
            $dados = $this->service->prepararDados($payload, $grupoId, $empresaId);
            $origens = $this->service->normalizarOrigens($payload['origens'] ?? []);

            // REGRA: não inativar produto com saldo em estoque.
            if ($dados['ativo'] === 0) {
                $this->service->garantirPodeInativar((int) $produto->id);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        DB::transaction(function () use ($produto, $dados, $origens) {
            $produto->update($dados);
            $this->service->salvarOrigens($produto, $origens);
        });

        return response()->json(['data' => $produto->fresh()]);
    }

    /** DELETE /api/admin/produtos/{id} */
    public function destroy(Request $request, $id)
    {
        $this->autorizar($request, 'produto.delete');
        $produto = $this->escopo(Produto::query(), $request)->findOrFail($id);

        try {
            DB::transaction(fn () => $produto->delete());
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['message' => 'O produto não pôde ser excluído pois está sendo usado.'], 409);
        }

        return response()->json(['message' => 'Produto excluído.']);
    }

    /** GET /api/admin/produtos/{id}/origens — sub-recurso (lista com soma 100%). */
    public function origens(Request $request, $id)
    {
        $this->autorizar($request, 'produto.view');
        $produto = $this->escopo(Produto::query(), $request)->findOrFail($id);
        $produto->load('origens', 'origens.uf');
        return response()->json([
            'data' => $produto->origens->map(fn ($o) => [
                'id' => $o->id, 'indimport' => (int) $o->indimport,
                'cuforig' => (int) $o->cuforig, 'porig' => (float) $o->porig,
                'uf' => optional($o->uf)->uf,
            ])->values(),
        ]);
    }

    /** PUT /api/admin/produtos/{id}/origens — substitui as origens (soma 100%). */
    public function salvarOrigensEndpoint(Request $request, $id)
    {
        $this->autorizar($request, 'produto.edit');
        $produto = $this->escopo(Produto::query(), $request)->findOrFail($id);

        try {
            $origens = $this->service->normalizarOrigens($request->input('origens', []));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        DB::transaction(fn () => $this->service->salvarOrigens($produto, $origens));
        return response()->json(['message' => 'Origens salvas.']);
    }

    /**
     * Validação condicional (paridade ProdutoRequest §4):
     *  - sempre: produtoclasse_id, descricao, unidademedida_id, vasilhameretornavel;
     *  - nfepermite=1: + nfgrupofiscal_id, nfedescricaofiscal; se classe é GLP: + ANP/CIDE + origens;
     *  - sped=1: + nfetipoitem; vasilhameretornavel=1: + produtoretornavel_id.
     */
    private function validar(Request $request): array
    {
        $regras = [
            'produtoclasse_id'    => 'required|integer',
            'descricao'           => 'required|string|max:50',
            'unidademedida_id'    => 'required|integer',
            'vasilhameretornavel' => 'required|boolean',
            // Demais campos (opcionais)
            'produtoretornavel_id' => 'nullable|integer',
            'ativo'               => 'nullable|boolean',
            'enviaappnf'          => 'nullable|boolean',
            'diasgiro'            => 'nullable|integer',
            'observacao'          => 'nullable|string|max:500',
            'precovenda'          => 'nullable',
            'precovendaminimo'    => 'nullable',
            'customedio'          => 'nullable',
            'custofrete'          => 'nullable',
            'precogasdopovo'      => 'nullable',
            'pesoliquido'         => 'nullable',
            'pesobruto'           => 'nullable',
            'ean'                 => 'nullable|string|max:14',
            'eantrib'             => 'nullable|string|max:14',
            'ncm'                 => 'nullable|string|max:8',
            'nfcest'              => 'nullable|string|max:7',
            'especie'             => 'nullable|string|max:60',
            'marca'               => 'nullable|string|max:60',
            'nfepermite'          => 'nullable|boolean',
            'nfetipoitem'         => 'nullable|integer',
            'nfgrupofiscal_id'    => 'nullable|integer',
            'nfipi_id'            => 'nullable|integer',
            'nfealiqipi'          => 'nullable',
            'nfebcipi'            => 'nullable',
            'nfecodenquadramentoipi' => 'nullable|integer',
            'nfeextipi'           => 'nullable|string|max:50',
            'nfedescricaofiscal'  => 'nullable|string|max:50',
            'nfenatrec'           => 'nullable|string|max:50',
            'nfecprodanp'         => 'nullable|string|max:9',
            'nfedescanp'          => 'nullable|string',
            'nfeqbcprod'          => 'nullable',
            'nfevaliqprod'        => 'nullable',
            'nfevcide'            => 'nullable',
            'nfecodgen'           => 'nullable|integer',
            'nfecodlst'           => 'nullable|integer',
            'tipo_glp'            => 'nullable|integer',
            'ressarcimentoproduto_id' => 'nullable|integer',
            'pgni'                => 'nullable',
            'pgnn'                => 'nullable',
            'pglp'                => 'nullable',
            'origens'             => 'nullable|array',
        ];

        if ($request->boolean('nfepermite')) {
            $regras['nfgrupofiscal_id'] = 'required|integer';
            $regras['nfedescricaofiscal'] = 'required|string|max:50';

            if ($this->classeEhGlp($request)) {
                $regras['nfecprodanp'] = 'required|string|max:9';
                $regras['nfedescanp'] = 'required|string';
                $regras['nfeqbcprod'] = 'required';
                $regras['nfevaliqprod'] = 'required';
                $regras['nfevcide'] = 'required';
                $regras['origens'] = 'required|array|min:1';
            }
        }

        if ($request->boolean('sped')) {
            $regras['nfetipoitem'] = 'required|integer';
        }

        if ($request->boolean('vasilhameretornavel')) {
            $regras['produtoretornavel_id'] = 'required|integer';
        }

        $msgs = [
            'descricao.required'         => 'O campo Nome do Produto é obrigatório.',
            'produtoclasse_id.required'  => 'Selecionar classe do produto.',
            'unidademedida_id.required'  => 'Selecionar unidade de medida.',
            'nfgrupofiscal_id.required'  => 'Selecionar um Grupo Fiscal.',
            'nfedescricaofiscal.required' => 'O campo Nome Fiscal é obrigatório.',
            'nfecprodanp.required'       => 'O campo Código ANP é obrigatório.',
            'nfedescanp.required'        => 'O campo Descrição ANP é obrigatório.',
            'origens.required'           => 'Tabela de Origem de Combustível é obrigatória para GLP.',
            'produtoretornavel_id.required' => 'Um produto do tipo vasilhame deve ser selecionado.',
        ];

        return $request->validate($regras, $msgs);
    }

    /** A classe selecionada é GLP (tipo 'G') no grupo da empresa? */
    private function classeEhGlp(Request $request): bool
    {
        $classeId = (int) $request->input('produtoclasse_id');
        if (! $classeId) {
            return false;
        }
        $user = $request->user();
        $grupoId = optional($user->empresa)->grupo_id ?? $user->grupo_id;
        return \App\Produtoclasse::where('id', $classeId)
            ->where('grupo_id', $grupoId)
            ->where('tipo', 'G')
            ->exists();
    }

    private function autorizar(Request $request, string $permissao): void
    {
        $user = $request->user();
        [$modulo, $acao] = explode('.', $permissao);
        abort_unless($user->podeRecurso($modulo, $acao), 403, 'Sem permissão.');
    }
}
