<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Estoque\EstoqueService;
use App\Http\Controllers\Controller;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueSaldo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estoque — N3. Saldos (leitura) e operações (entrada/saída/transferência/acerto/
 * fechamento). Toda mutação delega ao EstoqueService (saldo auditável).
 */
class EstoqueController extends Controller
{
    public function __construct(private EstoqueService $service)
    {
    }

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

    /** GET /estoque/historico?setor_id=&produto_id= */
    public function historico(Request $request): JsonResponse
    {
        $this->autorizar($request, 'estoque.view');

        $rows = EstoqueHistorico::query()
            ->when($request->query('setor_id'), fn ($q, $s) => $q->where('setor_id', $s))
            ->when($request->query('produto_id'), fn ($q, $p) => $q->where('produto_id', $p))
            ->latest()->limit(200)->get();

        return response()->json(['data' => $rows]);
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

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
