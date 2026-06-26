<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Http\Requests\PedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pedidos / Vendas — N4. CRUD + kanban + transição de situação (máquina de estados).
 */
class PedidoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private PedidoService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'pedido.view');
        $q = trim((string) $request->query('q', ''));

        $pedidos = Pedido::query()
            ->with(['cliente:id,nome', 'situacao:id,descricao,efeito'])
            ->when($request->query('situacao_id'), fn (Builder $b, $s) => $b->where('pedidosituacao_id', $s))
            ->when($q !== '', fn (Builder $b) => $b->whereHas('cliente', fn ($w) => $w->where('nome', 'ilike', '%'.$q.'%')))
            ->orderByDesc('datahora')
            ->paginate(20);

        return PedidoResource::collection($pedidos)->response();
    }

    public function show(Request $request, int $id): PedidoResource
    {
        $this->autorizar($request, 'pedido.view');

        return new PedidoResource(
            Pedido::query()->with(['cliente:id,nome', 'situacao', 'itens'])->findOrFail($id),
        );
    }

    public function store(PedidoRequest $request): JsonResponse
    {
        $this->autorizar($request, 'pedido.create');

        $dados = $request->safe()->except('itens');
        $dados['user_id'] = $request->user()->id;
        $pedido = $this->service->criar($dados, $request->validated()['itens'] ?? []);

        return (new PedidoResource($pedido->load('situacao')))->response()->setStatusCode(201);
    }

    public function update(PedidoRequest $request, int $id): PedidoResource
    {
        $this->autorizar($request, 'pedido.edit');
        $pedido = Pedido::query()->findOrFail($id);

        $dados = $request->safe()->except('itens');
        $itens = $request->validated()['itens'] ?? null;
        $atualizado = $this->service->atualizar($pedido, $dados, $itens);

        return new PedidoResource($atualizado->load('situacao'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'pedido.delete');
        $this->service->excluir(Pedido::query()->findOrFail($id));

        return response()->json(['message' => 'Pedido excluído.']);
    }

    /** PUT /pedidos/{id}/situacao — transição na máquina de estados. */
    public function mudarSituacao(Request $request, int $id): PedidoResource
    {
        $this->autorizar($request, 'pedido.edit');
        $d = $request->validate(['pedidosituacao_id' => 'required|integer|exists:pedidosituacoes,id']);

        $pedido = Pedido::query()->findOrFail($id);
        $atualizado = $this->service->mudarSituacao($pedido, $d['pedidosituacao_id'], $request->user()->id);

        return new PedidoResource($atualizado->load('situacao'));
    }

    /** GET /pedidos/situacoes */
    public function situacoes(Request $request): JsonResponse
    {
        $this->autorizar($request, 'pedido.view');

        return response()->json(['data' => PedidoSituacao::query()->where('ativo', true)->orderBy('ordem')->get()]);
    }

    /** POST /pedidos/situacoes — cria uma coluna (situação) do Kanban. */
    public function criarSituacao(Request $request): JsonResponse
    {
        $this->autorizar($request, 'pedidosituacao.create');
        $dados = $this->validarSituacao($request);

        // Posição no fim do kanban por padrão.
        $dados['ordem'] = $dados['ordem'] ?? ((int) PedidoSituacao::query()->max('ordem') + 1);
        $situacao = PedidoSituacao::query()->create($dados);

        return response()->json(['data' => $situacao], 201);
    }

    /** PUT /pedidos/situacoes/{id} — edita uma coluna do Kanban. */
    public function atualizarSituacao(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'pedidosituacao.edit');
        $situacao = PedidoSituacao::query()->findOrFail($id);
        $situacao->update($this->validarSituacao($request, $id));

        return response()->json(['data' => $situacao->fresh()]);
    }

    /** PUT /pedidos/situacoes/reordenar — persiste a ordem das colunas do Kanban. */
    public function reordenarSituacoes(Request $request): JsonResponse
    {
        $this->autorizar($request, 'pedidosituacao.edit');
        $d = $request->validate([
            'ordem' => ['required', 'array', 'min:1'],
            'ordem.*' => ['integer', 'exists:pedidosituacoes,id'],
        ]);

        DB::transaction(function () use ($d) {
            foreach (array_values($d['ordem']) as $posicao => $id) {
                PedidoSituacao::query()->whereKey($id)->update(['ordem' => $posicao]);
            }
        });

        return response()->json(['message' => 'Ordem atualizada.']);
    }

    /** DELETE /pedidos/situacoes/{id} — remove uma coluna vazia do Kanban. */
    public function excluirSituacao(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'pedidosituacao.delete');
        $situacao = PedidoSituacao::query()->findOrFail($id);

        // Bloqueia exclusão de coluna com pedidos vinculados (evita órfãos; o banco
        // já tem restrictOnDelete, mas damos a mensagem amigável antes).
        $vinculados = Pedido::query()->where('pedidosituacao_id', $situacao->id)->count();
        if ($vinculados > 0) {
            throw ValidationException::withMessages([
                'situacao' => "Não é possível excluir: há {$vinculados} pedido(s) nesta coluna. Mova-os para outra coluna antes.",
            ]);
        }

        $situacao->delete();

        return response()->json(['message' => 'Coluna excluída.']);
    }

    /**
     * Validação compartilhada de situação. `cor` é hex #RRGGBB opcional; `efeito`
     * governa a máquina de estados (PENDENTE/CONCLUIDO/CANCELADO). `descricao`
     * é única por grupo (ignora o próprio registro na edição).
     *
     * @return array<string, mixed>
     */
    private function validarSituacao(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'descricao' => [
                'required', 'string', 'max:255',
                Rule::unique('pedidosituacoes', 'descricao')
                    ->where(fn ($q) => $q->where('grupo_id', $request->user()->grupo_id))
                    ->ignore($ignorarId),
            ],
            'efeito' => ['required', Rule::enum(EfeitoPedido::class)],
            'cor' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['boolean'],
        ]);
    }

    /** GET /pedidos/kanban — colunas por situação com totais. */
    public function kanban(Request $request): JsonResponse
    {
        $this->autorizar($request, 'pedido.view');

        $situacoes = PedidoSituacao::query()->where('ativo', true)->orderBy('ordem')->get();

        $colunas = $situacoes->map(function (PedidoSituacao $s) {
            $pedidos = Pedido::query()->with('cliente:id,nome')
                ->where('pedidosituacao_id', $s->id)
                ->orderByDesc('datahora')->limit(50)->get();

            return [
                'situacao_id' => $s->id,
                'descricao' => $s->descricao,
                'efeito' => $s->efeito->value,
                'cor' => $s->cor,
                'total' => $pedidos->count(),
                'valor' => round((float) $pedidos->sum('valor_venda'), 2),
                'pedidos' => $pedidos->map(fn (Pedido $p) => [
                    'id' => $p->id,
                    'valor_venda' => (float) $p->valor_venda,
                    'datahora' => $p->datahora?->toIso8601String(),
                    'cliente' => $p->cliente?->nome,
                ]),
            ];
        });

        return response()->json(['data' => $colunas]);
    }

    /**
     * POST /pedidos/{id}/emitir-nfce — emite o documento fiscal (NFC-e 65 por padrão,
     * ou NF-e 55) a PARTIR de um pedido CONCLUÍDO. Fecha o gap da F03: venda →
     * conclusão → financeiro+estoque (já no PedidoService) → documento fiscal.
     *
     * - Idempotente: se já há nota não-cancelada para o pedido, devolve-a (200).
     * - Exige o pedido concretizado (efeito CONCLUIDO) — não fatura rascunho.
     * - O resultado real (autorizada/rejeitada) depende do gate fiscal (FISCAL_DRIVER):
     *   em CI/homolog o Fake autoriza; em produção, o NFePHP transmite à SEFAZ.
     */
    public function emitirNfce(Request $request, int $id, FiscalService $fiscal): JsonResponse
    {
        $this->autorizar($request, 'fiscal.emitir');
        $d = $request->validate(['modelo' => 'nullable|in:55,65']);
        $modelo = ($d['modelo'] ?? '65') === '55' ? ModeloDocumento::NFE : ModeloDocumento::NFCE;

        $pedido = Pedido::query()->with('situacao')->findOrFail($id);

        // Idempotência: uma nota viva por pedido/modelo.
        $existente = NotaFiscal::query()
            ->where('pedido_id', $pedido->id)
            ->where('modelo', $modelo->value)
            ->where('situacao', '!=', 'CANCELADA')
            ->first();
        if ($existente) {
            return response()->json(['data' => $existente->load('itens'), 'message' => 'Documento já emitido para este pedido.']);
        }

        // Só pedido concretizado fatura.
        if (! $pedido->situacao || ! $pedido->situacao->efeito->concretiza()) {
            throw ValidationException::withMessages(['pedido' => 'Só pedido concluído pode ser faturado.']);
        }

        $nota = $fiscal->emitirDoPedido($pedido, $modelo);

        return response()->json(['data' => $nota->load('itens')], 201);
    }
}
