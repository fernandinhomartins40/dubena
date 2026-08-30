<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Satelite\SituacaoValeGas;
use App\Domain\Satelite\ValeGasPdfService;
use App\Domain\Satelite\ValeGasService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Concerns\PaginaListagem;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Pedido\Pedido;
use App\Models\Produto\Produto;
use App\Models\Satelite\ValeGas;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Vale-gás (cupom pré-pago) — N8.
 */
class ValeGasController extends Controller
{
    use AutorizaPorPermissao;
    use PaginaListagem;

    public function __construct(private ValeGasService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'valegas.view');
        $q = trim((string) $request->query('q', ''));

        $query = ValeGas::query()
            // Sem o eager load a coluna "Cliente" da tela ficava sempre vazia:
            // a listagem devolve o modelo cru, e a relacao nao vinha junto.
            ->with('cliente:id,nome')
            ->when($q !== '', fn ($b) => $b->where('codigo', 'ilike', '%'.$q.'%'))
            ->when($request->query('situacao'), fn ($b, $s) => $b->where('situacao', $s))
            ->orderByDesc('id');

        return $this->paginar($request, $query);
    }

    public function situacoes(): JsonResponse
    {
        return response()->json(['data' => array_map(fn ($s) => $s->value, SituacaoValeGas::cases())]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'valegas.edit');
        $d = $request->validate([
            'cliente_id' => ['nullable', 'integer', new ExisteNoTenant(Cliente::class)],
            'produto_id' => ['nullable', 'integer', new ExisteNoTenant(Produto::class)],
            'valor' => 'required|numeric|gt:0',
            'validade' => 'nullable|date',
            'codigo' => 'nullable|string|max:40',
        ]);
        $d['empresa_id'] = $request->user()->empresa_id;
        $d['grupo_id'] = $request->user()->grupo_id;

        return response()->json(['data' => $this->service->emitir($d)], 201);
    }

    /**
     * GET /vale-gas/{id}/pdf — o cupom que vai para a mão do cliente.
     *
     * Permissão de leitura: imprimir não altera nada. Quem consulta o vale
     * precisa poder imprimi-lo.
     */
    public function pdf(Request $request, int $id, ValeGasPdfService $pdf): Response
    {
        $this->autorizar($request, 'valegas.view');

        $vale = ValeGas::query()->findOrFail($id);

        return response($pdf->vale($vale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vale-'.$vale->codigo.'.pdf"',
        ]);
    }

    /** GET /vale-gas/{id}/duplicata — a via de cobrança do vale vendido a prazo. */
    public function duplicata(Request $request, int $id, ValeGasPdfService $pdf): Response
    {
        $this->autorizar($request, 'valegas.view');

        $vale = ValeGas::query()->findOrFail($id);

        return response($pdf->duplicata($vale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="duplicata-'.$vale->codigo.'.pdf"',
        ]);
    }

    /** POST /vale-gas/baixar — muda situação por código (usado pela SPA). */
    public function baixar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'valegas.edit');
        $d = $request->validate([
            'codigo' => 'required|string',
            'situacao' => 'required|string',
            'pedido_id' => ['nullable', 'integer', new ExisteNoTenant(Pedido::class)],
        ]);
        $destino = SituacaoValeGas::tryFrom($d['situacao']);
        abort_unless($destino !== null, 422, 'Situação inválida.');

        $vale = ValeGas::query()->where('codigo', $d['codigo'])->firstOrFail();
        $atualizado = $this->service->mudarSituacao($vale, $destino, $d['pedido_id'] ?? null);

        return response()->json(['data' => $atualizado]);
    }
}
