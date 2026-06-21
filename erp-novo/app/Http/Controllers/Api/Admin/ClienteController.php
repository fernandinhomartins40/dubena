<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Cliente\ClienteService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cliente (também fornecedor/transportador) — N2.
 * CRUD escopado por empresa (BelongsToTenant). Lista paginada com busca.
 */
class ClienteController extends Controller
{
    public function __construct(private ClienteService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');
        $q = trim((string) $request->query('q', ''));

        $clientes = Cliente::query()
            ->when($q !== '', function (Builder $b) use ($q) {
                $b->where(function (Builder $w) use ($q) {
                    $w->where('nome', 'ilike', '%'.$q.'%')
                        ->orWhere('fantasia', 'ilike', '%'.$q.'%')
                        ->orWhere('cpf', 'ilike', '%'.$q.'%')
                        ->orWhere('cnpj', 'ilike', '%'.$q.'%');
                });
            })
            ->orderBy('nome')
            ->paginate(20);

        return ClienteResource::collection($clientes)->response();
    }

    public function show(Request $request, int $id): ClienteResource
    {
        $this->autorizar($request, 'cliente.view');

        return new ClienteResource(Cliente::query()->with('telefones')->findOrFail($id));
    }

    public function store(ClienteRequest $request): JsonResponse
    {
        $this->autorizar($request, 'cliente.create');

        $cliente = $this->service->criar($request->validated());

        return (new ClienteResource($cliente))->response()->setStatusCode(201);
    }

    public function update(ClienteRequest $request, int $id): ClienteResource
    {
        $this->autorizar($request, 'cliente.edit');

        $cliente = Cliente::query()->findOrFail($id);

        return new ClienteResource($this->service->atualizar($cliente, $request->validated()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'cliente.delete');

        $this->service->excluir(Cliente::query()->findOrFail($id));

        return response()->json(['message' => 'Cliente excluído.']);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
