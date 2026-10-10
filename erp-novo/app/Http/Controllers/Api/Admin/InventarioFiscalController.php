<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Acesso\CamposPermitidos;
use App\Domain\Auditoria\RegistroAcao;
use App\Domain\Fiscal\InventarioFiscalService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Fiscal\InventarioFiscal;
use App\Models\Produto\Produto;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Inventário fiscal (SPED Fiscal, Bloco H) — o estoque declarado ao fisco.
 *
 * O valor unitário aqui é CUSTO. Por isso toda porta exige, além de
 * `fiscal.*`, a permissão de campo que protege o custo do produto — a mesma
 * combinação do download do SPED, que entrega esses números.
 */
class InventarioFiscalController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(
        private InventarioFiscalService $service,
        private TenantContext $tenant,
        private CamposPermitidos $campos,
    ) {}

    /** GET /fiscal/inventarios */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');
        $this->exigirCusto($request, 'view');

        return response()->json(['data' => InventarioFiscal::query()
            ->with('itens')
            ->orderByDesc('mes_entrega')->orderByDesc('id')
            ->limit(120)->get()]);
    }

    /** GET /fiscal/inventarios/sugestao — saldo e custo de AGORA, para conferir e ajustar. */
    public function sugestao(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');
        $this->exigirCusto($request, 'view');

        return response()->json(['data' => $this->service->sugestao($this->tenant->requireEmpresaId(), comCusto: true)]);
    }

    /** POST /fiscal/inventarios */
    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.edit');
        $this->exigirCusto($request, 'view');

        $d = $request->validate([
            'mes_entrega' => 'required|date',
            'data_inventario' => 'required|date',
            'motivo' => ['nullable', Rule::in(array_keys(InventarioFiscalService::MOTIVOS))],
            'itens' => 'required|array|min:1|max:2000',
            'itens.*.produto_id' => ['required', 'integer', new ExisteNoTenant(Produto::class)],
            // Zero é declaração válida ("não tinha"); negativo não existe em
            // inventário.
            'itens.*.quantidade' => 'required|numeric|gte:0',
            'itens.*.valor_unitario' => 'required|numeric|gte:0',
        ]);

        $inventario = $this->service->criar(
            $this->tenant->requireEmpresaId(),
            ['mes_entrega' => $d['mes_entrega'], 'data_inventario' => $d['data_inventario'], 'motivo' => $d['motivo'] ?? null],
            $d['itens'],
            $request->user()->id,
        );

        return response()->json(['data' => $inventario], 201);
    }

    /**
     * DELETE /fiscal/inventarios/{id}
     *
     * Não há edição: corrigir é excluir e gravar de novo. O motivo é
     * obrigatório porque o inventário pode já ter sido entregue num SPED — e
     * a exclusão muda o que o próximo arquivo gerado para aquele mês vai dizer.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.edit');
        $d = $request->validate(['motivo' => 'required|string|min:3|max:255']);

        $inventario = InventarioFiscal::query()->findOrFail($id);
        // Antes de apagar: depois não há mais alvo para a trilha apontar.
        app(RegistroAcao::class)->registrar($inventario, 'excluido', $d['motivo'], [
            'mes_entrega' => $inventario->mes_entrega->format('m/Y'),
            'data_inventario' => $inventario->data_inventario->format('d/m/Y'),
            'valor_total' => (float) $inventario->valor_total,
        ]);
        $inventario->delete();

        return response()->json(['message' => 'Inventário excluído.']);
    }

    private function exigirCusto(Request $request, string $acao): void
    {
        abort_unless(
            $this->campos->pode($request->user(), 'produto', 'custo', $acao),
            403,
            'Sem permissão para visualizar custo do produto.',
        );
    }
}
