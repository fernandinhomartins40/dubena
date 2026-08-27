<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Acesso\CamposPermitidos;
use App\Domain\Fiscal\DanfePdfService;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\IbptService;
use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Fiscal\SpedContribuicoesService;
use App\Domain\Fiscal\SpedFiscalService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Concerns\PaginaListagem;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Pedido\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Notas fiscais (NF-e/NFC-e/CF-e) — N9. Emissão a partir do pedido, cancelamento.
 * A SEFAZ é gate: em CI/homolog o FakeSefazDriver autoriza; em produção, driver real.
 */
class NotaFiscalController extends Controller
{
    use AutorizaPorPermissao;
    use PaginaListagem;

    public function __construct(
        private FiscalService $service,
        private CamposPermitidos $campos,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');

        $busca = trim((string) $request->query('q', ''));

        $query = NotaFiscal::query()->with('cliente:id,nome')
            ->when($request->query('situacao'), fn ($b, $s) => $b->where('situacao', $s))
            ->when($request->query('modelo'), fn ($b, $m) => $b->where('modelo', $m))
            // A busca da tela é por número ou chave de acesso. `LOWER(..) LIKE`
            // em vez de `ilike`: o ilike é exclusivo do Postgres e a suíte roda
            // em sqlite — com ilike a busca passava no deploy e quebrava no teste.
            ->when($busca !== '', fn ($b) => $b->where(function ($w) use ($busca) {
                $w->whereRaw('LOWER(chave) LIKE ?', ['%'.mb_strtolower($busca).'%']);
                if (ctype_digit($busca)) {
                    $w->orWhere('numero', (int) $busca);
                }
            }))
            ->orderByDesc('id');

        $this->filtrarPeriodo($request, $query, 'emitida_em');

        return $this->paginar($request, $query);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');

        return response()->json(['data' => NotaFiscal::query()->with('itens')->findOrFail($id)]);
    }

    /**
     * GET /notas/{id}/danfe — o impresso que acompanha a mercadoria.
     *
     * Sem DANFE a carga não circula legalmente. A permissão é `fiscal.view`
     * porque imprimir não emite nem altera nada: quem consulta a nota precisa
     * poder imprimir o papel dela.
     */
    public function danfe(Request $request, int $id, DanfePdfService $danfe): Response
    {
        $this->autorizar($request, 'fiscal.view');

        $nota = NotaFiscal::query()->findOrFail($id);

        return response($danfe->gerar($nota), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="danfe-'.$nota->numero.'.pdf"',
        ]);
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

    /** POST /fiscal/inutilizacoes — inutiliza uma faixa de numeração (modelo/série). */
    public function inutilizar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.emitir');
        $d = $request->validate([
            'modelo' => 'required|integer|in:55,65',
            'serie' => 'required|integer|min:0',
            'numero_inicial' => 'required|integer|min:1',
            'numero_final' => 'required|integer|min:1|gte:numero_inicial',
            'justificativa' => 'required|string|min:15|max:255',
        ]);

        $inut = $this->service->inutilizar(
            (int) $request->user()->empresa_id,
            (int) $d['modelo'], (int) $d['serie'],
            (int) $d['numero_inicial'], (int) $d['numero_final'],
            $d['justificativa'],
        );

        return response()->json(['data' => $inut], 201);
    }

    /** POST /notas/{id}/carta-correcao — registra uma CCE sobre a nota. */
    public function cartaCorrecao(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.emitir');
        $d = $request->validate(['correcao' => 'required|string|min:15|max:1000']);

        $cce = $this->service->cartaCorrecao(NotaFiscal::query()->findOrFail($id), $d['correcao']);

        return response()->json(['data' => $cce], 201);
    }

    /** GET /fiscal/sped?inicio=&fim= — gera o arquivo da EFD ICMS/IPI do período. */
    public function sped(Request $request, SpedFiscalService $sped): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');
        abort_unless(
            $this->campos->pode($request->user(), 'produto', 'custo', 'view'),
            403,
            'Sem permissão para visualizar custo do produto.',
        );
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
}
