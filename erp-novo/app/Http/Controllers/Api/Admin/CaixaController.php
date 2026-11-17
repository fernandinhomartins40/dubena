<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Caixa\CaixaService;
use App\Domain\Shared\PdfService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Concerns\PaginaListagem;
use App\Http\Controllers\Controller;
use App\Models\Apoio\Banco;
use App\Models\Caixa\Conta;
use App\Models\Caixa\ContaFechamento;
use App\Models\Caixa\ContaMovimento;
use App\Models\Empresa;
use App\Models\Financeiro\FinanceiroParcela;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

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
    public function recibo(Request $request, int $movimentoId, PdfService $pdf): Response
    {
        $this->autorizar($request, 'caixa.view');

        $mov = ContaMovimento::query()->findOrFail($movimentoId);
        $conta = Conta::query()->find($mov->conta_id);
        $empresa = $mov->empresa_id !== null ? Empresa::query()->find($mov->empresa_id) : null;

        $valor = 'R$ '.number_format((float) $mov->valor, 2, ',', '.');
        $tipo = ((string) $mov->pagarreceber) === 'R' ? 'RECEBIMENTO' : 'PAGAMENTO';

        $corpo = $pdf->campos([
            'Documento' => (string) $mov->id,
            'Data' => $mov->datahora ? Carbon::parse($mov->datahora)->format('d/m/Y H:i') : null,
            'Conta' => (string) ($conta->descricao ?? ''),
            'Historico' => (string) ($mov->descricao ?? ''),
            'Tipo' => $tipo,
        ])
        .'<div class="total">Valor: '.e($valor).'</div>'
        .$pdf->assinatura('Recebemos de / Pagamos a');

        return response(
            $pdf->meiaPagina('Recibo de '.$tipo, $corpo, [
                'empresa' => (string) ($empresa->razao_social ?? ''),
                'cnpj' => (string) ($empresa->cnpj ?? ''),
                'rodape' => 'Documento gerado eletronicamente pelo sistema.',
            ]),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="recibo-'.$mov->id.'.pdf"',
            ],
        );
    }

    /** GET /caixa/contas */
    public function contas(Request $request): JsonResponse
    {
        $this->autorizar($request, 'caixa.view');

        $contas = Conta::query()->where('ativo', true)->orderBy('descricao')->get();
        $comAbertura = ContaFechamento::query()->whereIn('conta_id', $contas->pluck('id'))
            ->where('aberto', true)->pluck('conta_id')->flip();

        // PIE P2-D-09: `fechado=false` sem fechamento aberto é a conta que
        // nunca teve o caixa aberto pelo sistema (conta nova ou migrada). A
        // tela a mostrava "Aberto", o Fechar recusava ("Não há caixa aberto")
        // e não havia Abrir. O estado explícito dá a ela o botão de abrir.
        $rows = $contas->map(fn (Conta $c) => [
            'id' => $c->id,
            'descricao' => $c->descricao,
            'tipo' => $c->tipo,
            'saldoatual' => (float) $c->saldo_atual,
            'fechado' => (int) $c->fechado,
            'estado' => $c->fechado ? 'FECHADO' : ($comAbertura->has($c->id) ? 'ABERTO' : 'SEM_ABERTURA'),
        ]);

        return response()->json(['data' => $rows]);
    }

    public function criarConta(Request $request): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        $d = $request->validate([
            'descricao' => 'required|string|max:255',
            'tipo' => 'nullable|in:CAIXA,BANCO,CARTAO',
            'banco_id' => ['nullable', 'integer', new ExisteNoTenant(Banco::class)],
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
        $conta = Conta::query()->findOrFail($contaId); // valida escopo

        $query = ContaMovimento::query()->where('conta_id', $contaId)->orderByDesc('datahora')->orderByDesc('id');

        $this->filtrarPeriodo($request, $query, 'datahora');

        $resposta = $this->paginar($request, $query);
        $corpo = $resposta->getData(true);

        // A tela mostra "Saldo atual" e oferece estorno: sem o saldo aqui o
        // painel exibia "—" sempre; sem `estornado`, ofereceria estornar de
        // novo um movimento já estornado (o servidor recusa, mas a tela mente).
        $ids = array_column($corpo['data'] ?? [], 'id');
        $estornados = $ids === [] ? collect() : ContaMovimento::query()->whereIn('estorno_de_id', $ids)->pluck('estorno_de_id')->flip();
        $corpo['data'] = array_map(fn (array $m) => $m + ['estornado' => $estornados->has($m['id'])], $corpo['data'] ?? []);
        $corpo['saldo'] = (float) $conta->saldo_atual;

        return response()->json($corpo);
    }

    public function abrir(Request $request, int $contaId): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        Conta::query()->findOrFail($contaId);
        $d = $request->validate(['datahoraabertura' => 'nullable|date']);

        $empresaId = app(TenantContext::class)->empresaId();
        abort_if($empresaId === null, 403, 'Tenant não resolvido.');
        $f = $this->service->abrir($contaId, $empresaId, $d['datahoraabertura'] ?? null, $request->user()->id);

        return response()->json(['data' => $f], 201);
    }

    public function fechar(Request $request, int $contaId): JsonResponse
    {
        $this->autorizar($request, 'caixa.fechar'); // verbo sensível (A7)
        Conta::query()->findOrFail($contaId);
        $d = $request->validate(['datahorafechamento' => 'nullable|date']);

        $empresaId = app(TenantContext::class)->empresaId();
        abort_if($empresaId === null, 403, 'Tenant não resolvido.');
        $f = $this->service->fechar($contaId, $empresaId, $d['datahorafechamento'] ?? null);

        return response()->json(['data' => $f]);
    }

    /** POST /caixa/{contaId}/baixar */
    public function baixar(Request $request, int $contaId): JsonResponse
    {
        // RBAC antes de buscar e validar (PIE R3-F-05); o ABAC, que depende do
        // valor da parcela, continua depois da validação.
        $this->autorizar($request, 'financeiro.baixar');
        Conta::query()->findOrFail($contaId);
        $d = $request->validate([
            'parcela_id' => ['required', 'integer', new ExisteNoTenant(FinanceiroParcela::class)],
            'juros' => 'nullable|numeric|gte:0',
            'multa' => 'nullable|numeric|gte:0',
            'desconto' => 'nullable|numeric|gte:0',
            // Recebimento parcial (P2-D-16): ausente = o total. A regra mora no
            // CaixaService; aqui só o tipo.
            'valor_recebido' => 'nullable|numeric|gt:0',
        ]);

        // ABAC (A4): baixa é verbo sensível; o limite ABAC aplica sobre o valor da parcela.
        $valor = (float) FinanceiroParcela::query()->whereKey($d['parcela_id'])->value('valor');
        $this->autorizarRecurso($request, 'financeiro.baixar', ['valor' => $valor]);

        $empresaId = app(TenantContext::class)->empresaId();
        abort_if($empresaId === null, 403, 'Tenant não resolvido.');
        $mov = $this->service->baixarParcela($contaId, $d['parcela_id'], $empresaId, (float) ($d['juros'] ?? 0), (float) ($d['multa'] ?? 0), (float) ($d['desconto'] ?? 0), $request->user()->id,
            isset($d['valor_recebido']) ? (float) $d['valor_recebido'] : null);

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
            'itens.*.parcela_id' => ['required', 'integer', new ExisteNoTenant(FinanceiroParcela::class)],
            'itens.*.juros' => 'nullable|numeric|gte:0',
            'itens.*.multa' => 'nullable|numeric|gte:0',
            'itens.*.desconto' => 'nullable|numeric|gte:0',
        ]);

        $empresaId = app(TenantContext::class)->empresaId();
        abort_if($empresaId === null, 403, 'Tenant não resolvido.');
        $movs = $this->service->baixarTitulos($contaId, $d['itens'], $empresaId, $request->user()->id);

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

        $empresaId = app(TenantContext::class)->empresaId();
        abort_if($empresaId === null, 403, 'Tenant não resolvido.');
        $mov = $this->service->lancarEmCaixaFechado($contaId, (float) $d['valor'], $d['tipo'], $empresaId, [
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
            'conta_origem_id' => ['required', 'integer', new ExisteNoTenant(Conta::class)],
            'conta_destino_id' => ['required', 'integer', new ExisteNoTenant(Conta::class)],
            'valor' => 'required|numeric|gt:0',
        ]);

        $empresaId = app(TenantContext::class)->empresaId();
        abort_if($empresaId === null, 403, 'Tenant não resolvido.');

        $res = $this->service->transferir(
            $d['conta_origem_id'],
            $d['conta_destino_id'],
            $d['valor'],
            $empresaId,
            $request->user()->id,
        );

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

        $empresaId = app(TenantContext::class)->empresaId();
        abort_if($empresaId === null, 403, 'Tenant não resolvido.');
        $mov = $this->service->estornar($movimentoId, $empresaId, $request->user()->id);

        return response()->json(['data' => $mov], 201);
    }
}
