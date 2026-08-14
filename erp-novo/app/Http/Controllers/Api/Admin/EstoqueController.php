<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Estoque\EstoqueService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Concerns\PaginaListagem;
use App\Http\Controllers\Controller;
use App\Models\Estoque\EstoqueFechamento;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueInventario;
use App\Models\Estoque\EstoqueRequisicao;
use App\Models\Estoque\EstoqueSaldo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estoque — N3. Saldos (leitura) e operações (entrada/saída/transferência/acerto/
 * fechamento). Toda mutação delega ao EstoqueService (saldo auditável).
 */
class EstoqueController extends Controller
{
    use AutorizaPorPermissao;
    use PaginaListagem;

    public function __construct(private EstoqueService $service) {}

    /** GET /estoque/saldos?setor_id=&q= */
    public function saldos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        $rows = EstoqueSaldo::query()
            ->with(['setor:id,descricao', 'produto:id,descricao'])
            ->when($request->query('setor_id'), fn ($q, $s) => $q->where('setor_id', $s))
            ->when(trim((string) $request->query('q', '')), fn ($q, $b) => $q->whereHas('produto', fn ($w) => $w->where('descricao', 'ilike', '%'.$b.'%')))
            ->get()
            ->map(fn (EstoqueSaldo $s) => [
                'id' => $s->id,
                'setor_id' => $s->setor_id,
                'produto_id' => $s->produto_id,
                'quantidade' => (float) $s->quantidade,
                'quantidade_minima' => $s->quantidade_minima !== null ? (float) $s->quantidade_minima : null,
                'quantidade_maxima' => $s->quantidade_maxima !== null ? (float) $s->quantidade_maxima : null,
                'custo_medio' => (float) $s->custo_medio,
                'setor' => $s->setor?->descricao,
                'produto' => $s->produto?->descricao,
            ]);

        return response()->json(['data' => $rows]);
    }

    /** GET /estoque/fechamentos — lista os fechamentos (dados já existem). */
    public function fechamentos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        $rows = EstoqueFechamento::query()
            ->when($request->query('setor_id'), fn ($q, $s) => $q->where('setor_id', $s))
            ->orderByDesc('data_fechamento')->limit(200)->get();

        return response()->json(['data' => $rows]);
    }

    /** GET /estoque/transferencias — histórico de transferências entre setores. */
    public function transferencias(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        $rows = EstoqueHistorico::query()
            ->where('origem', 'transferencia')
            ->latest()->limit(200)->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /produtos/{id}/estoque — saldo do produto por setor.
     * Shape exigido pela SPA (ProdutoFormPage): { setores: [{setor,quantidade,minima,maxima}] }.
     */
    public function porProduto(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

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

        return $this->paginar($request, $query);
    }

    public function entrada(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $this->validarMov($request, comCusto: true);

        $mov = $this->service->entrada($d['setor_id'], $d['produto_id'], $d['quantidade'], $d['custo_unitario'] ?? null, 'manual', null, $request->user()->id);

        return response()->json(['data' => $mov], 201);
    }

    public function saida(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $this->validarMov($request);

        $mov = $this->service->saida($d['setor_id'], $d['produto_id'], $d['quantidade'], 'manual', null, $request->user()->id);

        return response()->json(['data' => $mov], 201);
    }

    public function transferir(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_origem_id' => 'required|integer|exists:setores,id',
            'setor_destino_id' => 'required|integer|exists:setores,id',
            'produto_id' => 'required|integer|exists:produtos,id',
            'quantidade' => 'required|numeric|gt:0',
        ]);

        $res = $this->service->transferir($d['setor_origem_id'], $d['setor_destino_id'], $d['produto_id'], $d['quantidade'], $request->user()->id);

        return response()->json(['data' => $res], 201);
    }

    public function acerto(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_id' => 'required|integer|exists:setores,id',
            'produto_id' => 'required|integer|exists:produtos,id',
            'quantidade_contada' => 'required|numeric|gte:0',
        ]);

        $mov = $this->service->acertar($d['setor_id'], $d['produto_id'], $d['quantidade_contada'], $request->user()->id);

        return response()->json(['data' => $mov, 'message' => $mov ? 'Acerto aplicado.' : 'Sem diferença.'], 201);
    }

    public function fechar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_id' => 'required|integer|exists:setores,id',
            'produto_id' => 'required|integer|exists:produtos,id',
            'data_fechamento' => 'required|date',
        ]);

        $fech = $this->service->fechar($d['setor_id'], $d['produto_id'], $d['data_fechamento']);

        return response()->json(['data' => $fech], 201);
    }

    /** @return array<string, mixed> */
    private function validarMov(Request $request, bool $comCusto = false): array
    {
        $regras = [
            'setor_id' => 'required|integer|exists:setores,id',
            'produto_id' => 'required|integer|exists:produtos,id',
            'quantidade' => 'required|numeric|gt:0',
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

        return response()->json(['data' => EstoqueRequisicao::query()->latest()->limit(200)->get()]);
    }

    public function requisicaoCriar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_origem_id' => 'nullable|integer|exists:setores,id',
            'setor_destino_id' => 'required|integer|exists:setores,id',
            'produto_id' => 'required|integer|exists:produtos,id',
            'quantidade' => 'required|numeric|gt:0',
            'observacao' => 'nullable|string|max:255',
            'atender' => 'nullable|boolean',
        ]);

        $req = EstoqueRequisicao::create(array_merge(
            collect($d)->except('atender')->all(),
            ['user_id' => $request->user()->id, 'situacao' => 'pendente'],
        ));

        // Atende na hora, se pedido e houver origem (faz a transferência).
        if (! empty($d['atender']) && $req->setor_origem_id) {
            $req = $this->service->atenderRequisicao($req, $request->user()->id);
        }

        return response()->json(['data' => $req], 201);
    }

    // ── Inventário / estoque físico (C11) ──
    public function inventariosIndex(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        return response()->json(['data' => EstoqueInventario::query()->with('itens')->latest()->limit(100)->get()]);
    }

    public function inventarioCriar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_id' => 'required|integer|exists:setores,id',
            'data' => 'nullable|date',
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|integer|exists:produtos,id',
            'itens.*.quantidade_contada' => 'required|numeric|gte:0',
        ]);

        $inv = EstoqueInventario::create([
            'setor_id' => $d['setor_id'],
            'data' => $d['data'] ?? now()->toDateString(),
            'situacao' => 'aberto',
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
        $inv = $this->service->efetivarInventario($inv, $request->user()->id);

        return response()->json(['data' => $inv, 'message' => 'Inventário efetivado (saldos ajustados).']);
    }

    /** POST /estoque/fechamentos/abrir — registra o fechamento de um setor×produto. */
    public function abrirFechamento(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.edit');
        $d = $request->validate([
            'setor_id' => 'required|integer|exists:setores,id',
            'produto_id' => 'required|integer|exists:produtos,id',
            'data_fechamento' => 'nullable|date',
        ]);

        $fech = $this->service->fechar($d['setor_id'], $d['produto_id'], $d['data_fechamento'] ?? now()->toDateString());

        return response()->json(['data' => $fech], 201);
    }
}
