<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Cobranca\BoletoService;
use App\Domain\Cobranca\SituacaoBoleto;
use App\Http\Controllers\Controller;
use App\Models\Cobranca\Boleto;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Boletos (CNAB) — N7 (GATE bancário).
 */
class BoletoController extends Controller
{
    public function __construct(private BoletoService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');
        $q = trim((string) $request->query('q', ''));

        $rows = Boleto::query()
            ->when($request->query('status'), fn ($b, $s) => $b->where('situacao', $s))
            ->when($q !== '', fn ($b) => $b->where('nosso_numero', 'ilike', '%'.$q.'%'))
            ->orderByDesc('vencimento')->limit(200)->get();

        return response()->json(['data' => $rows]);
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

        $parcela = FinanceiroParcela::query()->with('financeiro')->findOrFail($d['parcela_id']);
        $boleto = $this->service->gerarParaParcela($parcela);

        return response()->json(['data' => $boleto], 201);
    }

    /** POST /boletos/remessa — gera remessa CNAB dos boletos pendentes. */
    public function remessa(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');

        $boletos = Boleto::query()->where('situacao', SituacaoBoleto::PENDENTE->value)->get();
        $remessa = $this->service->gerarRemessa($boletos, (int) $request->user()->empresa_id);

        return response()->json(['data' => $remessa], 201);
    }

    /** POST /boletos/retorno — processa arquivo de retorno (linhas CNAB). */
    public function retorno(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $d = $request->validate(['linhas' => 'required|array', 'linhas.*' => 'string']);

        $n = $this->service->processarRetorno($d['linhas']);

        return response()->json(['message' => "Retorno processado: {$n} ocorrência(s).", 'processadas' => $n]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
