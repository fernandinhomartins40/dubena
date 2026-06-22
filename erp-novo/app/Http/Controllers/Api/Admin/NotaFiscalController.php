<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\ModeloDocumento;
use App\Http\Controllers\Controller;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Pedido\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notas fiscais (NF-e/NFC-e/CF-e) — N9. Emissão a partir do pedido, cancelamento.
 * A SEFAZ é gate: em CI/homolog o FakeSefazDriver autoriza; em produção, driver real.
 */
class NotaFiscalController extends Controller
{
    public function __construct(private FiscalService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');

        $rows = NotaFiscal::query()->with('cliente:id,nome')
            ->when($request->query('situacao'), fn ($b, $s) => $b->where('situacao', $s))
            ->when($request->query('modelo'), fn ($b, $m) => $b->where('modelo', $m))
            ->orderByDesc('id')->limit(200)->get();

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');

        return response()->json(['data' => NotaFiscal::query()->with('itens')->findOrFail($id)]);
    }

    /** POST /notas/emitir — emite a partir de um pedido. */
    public function emitir(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.emitir');
        $d = $request->validate([
            'pedido_id' => 'required|integer|exists:pedidos,id',
            'modelo' => 'required|in:55,65,59',
        ]);

        $pedido = Pedido::query()->findOrFail($d['pedido_id']);
        $nota = $this->service->emitirDoPedido($pedido, ModeloDocumento::from($d['modelo']));

        return response()->json(['data' => $nota->load('itens')], 201);
    }

    /** POST /notas/{id}/cancelar */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.emitir');
        $d = $request->validate(['justificativa' => 'required|string|min:15|max:255']);

        $nota = $this->service->cancelar(NotaFiscal::query()->findOrFail($id), $d['justificativa']);

        return response()->json(['data' => $nota]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
