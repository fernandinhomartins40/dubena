<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Acesso\CamposPermitidos;
use App\Domain\Fiscal\NfEntradaService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Estoque\Setor;
use App\Models\Fiscal\NfRecebida;
use App\Models\Fiscal\NfRecebidaItem;
use App\Rules\ExisteNoTenant;
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
    use AutorizaPorPermissao;

    public function __construct(
        private NfEntradaService $service,
        private CamposPermitidos $campos,
    ) {}

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
            'data' => collect($notas->items())
                ->map(fn (NfRecebida $nota) => $this->apresentar($request, $nota))
                ->all(),
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

        return response()->json(['data' => $this->apresentar($request, $nota)]);
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

        return response()->json(['data' => $this->apresentar($request, $nota)], 201);
    }

    /**
     * POST /fiscal/nf-entrada/{id}/processar — dá entrada no estoque e gera o
     * financeiro a pagar (idempotente). Exige o setor de destino.
     */
    public function processar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'fiscal.emitir');
        abort_unless(
            $this->campos->pode($request->user(), 'produto', 'custo', 'edit'),
            403,
            'Sem permissão para alterar custo de produto.',
        );
        $d = $request->validate([
            'setor_id' => ['required', 'integer', new ExisteNoTenant(Setor::class)],
        ]);

        // findOrFail é tenant-scoped: só processa NF da empresa ativa.
        $nota = NfRecebida::query()->with('itens')->findOrFail($id);
        $nota = $this->service->processar($nota, (int) $d['setor_id']);
        $nota->loadMissing('itens');

        return response()->json(['data' => $this->apresentar($request, $nota)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function apresentar(Request $request, NfRecebida $nota): array
    {
        $dados = $nota->toArray();

        if (! $nota->relationLoaded('itens')) {
            return $dados;
        }

        $mostrarCusto = $this->campos->pode($request->user(), 'produto', 'custo', 'view');
        $dados['itens'] = $nota->itens
            ->map(function (NfRecebidaItem $item) use ($mostrarCusto): array {
                $dadosItem = $item->toArray();
                if ($mostrarCusto) {
                    $dadosItem['valor_unitario'] = $item->getAttribute('valor_unitario') === null
                        ? null
                        : (float) $item->getAttribute('valor_unitario');
                }

                return $dadosItem;
            })
            ->values()
            ->all();

        return $dados;
    }
}
