<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Caixa\CaixaService;
use App\Http\Controllers\Controller;
use App\Models\Caixa\Conta;
use App\Models\Caixa\ContaMovimento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Caixa / Conta — N6. Contas, movimentos, abrir/fechar, baixar parcela,
 * transferir, estornar. Toda mutação passa pelo CaixaService (saldo auditável).
 */
class CaixaController extends Controller
{
    public function __construct(private CaixaService $service)
    {
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

        $movs = ContaMovimento::query()->where('conta_id', $contaId)->orderByDesc('datahora')->limit(200)->get();

        return response()->json(['data' => $movs]);
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
        $this->autorizar($request, 'caixa.edit');
        Conta::query()->findOrFail($contaId);
        $d = $request->validate(['datahorafechamento' => 'nullable|date']);

        $f = $this->service->fechar($contaId, $d['datahorafechamento'] ?? null);

        return response()->json(['data' => $f]);
    }

    /** POST /caixa/{contaId}/baixar */
    public function baixar(Request $request, int $contaId): JsonResponse
    {
        $this->autorizar($request, 'caixa.edit');
        Conta::query()->findOrFail($contaId);
        $d = $request->validate([
            'parcela_id' => 'required|integer|exists:financeiroparcelas,id',
            'juros' => 'nullable|numeric|gte:0',
            'multa' => 'nullable|numeric|gte:0',
            'desconto' => 'nullable|numeric|gte:0',
        ]);

        $mov = $this->service->baixarParcela($contaId, $d['parcela_id'], (float) ($d['juros'] ?? 0), (float) ($d['multa'] ?? 0), (float) ($d['desconto'] ?? 0), $request->user()->id);

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
        $this->autorizar($request, 'caixa.edit');
        ContaMovimento::query()->whereKey($movimentoId)->firstOrFail();

        $mov = $this->service->estornar($movimentoId, $request->user()->id);

        return response()->json(['data' => $mov], 201);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
