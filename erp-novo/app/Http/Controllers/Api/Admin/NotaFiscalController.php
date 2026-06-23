<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\IbptService;
use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Fiscal\SpedContribuicoesService;
use App\Domain\Fiscal\SpedFiscalService;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
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
    public function __construct(private FiscalService $service) {}

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

    /**
     * POST /fiscal/nfe/{id}/transmitir — transmite à SEFAZ uma nota já montada
     * (RASCUNHO). Alias da SPA; reusa FiscalService::emitir (gate fake em CI).
     */
    public function transmitir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.emitir');

        $nota = $this->service->emitir(NotaFiscal::query()->findOrFail($id));

        return response()->json(['data' => $nota->load('itens')]);
    }

    /** POST /notas/{id}/cancelar */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.emitir');
        $d = $request->validate(['justificativa' => 'required|string|min:15|max:255']);

        $nota = $this->service->cancelar(NotaFiscal::query()->findOrFail($id), $d['justificativa']);

        return response()->json(['data' => $nota]);
    }

    /** GET /fiscal/sped?inicio=&fim= — gera o arquivo da EFD ICMS/IPI do período. */
    public function sped(Request $request, SpedFiscalService $sped): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');
        $d = $request->validate([
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->empresa_id);
        $conteudo = $sped->gerar($empresa, $d['inicio'], $d['fim']);

        return response()->json(['data' => [
            'arquivo' => $conteudo,
            'linhas' => substr_count($conteudo, "\n"),
            'periodo' => ['inicio' => $d['inicio'], 'fim' => $d['fim']],
        ]]);
    }

    /** GET /fiscal/sped-contribuicoes?inicio=&fim= — gera a EFD-Contribuições (PIS/COFINS). */
    public function spedContribuicoes(Request $request, SpedContribuicoesService $sped): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');
        $d = $request->validate([
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->empresa_id);
        $conteudo = $sped->gerar($empresa, $d['inicio'], $d['fim']);

        return response()->json(['data' => [
            'arquivo' => $conteudo,
            'linhas' => substr_count($conteudo, "\n"),
            'periodo' => ['inicio' => $d['inicio'], 'fim' => $d['fim']],
        ]]);
    }

    /** GET /fiscal/ibpt?ncm=&valor=&origem= — carga tributária aproximada (Lei 12.741). */
    public function ibpt(Request $request, IbptService $ibpt): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');
        $d = $request->validate([
            'ncm' => 'required|string|max:10',
            'valor' => 'required|numeric|gte:0',
            'origem' => 'nullable|in:nacional,importado',
        ]);

        return response()->json(['data' => $ibpt->calcular($d['ncm'], (float) $d['valor'], $d['origem'] ?? 'nacional')]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
