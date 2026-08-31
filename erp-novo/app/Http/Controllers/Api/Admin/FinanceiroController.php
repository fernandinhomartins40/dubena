<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Financeiro\ConciliacaoContabilService;
use App\Domain\Financeiro\ConciliacaoService;
use App\Domain\Financeiro\ContaExtratoAcao;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Caixa\Conta;
use App\Models\Cliente\Cliente;
use App\Models\Financeiro\CentroCusto;
use App\Models\Financeiro\ContaExtratoRegra;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\Financeiro\PlanoConta;
use App\Rules\ExisteNoTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Financeiro (a pagar / a receber) — N5. Lançamentos por PARCELA (a granularidade
 * de cobrança), resumo, criação via FinanceiroService.
 */
class FinanceiroController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private FinanceiroService $service, private TenantContext $tenant) {}

    /** GET /financeiro/lancamentos?pagarreceber=&status=&q= (parcelas) */
    public function lancamentos(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');

        $q = trim((string) $request->query('q', ''));
        $parcelas = FinanceiroParcela::query()
            ->whereHas('financeiro', function (Builder $b) use ($request, $q) {
                $b->where('cancelado', false);
                if ($pr = $request->query('pagarreceber')) {
                    $b->where('pagarreceber', $pr);
                }
                if ($q !== '') {
                    $b->where(fn (Builder $w) => $w->where('documento', 'ilike', '%'.$q.'%')->orWhere('descricao', 'ilike', '%'.$q.'%'));
                }
            })
            ->when($request->query('status') === 'baixado', fn (Builder $b) => $b->where('baixado', true))
            ->when($request->query('status') === 'aberto', fn (Builder $b) => $b->where('baixado', false))
            ->with('financeiro:id,pagarreceber,documento,descricao,cliente_id')
            ->orderBy('vencimento')
            ->paginate(20);

        $data = collect($parcelas->items())->map(fn (FinanceiroParcela $p) => [
            'id' => $p->id,
            'financeiro_id' => $p->financeiro_id,
            'numero' => $p->numero,
            'datavencimento' => $p->vencimento->toDateString(),
            'valor' => (float) $p->valor,
            'valorefetivado' => (float) $p->valor_efetivado,
            'baixado' => (int) $p->baixado,
            'datahorabaixa' => $p->datahora_baixa?->toIso8601String(),
            'pagarreceber' => $p->financeiro?->pagarreceber,
            'documento' => $p->financeiro?->documento,
            'descricao' => $p->financeiro?->descricao,
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $parcelas->currentPage(),
                'last_page' => $parcelas->lastPage(),
                'per_page' => $parcelas->perPage(),
                'total' => $parcelas->total(),
            ],
        ]);
    }

    /** GET /financeiro/lancamentos/resumo */
    public function resumo(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');

        $base = FinanceiroParcela::query()->whereHas('financeiro', fn (Builder $b) => $b->where('cancelado', false));

        $aReceber = (clone $base)->whereHas('financeiro', fn (Builder $b) => $b->where('pagarreceber', 'R'))->where('baixado', false)->sum('valor');
        $aPagar = (clone $base)->whereHas('financeiro', fn (Builder $b) => $b->where('pagarreceber', 'P'))->where('baixado', false)->sum('valor');
        $vencidas = (clone $base)->where('baixado', false)->where('vencimento', '<', now()->toDateString())->count();

        return response()->json(['data' => [
            'a_receber' => round((float) $aReceber, 2),
            'a_pagar' => round((float) $aPagar, 2),
            'parcelas_vencidas' => $vencidas,
        ]]);
    }

    /** POST /financeiro/lancamentos */
    public function criar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.create');

        $d = $request->validate([
            'pagarreceber' => 'required|in:P,R',
            'cliente_id' => ['nullable', 'integer', new ExisteNoTenant(Cliente::class)],
            'planoconta_id' => ['nullable', 'integer', new ExisteNoTenant(PlanoConta::class)],
            'centrocusto_id' => ['nullable', 'integer', new ExisteNoTenant(CentroCusto::class)],
            'documento' => 'nullable|string|max:60',
            'descricao' => 'nullable|string|max:255',
            'valor' => 'required|numeric|gt:0',
            'data_emissao' => 'nullable|date',
            'num_parcelas' => 'nullable|integer|min:1|max:360',
        ]);

        $financeiro = $this->service->criar(
            array_merge($d, ['empresa_id' => $request->user()->empresa_id, 'grupo_id' => $request->user()->grupo_id]),
            numParcelas: (int) ($d['num_parcelas'] ?? 1),
        );

        return response()->json(['data' => $financeiro], 201);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'financeiro.delete');
        $this->service->cancelar(Financeiro::query()->findOrFail($id));

        return response()->json(['message' => 'Título cancelado.']);
    }

    /**
     * POST /financeiro/lancamentos/agrupar — consolida vários títulos num agrupador
     * (ex.: fechamento de convênio do mês). Expõe FinanceiroService::agrupar (F00.6).
     * Os títulos são resolvidos via query tenant-scoped (não enxerga outra empresa).
     */
    public function agrupar(Request $request): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $d = $request->validate([
            'titulos' => 'required|array|min:1',
            'titulos.*' => 'integer|distinct',
            'pagarreceber' => 'required|in:P,R',
            'cliente_id' => ['nullable', 'integer', new ExisteNoTenant(Cliente::class)],
            'descricao' => 'nullable|string|max:255',
            'num_parcelas' => 'nullable|integer|min:1|max:360',
        ]);

        $titulos = Financeiro::query()->whereIn('id', $d['titulos'])->get();
        abort_if($titulos->count() !== count($d['titulos']), 422, 'Um ou mais títulos não pertencem à empresa ativa.');

        $agrupador = $this->service->agrupar(
            $titulos,
            [
                'empresa_id' => $request->user()->empresa_id,
                'grupo_id' => $request->user()->grupo_id,
                'pagarreceber' => $d['pagarreceber'],
                'cliente_id' => $d['cliente_id'] ?? null,
                'descricao' => $d['descricao'] ?? 'Agrupamento de títulos',
            ],
            numParcelas: (int) ($d['num_parcelas'] ?? 1),
        );

        return response()->json(['data' => $agrupador], 201);
    }

    /** POST /financeiro/lancamentos/{id}/desagrupar — desfaz um agrupador (F00.6). */
    public function desagrupar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $this->service->desagrupar(Financeiro::query()->findOrFail($id));

        return response()->json(['message' => 'Agrupamento desfeito.']);
    }

    /** POST /financeiro/lancamentos/{id}/reparcelar — reparcela o saldo em aberto (F00.6). */
    public function reparcelar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $d = $request->validate([
            'num_parcelas' => 'required|integer|min:1|max:360',
            'data_base' => 'nullable|date',
        ]);

        $novo = $this->service->reparcelar(
            Financeiro::query()->findOrFail($id),
            (int) $d['num_parcelas'],
            $d['data_base'] ?? null,
        );

        return response()->json(['data' => $novo], 201);
    }

    /**
     * GET/POST /financeiro/conciliacao — concilia um extrato OFX com os movimentos
     * da conta no período (C8). Sem OFX (GET inicial), devolve os movimentos do ERP
     * como pendentes; com OFX (campo `ofx` ou upload `arquivo`), casa as transações.
     */
    public function conciliacao(Request $request, ConciliacaoService $service): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');
        $d = $request->validate([
            'conta_id' => ['required', 'integer', Rule::exists('contas', 'id')->where(
                fn ($query) => $query->where('empresa_id', $this->tenant->requireEmpresaId())
            )],
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
            'ofx' => 'nullable|string',
            'arquivo' => 'nullable|file',
        ]);

        $ofx = $d['ofx'] ?? null;
        if (! $ofx && $request->hasFile('arquivo')) {
            $ofx = (string) file_get_contents($request->file('arquivo')->getRealPath());
        }

        $resultado = $service->conciliar((int) $d['conta_id'], $this->tenant->requireEmpresaId(), $ofx ?? '', $d['inicio'], $d['fim']);

        return response()->json(['data' => $resultado]);
    }

    /**
     * POST /financeiro/conciliacao/casar — casamento MANUAL (F5-04).
     *
     * O algoritmo casa por (valor, data) dentro de uma tolerancia. Ele nao
     * alcanca a tarifa que entrou com valor diferente do previsto, nem o
     * deposito que o banco lancou tres dias depois. Quem resolve esses e o
     * operador — e a decisao dele fica registrada com nome e motivo, senao a
     * conciliacao vira um numero que ninguem consegue defender depois.
     *
     * Escrita, e nao leitura: exige `financeiro.edit`.
     */
    public function conciliacaoCasar(Request $request, ConciliacaoService $service): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $empresaId = $this->tenant->requireEmpresaId();

        $d = $request->validate([
            // `exists` filtrado por empresa: `exists:tabela,id` sozinho aceitaria
            // o id de outra revenda, e o servico ainda barraria — mas a mensagem
            // de erro sairia como "invalido", escondendo a tentativa.
            'lancamento_id' => ['required', 'integer', Rule::exists('conciliacao_lancamentos', 'id')
                ->where(fn ($q) => $q->where('empresa_id', $empresaId))],
            'movimento_id' => ['required', 'integer', Rule::exists('contamovimentos', 'id')
                ->where(fn ($q) => $q->where('empresa_id', $empresaId))],
            'motivo' => 'nullable|string|max:255',
        ]);

        $lancamento = $service->casarManualmente(
            (int) $d['lancamento_id'], (int) $d['movimento_id'], $empresaId,
            $request->user()?->id, $d['motivo'] ?? null,
        );

        return response()->json(['data' => $lancamento]);
    }

    /**
     * POST /financeiro/conciliacao/desfazer — desfaz um par (F5-04).
     *
     * Vai para DESFEITO, nao de volta para PENDENTE: "nunca casou" e "casou e
     * alguem desfez" sao fatos diferentes, e o segundo e o que se investiga.
     */
    public function conciliacaoDesfazer(Request $request, ConciliacaoService $service): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $empresaId = $this->tenant->requireEmpresaId();

        $d = $request->validate([
            'lancamento_id' => ['required', 'integer', Rule::exists('conciliacao_lancamentos', 'id')
                ->where(fn ($q) => $q->where('empresa_id', $empresaId))],
            'motivo' => 'nullable|string|max:255',
        ]);

        $lancamento = $service->desfazer(
            (int) $d['lancamento_id'], $empresaId, $request->user()?->id, $d['motivo'] ?? null,
        );

        return response()->json(['data' => $lancamento]);
    }

    /**
     * GET /financeiro/conciliacao-contabil — concilia o financeiro do ERP com o
     * saldo contábil externo (CONSISA) por período (F08). Gate: sem CONSISA
     * configurada, devolve o lado do ERP com diferença = valor (habilitado=false).
     */
    // ── Regras de classificacao do extrato (T4.2) ──
    //
    // Sem elas a importacao OFX devolve uma lista crua e cada linha precisa ser
    // classificada a mao — inviavel no volume real da operacao.

    /** GET /financeiro/contas/{contaId}/extrato-regras */
    public function extratoRegras(Request $request, int $contaId): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');
        $this->contaAtiva($contaId);

        $regras = ContaExtratoRegra::withoutTenant()
            ->where('empresa_id', $this->tenant->requireEmpresaId())
            ->where('conta_id', $contaId)
            ->orderByDesc('prioridade')
            ->orderBy('descricao')
            ->get();

        return response()->json([
            'data' => $regras,
            'acoes' => array_map(
                fn (ContaExtratoAcao $a) => ['valor' => $a->value, 'rotulo' => $a->rotulo()],
                ContaExtratoAcao::cases(),
            ),
        ]);
    }

    /** POST /financeiro/contas/{contaId}/extrato-regras */
    public function criarExtratoRegra(Request $request, int $contaId): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $this->contaAtiva($contaId);

        $dados = $this->validarExtratoRegra($request);
        $dados['conta_id'] = $contaId;
        $dados['empresa_id'] = $this->tenant->requireEmpresaId();
        $dados['grupo_id'] = $this->tenant->requireGrupoId();

        $regra = ContaExtratoRegra::query()->create($dados);

        return response()->json(['data' => $regra], 201);
    }

    /** PUT /financeiro/contas/{contaId}/extrato-regras/{id} */
    public function atualizarExtratoRegra(Request $request, int $contaId, int $id): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $this->contaAtiva($contaId);

        // findOrFail escopado pela conta: o id vem do cliente.
        $regra = ContaExtratoRegra::withoutTenant()
            ->where('empresa_id', $this->tenant->requireEmpresaId())
            ->where('conta_id', $contaId)->findOrFail($id);

        $regra->update($this->validarExtratoRegra($request));

        return response()->json(['data' => $regra->fresh()]);
    }

    /** DELETE /financeiro/contas/{contaId}/extrato-regras/{id} */
    public function excluirExtratoRegra(Request $request, int $contaId, int $id): JsonResponse
    {
        $this->autorizar($request, 'financeiro.edit');
        $this->contaAtiva($contaId);

        ContaExtratoRegra::withoutTenant()
            ->where('empresa_id', $this->tenant->requireEmpresaId())
            ->where('conta_id', $contaId)->findOrFail($id)->delete();

        return response()->json(['data' => ['excluido' => true]]);
    }

    /**
     * Valida a regra, com as OBRIGATORIEDADES POR ACAO do legado.
     *
     * E esta validacao condicional que da sentido a regra: uma de LANCAR sem
     * plano de contas nao classifica nada; uma de TRANSFERIR sem conta de origem
     * nao sabe de onde o dinheiro veio.
     *
     * @return array<string,mixed>
     */
    private function validarExtratoRegra(Request $request): array
    {
        $base = [
            'descricao' => 'required|string|max:255',
            'acao' => ['required', Rule::enum(ContaExtratoAcao::class)],
            'cliente_id' => 'nullable|integer',
            'ativo' => 'nullable|boolean',
            'prioridade' => 'nullable|integer|min:0',
        ];

        $acao = ContaExtratoAcao::tryFrom((string) $request->input('acao'));
        $especificos = $acao?->camposObrigatorios() ?? [];

        // Os campos que a acao NAO exige continuam aceitos como opcionais.
        $opcionais = array_diff_key([
            'condicaopagamento_id' => 'nullable|integer',
            'contamovimentotipo_id' => 'nullable|integer',
            'plano_conta_id' => 'nullable|integer',
            'centro_custo_id' => 'nullable|integer',
            'conta_origem_id' => 'nullable|integer',
        ], $especificos);

        return $request->validate($base + $especificos + $opcionais);
    }

    public function conciliacaoContabil(Request $request, ConciliacaoContabilService $service): JsonResponse
    {
        $this->autorizar($request, 'financeiro.view');
        $d = $request->validate([
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
            'tipo' => 'nullable|in:P,R',
        ]);

        $resultado = $service->conciliar(
            $this->tenant->requireEmpresaId(),
            $d['inicio'],
            $d['fim'],
            $d['tipo'] ?? 'R',
        );

        return response()->json(['data' => $resultado]);
    }

    private function contaAtiva(int $contaId): Conta
    {
        return Conta::withoutTenant()
            ->whereKey($contaId)
            ->where('empresa_id', $this->tenant->requireEmpresaId())
            ->firstOrFail();
    }
}
