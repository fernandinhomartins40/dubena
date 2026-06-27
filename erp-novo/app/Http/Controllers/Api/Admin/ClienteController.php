<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Acesso\CamposPermitidos;
use App\Domain\Cliente\ClienteService;
use App\Domain\Relatorio\RelatorioService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente\Cliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cliente (também fornecedor/transportador) — N2.
 * CRUD escopado por empresa (BelongsToTenant). Lista paginada com busca.
 * Field-level (A7) em campos de crédito/convênio; export gated por cliente.export.
 */
class ClienteController extends Controller
{
    use AutorizaPorPermissao;

    /** Campos sob controle field-level na escrita (espelha o Resource). */
    private const CAMPOS_SENSIVEIS = ['credito_limite', 'credito_saldo', 'convenio_limite'];

    public function __construct(
        private ClienteService $service,
        private CamposPermitidos $campos,
    ) {}

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

        $dados = $this->semCamposBloqueados($request);
        $cliente = $this->service->criar($dados);

        return (new ClienteResource($cliente))->response()->setStatusCode(201);
    }

    public function update(ClienteRequest $request, int $id): ClienteResource
    {
        $this->autorizar($request, 'cliente.edit');

        $cliente = Cliente::query()->findOrFail($id);
        $dados = $this->semCamposBloqueados($request);

        return new ClienteResource($this->service->atualizar($cliente, $dados));
    }

    /** GET /clientes/exportar — CSV. Gated por cliente.export (A7). */
    public function exportar(Request $request, RelatorioService $relatorio): Response
    {
        $this->autorizar($request, 'cliente.export');

        $linhas = Cliente::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'fantasia', 'cpf', 'cnpj', 'email', 'ativo'])
            ->map(fn (Cliente $c) => [
                'id' => $c->id, 'nome' => $c->nome, 'fantasia' => $c->fantasia,
                'cpf' => $c->cpf, 'cnpj' => $c->cnpj, 'email' => $c->email,
                'ativo' => $c->ativo ? 'Sim' : 'Não',
            ])->all();

        return response($relatorio->csv($linhas), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="clientes.csv"',
        ]);
    }

    /**
     * Remove dos dados validados os campos field-level que o usuário não pode
     * editar (sem `cliente.campo.{nome}.edit`) — defense-in-depth no backend.
     *
     * @return array<string, mixed>
     */
    private function semCamposBloqueados(ClienteRequest $request): array
    {
        return $this->campos->filtrarEscrita(
            $request->user(), 'cliente', $request->validated(), self::CAMPOS_SENSIVEIS,
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'cliente.delete');

        $this->service->excluir(Cliente::query()->findOrFail($id));

        return response()->json(['message' => 'Cliente excluído.']);
    }
}
