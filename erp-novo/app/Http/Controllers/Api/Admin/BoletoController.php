<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Cobranca\BoletoPdfService;
use App\Domain\Cobranca\BoletoService;
use App\Domain\Cobranca\SituacaoBoleto;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Concerns\PaginaListagem;
use App\Http\Controllers\Controller;
use App\Models\Cobranca\Boleto;
use App\Models\Cobranca\RemessaCnab;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Boletos (CNAB) — N7 (GATE bancário).
 */
class BoletoController extends Controller
{
    use AutorizaPorPermissao;
    use PaginaListagem;

    public function __construct(private BoletoService $service, private TenantContext $tenant) {}

    /**
     * GET /boletos/{id}/pdf — imprime o boleto (T4.6).
     *
     * Era a lacuna BLOQUEANTE da familia de saidas impressas: o CNAB completo
     * gerava o titulo, mandava ao banco e recebia o retorno, mas o boleto nunca
     * virava papel — e sem o papel a cobranca nao chega ao cliente.
     *
     * `financeiro.view` e nao `.edit`: imprimir e leitura. Mas E dado financeiro
     * de cliente, entao passa por autorizacao como todo o resto.
     */
    public function pdf(Request $request, int $id, BoletoPdfService $pdf): \Illuminate\Http\Response
    {
        $this->autorizar($request, 'financeiro.view');

        $boleto = Boleto::withoutTenant()
            ->whereKey($id)
            ->where('empresa_id', $this->tenant->requireEmpresaId())
            ->firstOrFail();

        return response($pdf->gerar($boleto), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="boleto-' . $boleto->id . '.pdf"',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');
        $q = trim((string) $request->query('q', ''));

        $query = Boleto::query()
            ->when($request->query('status'), fn ($b, $s) => $b->where('situacao', $s))
            ->when($q !== '', fn ($b) => $b->where('nosso_numero', 'ilike', '%'.$q.'%'))
            ->orderByDesc('vencimento');

        $this->filtrarPeriodo($request, $query, 'vencimento');

        return $this->paginar($request, $query);
    }

    public function resumo(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');

        return response()->json(['data' => [
            'registrados' => Boleto::query()->where('situacao', SituacaoBoleto::REGISTRADO->value)->count(),
            'liquidados' => Boleto::query()->where('situacao', SituacaoBoleto::LIQUIDADO->value)->count(),
            'valor_aberto' => round((float) Boleto::query()->whereIn('situacao', [SituacaoBoleto::PENDENTE->value, SituacaoBoleto::REGISTRADO->value])->sum('valor'), 2),
        ]]);
    }

    /** POST /boletos — gera boleto para uma parcela. */
    public function gerar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $d = $request->validate(['parcela_id' => 'required|integer|exists:financeiroparcelas,id']);

        $parcela = FinanceiroParcela::withoutTenant()
            ->whereKey($d['parcela_id'])
            ->where('empresa_id', $this->tenant->requireEmpresaId())
            ->with('financeiro')
            ->firstOrFail();
        $boleto = $this->service->gerarParaParcela($parcela);

        return response()->json(['data' => $boleto], 201);
    }

    /** POST /boletos/remessa — gera remessa CNAB dos boletos pendentes. */
    public function remessa(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');

        $boletos = Boleto::withoutTenant()
            ->where('empresa_id', $this->tenant->requireEmpresaId())
            ->where('situacao', SituacaoBoleto::PENDENTE->value)
            ->get();
        $remessa = $this->service->gerarRemessa($boletos, $this->tenant->requireEmpresaId());

        return response()->json(['data' => $remessa], 201);
    }

    /**
     * POST /boletos/retorno — processa arquivo de retorno CNAB. Aceita as `linhas`
     * (array) OU um upload `arquivo` (.ret/.txt), que é quebrado em linhas.
     */
    public function retorno(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $request->validate([
            'linhas' => 'nullable|array',
            'linhas.*' => 'string',
            'arquivo' => 'nullable|file',
        ]);

        $linhas = $request->input('linhas');
        if (! $linhas && $request->hasFile('arquivo')) {
            $conteudo = (string) file_get_contents($request->file('arquivo')->getRealPath());
            $linhas = preg_split('/\r\n|\r|\n/', $conteudo) ?: [];
        }
        abort_if(empty($linhas), 422, 'Envie o retorno (linhas ou arquivo).');

        $n = $this->service->processarRetorno($linhas, $this->tenant->requireEmpresaId());

        return response()->json(['message' => "Retorno processado: {$n} ocorrência(s).", 'processadas' => $n]);
    }

    /** GET /boletos/remessas — lista remessas geradas. */
    public function remessas(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');

        return response()->json(['data' => RemessaCnab::query()->orderByDesc('numero_remessa')->limit(100)->get()]);
    }

    /** GET /boletos/remessas/{id}/arquivo — baixa o .rem (CNAB) da remessa. */
    public function baixarRemessa(Request $request, int $id): StreamedResponse
    {
        $this->autorizar($request, 'financeiro.view');
        $remessa = RemessaCnab::withoutTenant()
            ->whereKey($id)
            ->where('empresa_id', $this->tenant->requireEmpresaId())
            ->firstOrFail();

        abort_unless($remessa->arquivo && Storage::disk('local')->exists($remessa->arquivo), 404, 'Arquivo da remessa não encontrado.');

        return Storage::disk('local')->download($remessa->arquivo, basename($remessa->arquivo));
    }
}
