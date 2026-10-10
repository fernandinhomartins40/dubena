<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Acesso\CamposPermitidos;
use App\Domain\Auditoria\RegistroAcao;
use App\Domain\Estoque\EstoqueService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Concerns\PaginaListagem;
use App\Http\Controllers\Controller;
use App\Models\Estoque\EstoqueFechamento;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueInventario;
use App\Models\Estoque\EstoqueRequisicao;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

/**
 * Estoque — N3. Saldos (leitura) e operações (entrada/saída/transferência/acerto/
 * fechamento). Toda mutação delega ao EstoqueService (saldo auditável).
 */
class EstoqueController extends Controller
{
    use AutorizaPorPermissao;
    use PaginaListagem;

    public function __construct(
        private EstoqueService $service,
        private TenantContext $tenant,
        private CamposPermitidos $campos,
    ) {}

    /** GET /estoque/saldos?setor_id=&q= */
    public function saldos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        $mostrarCusto = $this->campos->pode($request->user(), 'produto', 'custo', 'view');
        $rows = EstoqueSaldo::query()
            ->with(['setor:id,descricao', 'produto:id,descricao'])
            ->when($request->query('setor_id'), fn ($q, $s) => $q->where('setor_id', $s))
            ->when(trim((string) $request->query('q', '')), fn ($q, $b) => $q->whereHas('produto', fn ($w) => $w->where('descricao', 'ilike', '%'.$b.'%')))
            ->get()
            ->map(fn (EstoqueSaldo $s) => array_merge([
                'id' => $s->id,
                'setor_id' => $s->setor_id,
                'produto_id' => $s->produto_id,
                'quantidade' => (float) $s->quantidade,
                'quantidade_minima' => $s->quantidade_minima !== null ? (float) $s->quantidade_minima : null,
                'quantidade_maxima' => $s->quantidade_maxima !== null ? (float) $s->quantidade_maxima : null,
                'setor' => $s->setor?->descricao,
                'produto' => $s->produto?->descricao,
            ], $mostrarCusto ? ['custo_medio' => (float) $s->custo_medio] : []));

        return response()->json(['data' => $rows]);
    }

    /** GET /estoque/fechamentos — lista os fechamentos (dados já existem). */
    public function fechamentos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        // Setor e produto carregados: sem eles a lista só teria ids para mostrar.
        $rows = EstoqueFechamento::query()
            ->with(['setor:id,descricao', 'produto:id,descricao'])
            ->when($request->query('setor_id'), fn ($q, $s) => $q->where('setor_id', $s))
            ->orderByDesc('data_fechamento')->limit(200)->get();

        return response()->json(['data' => $rows]);
    }

    /** GET /estoque/transferencias — histórico de transferências entre setores. */
    public function transferencias(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        // Produto e setor carregados: a tela lista movimentos e, sem eles, só
        // teria ids para mostrar.
        $rows = EstoqueHistorico::query()
            ->with(['produto:id,descricao', 'setor:id,descricao'])
            ->where('origem', 'transferencia')
            ->latest()->limit(200)->get()
            ->map(fn (EstoqueHistorico $mov) => $this->serializarMovimento($request, $mov));

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /produtos/{id}/estoque — saldo do produto por setor.
     * Shape exigido pela SPA (ProdutoFormPage): { setores: [{setor,quantidade,minima,maxima}] }.
     */
    public function porProduto(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        // F2-08: o produto tem de ser DESTE tenant. Sem isto a rota devolvia 200
        // para id alheio — os saldos não vazavam (a RLS os protege), mas o 200
        // confirmava que aquele id existe em algum lugar, que é metade do que um
        // atacante quer ao trocar números na URL.
        //
        // 404 e não 403: "existe, mas você não pode" já é a informação.
        abort_unless(Produto::query()->whereKey($id)->exists(), 404);

        $setores = EstoqueSaldo::query()
            ->with(['setor:id,descricao'])
            ->where('produto_id', $id)
            ->get()
            ->map(fn (EstoqueSaldo $s) => [
                'setor' => $s->setor?->descricao,
                'quantidade' => (float) $s->quantidade,
                'minima' => $s->quantidade_minima !== null ? (float) $s->quantidade_minima : 0.0,
                'maxima' => $s->quantidade_maxima !== null ? (float) $s->quantidade_maxima : 0.0,
            ])->values();

        return response()->json(['data' => ['setores' => $setores]]);
    }

    /**
     * F3-06 — entrada MANUAL nao entra em local de custodia.
     *
     * O que esta em poder de uma pessoa ou num veiculo chegou la por um
     * movimento (carga, transferencia). Lancar entrada direto ali cria
     * mercadoria do nada num lugar que deveria ter RECEBIDO de algum outro — e
     * o erro nao aparece na hora, aparece como saldo que nao bate no
     * inventario, quando ninguem mais liga uma coisa a outra.
     *
     * A restricao e da porta HTTP, e nao do `EstoqueService`: o servico e usado
     * pela transferencia e pela carga do franqueado, que sao justamente os
     * caminhos legitimos de colocar mercadoria nesses locais.
     */
    private function recusarLancamentoEmCustodia(int $setorId): void
    {
        $setor = Setor::query()->find($setorId);

        if ($setor !== null && ! $setor->tipo->aceitaEntradaDireta()) {
            throw ValidationException::withMessages([
                'setor_id' => 'Nao se lanca entrada direta em "'.$setor->tipo->rotulo()
                    .'". Use transferencia a partir de um deposito.',
            ]);
        }
    }

    /** GET /estoque/historico?setor_id=&produto_id= */
    public function historico(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        $query = EstoqueHistorico::query()
            ->when($request->query('setor_id'), fn ($q, $s) => $q->where('setor_id', $s))
            ->when($request->query('produto_id'), fn ($q, $p) => $q->where('produto_id', $p))
            ->when($request->query('tipo'), fn ($q, $t) => $q->where('tipo', $t))
            ->latest();

        $this->filtrarPeriodo($request, $query, 'created_at');

        return $this->paginar(
            $request,
            $query,
            fn (EstoqueHistorico $mov) => $this->serializarMovimento($request, $mov),
        );
    }

    public function entrada(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');

        if ($request->exists('custo_unitario')
            && ! $this->campos->pode($request->user(), 'produto', 'custo', 'edit')) {
            abort(403, 'Sem permissão para alterar custo do produto.');
        }

        $d = $this->validarMov($request, comCusto: true);
        $this->recusarLancamentoEmCustodia((int) $d['setor_id']);

        $mov = $this->service->entrada($d['setor_id'], $d['produto_id'], $d['quantidade'], $d['custo_unitario'] ?? null, 'manual', null, $request->user()->id, $this->tenant->requireEmpresaId(), $this->chaveManual($request));
        $this->registrarMotivo($mov, $d['motivo'] ?? null);

        return response()->json(['data' => $this->serializarMovimento($request, $mov)], 201);
    }

    public function saida(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $this->validarMov($request);

        $mov = $this->service->saida($d['setor_id'], $d['produto_id'], $d['quantidade'], 'manual', null, $request->user()->id, $this->tenant->requireEmpresaId(), $this->chaveManual($request));
        $this->registrarMotivo($mov, $d['motivo'] ?? null);

        return response()->json(['data' => $this->serializarMovimento($request, $mov)], 201);
    }

    /**
     * F4-01: chave do lançamento manual, gerada pela tela quando o formulário
     * abre. Prefixada para não colidir com a de outra origem que use o mesmo
     * uuid (a unicidade é por empresa, não por origem).
     */
    private function chaveManual(Request $request): ?string
    {
        $chave = $this->chaveIdempotencia($request);

        return $chave !== null ? "manual:{$chave}" : null;
    }

    private function chaveIdempotencia(Request $request): ?string
    {
        $chave = $request->header('Idempotency-Key');
        if ($chave !== null && ! preg_match('/^[A-Za-z0-9-]{8,64}$/', $chave)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'Chave de idempotência inválida.']);
        }

        return $chave;
    }

    /**
     * O porquê do lançamento manual vai para a trilha de auditoria: o ledger
     * não tem coluna de observação, e a tela antiga pedia o motivo e o
     * descartava. Só na primeira gravação — um reenvio com a mesma chave
     * devolve o movimento já gravado e não deve somar outra linha na trilha.
     */
    private function registrarMotivo(EstoqueHistorico $mov, ?string $motivo): void
    {
        if ($motivo !== null && $motivo !== '' && $mov->wasRecentlyCreated) {
            app(RegistroAcao::class)->registrar($mov, 'lancamento_manual', $motivo);
        }
    }

    public function transferir(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        // Dois formatos: um produto (`produto_id` + `quantidade`) ou uma carga
        // com vários (`itens[]`), que é o que a tela monta. A tela mandava
        // `itens[]` com nomes que este endpoint nunca aceitou — toda
        // transferência feita por ela tomava 422.
        $d = $request->validate([
            'setor_origem_id' => ['required', 'integer', $this->existsDaEmpresa('setores')],
            'setor_destino_id' => ['required', 'integer', $this->existsDaEmpresa('setores')],
            'produto_id' => ['required_without:itens', 'integer', $this->existsDaEmpresa('produtos')],
            'quantidade' => 'required_without:itens|numeric|gt:0',
            'itens' => 'sometimes|array|min:1|max:200',
            'itens.*.produto_id' => ['required', 'integer', $this->existsDaEmpresa('produtos')],
            'itens.*.quantidade' => 'required|numeric|gt:0',
        ]);

        // F4-01: o cliente gera a chave quando ABRE o formulário. Se a rede cair
        // depois que o servidor gravou, o reenvio traz a mesma chave e devolve o
        // que já foi feito, em vez de mover a mercadoria de novo.
        $chave = $this->chaveIdempotencia($request);

        $itens = $d['itens'] ?? [['produto_id' => $d['produto_id'], 'quantidade' => $d['quantidade']]];
        $empresaId = $this->tenant->requireEmpresaId();

        // Atômica: uma carga transferida pela metade deixaria parte da
        // mercadoria nos dois lugares ao mesmo tempo (ou em nenhum).
        $resultados = DB::transaction(fn () => array_map(
            fn (array $item, int $i) => $this->service->transferir(
                $d['setor_origem_id'], $d['setor_destino_id'], (int) $item['produto_id'], (float) $item['quantidade'],
                $request->user()->id, $empresaId,
                $chave !== null ? "transferencia:{$chave}:{$i}" : null,
            ),
            $itens, array_keys($itens),
        ));

        $serializar = fn (array $res) => [
            'saida' => $this->serializarMovimento($request, $res['saida']),
            'entrada' => $this->serializarMovimento($request, $res['entrada']),
        ];

        return response()->json([
            'data' => isset($d['itens']) ? array_map($serializar, $resultados) : $serializar($resultados[0]),
        ], 201);
    }

    public function acerto(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_id' => ['required', 'integer', $this->existsDaEmpresa('setores')],
            'produto_id' => ['required', 'integer', $this->existsDaEmpresa('produtos')],
            'quantidade_contada' => 'required|numeric|gte:0',
        ]);

        $mov = $this->service->acertar($d['setor_id'], $d['produto_id'], $d['quantidade_contada'], $request->user()->id, $this->tenant->requireEmpresaId());

        return response()->json([
            'data' => $mov ? $this->serializarMovimento($request, $mov) : null,
            'message' => $mov ? 'Acerto aplicado.' : 'Sem diferença.',
        ], 201);
    }

    public function fechar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_id' => ['required', 'integer', $this->existsDaEmpresa('setores')],
            'produto_id' => ['required', 'integer', $this->existsDaEmpresa('produtos')],
            'data_fechamento' => 'required|date',
        ]);

        $fech = $this->service->fechar($d['setor_id'], $d['produto_id'], $d['data_fechamento'], $this->tenant->requireEmpresaId());

        return response()->json(['data' => $fech], 201);
    }

    /**
     * POST /estoque/fechamentos/{id}/reabrir — destrava o período, com motivo.
     *
     * O motivo é obrigatório e vai para a trilha: reabrir um fechamento é o que
     * permite alterar um saldo que alguém já deu por conferido.
     */
    public function reabrirFechamento(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate(['motivo' => 'required|string|min:3|max:255']);

        $fech = EstoqueFechamento::query()->findOrFail($id);
        $fech = $this->service->reabrirFechamento($fech, $this->tenant->requireEmpresaId());
        app(RegistroAcao::class)->registrar($fech, 'reabriu_fechamento', $d['motivo']);

        return response()->json(['data' => $fech, 'message' => 'Fechamento reaberto.']);
    }

    /** @return array<string, mixed> */
    private function validarMov(Request $request, bool $comCusto = false): array
    {
        $regras = [
            'setor_id' => ['required', 'integer', $this->existsDaEmpresa('setores')],
            'produto_id' => ['required', 'integer', $this->existsDaEmpresa('produtos')],
            'quantidade' => 'required|numeric|gt:0',
            'motivo' => 'nullable|string|max:255',
        ];
        if ($comCusto) {
            $regras['custo_unitario'] = 'nullable|numeric|gte:0';
        }

        return $request->validate($regras);
    }

    // ── Requisições (C11) ──
    public function requisicoesIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        return response()->json(['data' => EstoqueRequisicao::query()
            ->with(['produto:id,descricao', 'setorOrigem:id,descricao', 'setorDestino:id,descricao'])
            ->latest()->limit(200)->get()]);
    }

    /**
     * POST /estoque/requisicoes/{id}/atender — atende uma requisição pendente.
     *
     * Sem esta porta, a requisição criada sem `atender` ficava "pendente" para
     * sempre: o serviço sabia atender, mas só no instante da criação.
     */
    public function requisicaoAtender(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_origem_id' => ['nullable', 'integer', $this->existsDaEmpresa('setores')],
        ]);

        $req = EstoqueRequisicao::query()->findOrFail($id);
        // A origem pode ter ficado em aberto no pedido ("preciso de 10 no
        // balcão") e só ser decidida por quem atende.
        if (! empty($d['setor_origem_id']) && $req->situacao === 'pendente') {
            $req->update(['setor_origem_id' => $d['setor_origem_id']]);
        }

        $req = $this->service->atenderRequisicao($req, $request->user()->id, $this->tenant->requireEmpresaId());

        return response()->json(['data' => $req, 'message' => 'Requisição atendida.']);
    }

    public function requisicaoCriar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_origem_id' => ['nullable', 'integer', $this->existsDaEmpresa('setores')],
            'setor_destino_id' => ['required', 'integer', $this->existsDaEmpresa('setores')],
            'produto_id' => ['required', 'integer', $this->existsDaEmpresa('produtos')],
            'quantidade' => 'required|numeric|gt:0',
            'observacao' => 'nullable|string|max:255',
            'atender' => 'nullable|boolean',
        ]);

        $req = EstoqueRequisicao::create(array_merge(
            collect($d)->except('atender')->all(),
            ['empresa_id' => $this->tenant->requireEmpresaId(), 'user_id' => $request->user()->id, 'situacao' => 'pendente'],
        ));

        // Atende na hora, se pedido e houver origem (faz a transferência).
        if (! empty($d['atender']) && $req->setor_origem_id) {
            $req = $this->service->atenderRequisicao($req, $request->user()->id, $this->tenant->requireEmpresaId());
        }

        return response()->json(['data' => $req], 201);
    }

    // ── Inventário / estoque físico (C11) ──
    public function inventariosIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        return response()->json(['data' => EstoqueInventario::query()
            ->with(['itens.produto:id,descricao', 'setor:id,descricao'])
            ->latest()->limit(100)->get()]);
    }

    public function inventarioCriar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_id' => ['required', 'integer', $this->existsDaEmpresa('setores')],
            'data' => 'nullable|date',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => ['required', 'integer', $this->existsDaEmpresa('produtos')],
            'itens.*.quantidade_contada' => 'required|numeric|gte:0',
        ]);

        $inv = EstoqueInventario::create([
            'empresa_id' => $this->tenant->requireEmpresaId(),
            'setor_id' => $d['setor_id'],
            'data' => $d['data'] ?? now()->toDateString(),
            'situacao' => 'aberto',
            // F4-03: quem informou a contagem. E diferente de quem APROVA o
            // ajuste na efetivacao — e a separacao existe justamente para
            // deixar visivel quando as duas sao a mesma pessoa.
            'contado_por' => $request->user()->id,
        ]);
        foreach ($d['itens'] as $i) {
            $inv->itens()->create(['produto_id' => $i['produto_id'], 'quantidade_contada' => $i['quantidade_contada']]);
        }

        return response()->json(['data' => $inv->load('itens')], 201);
    }

    public function inventarioEfetivar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $inv = EstoqueInventario::query()->with('itens')->findOrFail($id);
        $inv = $this->service->efetivarInventario($inv, $request->user()->id, $this->tenant->requireEmpresaId());

        return response()->json(['data' => $inv, 'message' => 'Inventário efetivado (saldos ajustados).']);
    }

    /** POST /estoque/fechamentos/abrir — registra o fechamento de um setor×produto. */
    public function abrirFechamento(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_id' => ['required', 'integer', $this->existsDaEmpresa('setores')],
            'produto_id' => ['required', 'integer', $this->existsDaEmpresa('produtos')],
            'data_fechamento' => 'nullable|date',
        ]);

        $fech = $this->service->fechar($d['setor_id'], $d['produto_id'], $d['data_fechamento'] ?? now()->toDateString(), $this->tenant->requireEmpresaId());

        return response()->json(['data' => $fech], 201);
    }

    private function existsDaEmpresa(string $tabela): Exists
    {
        $empresaId = $this->tenant->requireEmpresaId();

        return Rule::exists($tabela, 'id')->where(fn ($query) => $query->where('empresa_id', $empresaId));
    }

    /** @return array<string, mixed> */
    private function serializarMovimento(Request $request, EstoqueHistorico $mov): array
    {
        $dados = $mov->toArray();

        if ($this->campos->pode($request->user(), 'produto', 'custo', 'view')) {
            $custo = $mov->getAttribute('custo_unitario');
            $dados['custo_unitario'] = $custo !== null ? (float) $custo : null;
        }

        return $dados;
    }
}
