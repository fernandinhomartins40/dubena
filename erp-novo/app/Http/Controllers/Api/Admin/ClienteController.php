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
            ->tap(fn (Builder $b) => $this->filtrarSituacao($b, $request))
            ->with('desativadoPor:id,name')
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

        return new ClienteResource(
            Cliente::query()
                ->with(['telefones', 'cidade', 'bairro', 'rua', 'tipopessoa', 'segmento'])
                ->findOrFail($id),
        );
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

        $atualizado = $this->service->atualizar($cliente, $dados);

        return new ClienteResource(
            $atualizado->load(['telefones', 'cidade', 'bairro', 'rua', 'tipopessoa', 'segmento']),
        );
    }

    /** GET /clientes/exportar — CSV. Gated por cliente.export (A7). */
    public function exportar(Request $request, RelatorioService $relatorio): Response
    {
        $this->autorizar($request, 'cliente.export');

        $linhas = Cliente::query()
            ->tap(fn (Builder $b) => $this->filtrarSituacao($b, $request))
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

    /**
     * DELETE /clientes/{id} — DESATIVA o cliente (não apaga).
     *
     * O verbo continua DELETE porque é a ação de "excluir" da tela; o efeito é
     * ativo = false. Apagar de verdade destruiria o histórico de pedidos e a
     * rastreabilidade fiscal — e era o que empurrava o operador a renomear o
     * cadastro para "FULANO - EXCLUIDO".
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'cliente.delete');

        $motivo = $request->validate([
            'motivo' => 'nullable|string|max:255',
        ])['motivo'] ?? null;

        $cliente = $this->service->desativar(
            Cliente::query()->findOrFail($id), $motivo, $request->user()?->id,
        );

        return response()->json([
            'message' => 'Cliente desativado. O histórico foi preservado.',
            'data' => new ClienteResource($cliente),
        ]);
    }

    /** POST /clientes/{id}/reativar — devolve o cliente à lista de ativos. */
    public function reativar(Request $request, int $id): JsonResponse
    {
        // Mesma permissão de desativar: quem tira da lista pode devolver.
        $this->autorizar($request, 'cliente.delete');

        $cliente = $this->service->reativar(Cliente::query()->findOrFail($id));

        return response()->json([
            'message' => 'Cliente reativado.',
            'data' => new ClienteResource($cliente),
        ]);
    }

    /**
     * Filtro de situação da lista. O DEFAULT é 'ativos': mostrar desativados
     * junto dos ativos é o que fazia a lista virar um depósito e levava o
     * operador a marcar o estado no próprio nome do cliente.
     */
    private function filtrarSituacao(Builder $b, Request $request): void
    {
        $situacao = (string) $request->query('situacao', 'ativos');

        match ($situacao) {
            'inativos' => $b->where('ativo', false),
            'todos' => null,
            // Cadastro antigo pode ter `ativo` NULL (migrado do legado sem o
            // campo): tratar NULL como ativo, senão ele sumiria da lista.
            default => $b->where(fn (Builder $w) => $w->where('ativo', true)->orWhereNull('ativo')),
        };
    }
}
