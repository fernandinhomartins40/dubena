<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Acesso\CamposPermitidos;
use App\Domain\Produto\ProdutoService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProdutoRequest;
use App\Http\Resources\ProdutoResource;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Produto (classificação fiscal/GLP) — N3. CRUD escopado por empresa.
 */
class ProdutoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(
        private ProdutoService $service,
        private CamposPermitidos $campos,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'produto.view');
        $q = trim((string) $request->query('q', ''));

        $produtos = Produto::query()
            ->when($q !== '', fn (Builder $b) => $b->where('descricao', 'ilike', '%'.$q.'%'))
            ->orderBy('descricao')
            ->paginate(20);

        return ProdutoResource::collection($produtos)->response();
    }

    public function show(Request $request, int $id): ProdutoResource
    {
        $this->autorizar($request, 'produto.view');

        return new ProdutoResource(
            Produto::query()->with(['origens', 'classe', 'unidade', 'retornavel', 'grupoFiscal'])->findOrFail($id),
        );
    }

    public function store(ProdutoRequest $request): JsonResponse
    {
        $this->autorizar($request, 'produto.create');

        $produto = $this->service->criar($this->dadosPermitidos($request));

        return (new ProdutoResource($produto))->response()->setStatusCode(201);
    }

    public function update(ProdutoRequest $request, int $id): ProdutoResource
    {
        $this->autorizar($request, 'produto.edit');

        $produto = Produto::query()->findOrFail($id);

        $dados = $this->dadosPermitidos($request);
        // PIE P2-E-05: preço é controle de margem e tem permissão própria
        // (`produto.preco`), que até aqui só o reajuste em massa exigia — o
        // Operador mudava 100 → 1 pelo cadastro. Sem ela, o campo é ignorado,
        // como já se faz com o custo.
        if (Gate::forUser($request->user())->denies('produto.preco')) {
            unset($dados['preco_venda'], $dados['preco_venda_minimo'], $dados['preco_gasdopovo']);
        }

        return new ProdutoResource($this->service->atualizar($produto, $dados));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'produto.delete');

        $this->service->excluir(Produto::query()->findOrFail($id));

        return response()->json(['message' => 'Produto excluído.']);
    }

    /** @return array<string,mixed> */
    private function dadosPermitidos(ProdutoRequest $request): array
    {
        $dados = $request->validated();
        if (! $this->campos->pode($request->user(), 'produto', 'custo', 'edit')) {
            unset($dados['custo_medio'], $dados['custo_frete']);
        }

        return $dados;
    }
}
