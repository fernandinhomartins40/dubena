<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Caixa\CaixaService;
use App\Domain\Shared\PdfService;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Concerns\PaginaListagem;
use App\Http\Controllers\Controller;
use App\Models\Caixa\Conta;
use App\Models\Caixa\ContaMovimento;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Caixa / Conta — N6. Contas, movimentos, abrir/fechar, baixar parcela,
 * transferir, estornar. Toda mutação passa pelo CaixaService (saldo auditável).
 */
class CaixaController extends Controller
{
    use AutorizaPorPermissao;
    use PaginaListagem;

    public function __construct(private CaixaService $service) {}

    /**
     * GET /caixa/movimentos/{id}/recibo — recibo do lancamento em PDF (T4.6).
     *
     * `CaixaController@gerarRecibo` existe no legado; grep `recibo` no novo
     * retornava ZERO. Sem ele o cliente que paga no balcao sai sem comprovante,
     * e o operador nao tem o que anexar ao acerto do dia.
     *
     * Meia pagina de proposito: dois recibos por folha A4. Papel custa, e um
     * recibo ocupando uma folha inteira e desperdicio que o balcao nota todo dia.
     */
    public function recibo(Request $request, int $movimentoId, PdfService $pdf): \Illuminate\Http\Response
    {
        $this->autorizar($request, 'caixa.view');

        $mov = ContaMovimento::query()->findOrFail($movimentoId);
        $conta = Conta::query()->find($mov->conta_id);
        $empresa = $mov->empresa_id !== null ? \App\Models\Empresa::query()->find($mov->empresa_id) : null;

        $valor = 'R$ ' . number_format((float) $mov->valor, 2, ',', '.');
        $tipo = ((string) $mov->pagarreceber) === 'R' ? 'RECEBIMENTO' : 'PAGAMENTO';

        $corpo = $pdf->campos([
            'Documento' => (string) $mov->id,
            'Data' => $mov->datahora ? \Illuminate\Support\Carbon::parse($mov->datahora)->format('d/m/Y H:i') : null,
            'Conta' => (string) ($conta->descricao ?? ''),
            'Historico' => (string) ($mov->descricao ?? ''),
            'Tipo' => $tipo,
        ])
        . '<div class="total">Valor: ' . e($valor) . '</div>'
        . $pdf->assinatura('Recebemos de / Pagamos a');

        return response(
            $pdf->meiaPagina('Recibo de ' . $tipo, $corpo, [
                'empresa' => (string) ($empresa->razao_social ?? ''),
                'cnpj' => (string) ($empresa->cnpj ?? ''),
                'rodape' => 'Documento gerado eletronicamente pelo sistema.',
            ]),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="recibo-' . $mov->id . '.pdf"',
            ],
        );
    }

    /** GET /caixa/contas */
    public function contas(Request $request): JsonResponse
    {
        $this->autorizar($request, 'caixa.view');

        $rows = Conta::query()->where('ativo', true)->orderBy('descricao')->get()
            ->map(fn (Conta $c) => [
                'id' => $c->id,
                'descricao' => $c->descricao,
                'tipo' => $c->tipo,
                'saldoatual' => (float) $c->saldo_atual,
                'fechado' => (int) $c->fechado,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function criarConta(Request $request): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        $d = $request->validate([
            'descricao' => 'required|string|max:255',
            'tipo' => 'nullable|in:CAIXA,BANCO,CARTAO',
            'banco_id' => 'nullable|integer|exists:bancos,id',
            'agencia' => 'nullable|string|max:20',
            'numero' => 'nullable|string|max:30',
            'saldo_inicial' => 'nullable|numeric',
        ]);
        $d['empresa_id'] = $request->user()->empresa_id;
        $d['grupo_id'] = $request->user()->grupo_id;

        return response()->json(['data' => $this->service->criarConta($d)], 201);
    }

    /** GET /caixa/{contaId}/movimentos */
    public function movimentos(Request $request, int $contaId): JsonResponse
    {
        $this->autorizar($request, 'caixa.view');
        Conta::query()->findOrFail($contaId); // valida escopo

        $query = ContaMovimento::query()->where('conta_id', $contaId)->orderByDesc('datahora');

        $this->filtrarPeriodo($request, $query, 'datahora');

        return $this->paginar($request, $query);
    }

    public function abrir(Request $request, int $contaId): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        Conta::query()->findOrFail($contaId);
        $d = $request->validate(['datahoraabertura' => 'nullable|date']);

        $f = $this->service->abrir($contaId, $d['datahoraabertura'] ?? null, $request->user()->id);

        return response()->json(['data' => $f], 201);
    }

    public function fechar(Request $request, int $contaId): JsonResponse
    {
        $this->autorizar($request, 'caixa.fechar'); // verbo sensível (A7)
        Conta::query()->findOrFail($contaId);
        $d = $request->validate(['datahorafechamento' => 'nullable|date']);

        $f = $this->service->fechar($contaId, $d['datahorafechamento'] ?? null);

        return response()->json(['data' => $f]);
    }

    /** POST /caixa/{contaId}/baixar */
    public function baixar(Request $request, int $contaId): JsonResponse
    {
        Conta::query()->findOrFail($contaId);
        $d = $request->validate([
            'parcela_id' => 'required|integer|exists:financeiroparcelas,id',
            'juros' => 'nullable|numeric|gte:0',
            'multa' => 'nullable|numeric|gte:0',
            'desconto' => 'nullable|numeric|gte:0',
        ]);

        // ABAC (A4): baixa é verbo sensível; o limite ABAC aplica sobre o valor da parcela.
        $valor = (float) FinanceiroParcela::query()->whereKey($d['parcela_id'])->value('valor');
        $this->autorizarRecurso($request, 'financeiro.baixar', ['valor' => $valor]);

        $mov = $this->service->baixarParcela($contaId, $d['parcela_id'], (float) ($d['juros'] ?? 0), (float) ($d['multa'] ?? 0), (float) ($d['desconto'] ?? 0), $request->user()->id);

        return response()->json(['data' => $mov], 201);
    }

    /**
     * POST /caixa/{contaId}/baixar-titulos — baixa VÁRIAS parcelas em UMA transação
     * (tudo-ou-nada). Expõe CaixaService::baixarTitulos (F00.6).
     */
    public function baixarTitulos(Request $request, int $contaId): JsonResponse
    {
        $this->autorizar($request, 'financeiro.baixar'); // verbo sensível (A7)
        Conta::query()->findOrFail($contaId);
        $d = $request->validate([
            'itens' => 'required|array|min:1',
            'itens.*.parcela_id' => 'required|integer|exists:financeiroparcelas,id',
            'itens.*.juros' => 'nullable|numeric|gte:0',
            'itens.*.multa' => 'nullable|numeric|gte:0',
            'itens.*.desconto' => 'nullable|numeric|gte:0',
        ]);

        $movs = $this->service->baixarTitulos($contaId, $d['itens'], $request->user()->id);

        return response()->json(['data' => $movs], 201);
    }

    /**
     * POST /caixa/{contaId}/lancar-fechado — lançamento AUTORIZADO em caixa fechado
     * (retroativo). Exige a permissão caixa.edit + intenção explícita. Expõe
     * CaixaService::lancarEmCaixaFechado (F00.6).
     */
    public function lancarFechado(Request $request, int $contaId): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        Conta::query()->findOrFail($contaId);
        $d = $request->validate([
            'valor' => 'required|numeric|not_in:0',
            'tipo' => 'required|string|max:30',
            'descricao' => 'nullable|string|max:255',
            'datahora' => 'nullable|date',
        ]);

        $mov = $this->service->lancarEmCaixaFechado($contaId, (float) $d['valor'], $d['tipo'], [
            'descricao' => $d['descricao'] ?? 'Lançamento em caixa fechado',
            'datahora' => $d['datahora'] ?? null,
            'origem' => 'lancamento-fechado',
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $mov], 201);
    }

    public function transferir(Request $request): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        $d = $request->validate([
            'conta_origem_id' => 'required|integer|exists:contas,id',
            'conta_destino_id' => 'required|integer|exists:contas,id',
            'valor' => 'required|numeric|gt:0',
        ]);

        $res = $this->service->transferir($d['conta_origem_id'], $d['conta_destino_id'], $d['valor'], $request->user()->id);

        return response()->json(['data' => $res], 201);
    }

    public function estornar(Request $request, int $movimentoId): JsonResponse
    {
        $movimento = ContaMovimento::query()->whereKey($movimentoId)->firstOrFail();

        // ABAC (A4) ponto-a-ponto: estornar é verbo sensível. O recurso carrega o
        // DONO do lançamento (user_id → condição ownership: "só estorna o próprio")
        // e o VALOR (condição limite: "estorna até R$ X"). Sem condições no papel,
        // basta ter 'caixa.estornar'.
        $this->autorizarRecurso($request, 'caixa.estornar', [
            'user_id' => $movimento->user_id,
            'valor' => abs((float) $movimento->valor),
        ]);

        $mov = $this->service->estornar($movimentoId, $request->user()->id);

        return response()->json(['data' => $mov], 201);
    }
}
