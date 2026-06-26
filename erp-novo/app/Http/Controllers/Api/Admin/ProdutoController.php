<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Produto\ProdutoService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProdutoRequest;
use App\Http\Resources\ProdutoResource;
use App\Models\Produto\Produto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Produto (classificação fiscal/GLP) — N3. CRUD escopado por empresa.
 */
class ProdutoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private ProdutoService $service) {}

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

        return new ProdutoResource(Produto::query()->with('origens')->findOrFail($id));
    }

    public function store(ProdutoRequest $request): JsonResponse
    {
        $this->autorizar($request, 'produto.create');

        $produto = $this->service->criar($request->validated());

        return (new ProdutoResource($produto))->response()->setStatusCode(201);
    }

    public function update(ProdutoRequest $request, int $id): ProdutoResource
    {
        $this->autorizar($request, 'produto.edit');

        $produto = Produto::query()->findOrFail($id);

        return new ProdutoResource($this->service->atualizar($produto, $request->validated()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'produto.delete');

        $this->service->excluir(Produto::query()->findOrFail($id));

        return response()->json(['message' => 'Produto excluído.']);
    }
}
