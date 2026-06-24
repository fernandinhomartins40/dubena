<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Fiscal\NfEntradaService;
use App\Http\Controllers\Controller;
use App\Models\Fiscal\NfRecebida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NF de entrada (recebida) — F00.6.
 *
 * A auditoria apontou que `NfEntradaService` (importar XML do fornecedor → estoque
 * de entrada + financeiro a pagar) estava COMPLETO porém SEM controller/rota: uma
 * capacidade morta no HTTP. Este controller expõe o service, escopado por tenant
 * (NfRecebida usa BelongsToTenant; o XML é importado com a empresa/grupo ativos).
 */
class NfEntradaController extends Controller
{
    public function __construct(private NfEntradaService $service) {}

    /** GET /fiscal/nf-entrada — lista as NFs recebidas da empresa ativa. */
    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');

        $notas = NfRecebida::query()
            ->withCount('itens')
            ->when($request->query('situacao'), fn ($b, $s) => $b->where('situacao', $s))
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $notas->items(),
            'meta' => [
                'current_page' => $notas->currentPage(),
                'last_page' => $notas->lastPage(),
                'per_page' => $notas->perPage(),
                'total' => $notas->total(),
            ],
        ]);
    }

    /** GET /fiscal/nf-entrada/{id} — detalhe + itens. */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.view');
        $nota = NfRecebida::query()->with('itens')->findOrFail($id);

        return response()->json(['data' => $nota]);
    }

    /**
     * POST /fiscal/nf-entrada/importar — importa o XML do fornecedor.
     * Aceita o XML no campo `xml` (string) ou upload `arquivo`.
     */
    public function importar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'fiscal.emitir');
        $request->validate([
            'xml' => 'nullable|string',
            'arquivo' => 'nullable|file|mimetypes:text/xml,application/xml',
        ]);

        $xml = (string) $request->input('xml', '');
        if ($xml === '' && $request->hasFile('arquivo')) {
            $xml = (string) file_get_contents($request->file('arquivo')->getRealPath());
        }
        abort_if($xml === '', 422, 'Envie o XML da NF (campo xml ou arquivo).');

        $nota = $this->service->importarXml(
            (int) $request->user()->empresa_id,
            (int) $request->user()->grupo_id,
            $xml,
        );

        return response()->json(['data' => $nota], 201);
    }

    /**
     * POST /fiscal/nf-entrada/{id}/processar — dá entrada no estoque e gera o
     * financeiro a pagar (idempotente). Exige o setor de destino.
     */
    public function processar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.emitir');
        $d = $request->validate([
            'setor_id' => 'required|integer|exists:setores,id',
        ]);

        // findOrFail é tenant-scoped: só processa NF da empresa ativa.
        $nota = NfRecebida::query()->with('itens')->findOrFail($id);
        $nota = $this->service->processar($nota, (int) $d['setor_id']);

        return response()->json(['data' => $nota]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
