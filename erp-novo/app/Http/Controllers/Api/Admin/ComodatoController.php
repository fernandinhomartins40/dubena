<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Satelite\ComodatoPdfService;
use App\Domain\Satelite\ComodatoService;
use App\Domain\Satelite\VinculoVasilhame;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\ComodatoAvaliacao;
use App\Models\Satelite\ComodatoConfig;
use App\Models\Satelite\ComodatoContrato;
use App\Models\Satelite\ComodatoMovimento;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Comodato (vasilhame emprestado) — N8.
 */
class ComodatoController extends Controller
{
    use AutorizaPorPermissao;

    public function __construct(private ComodatoService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->autorizar($request, 'comodato.view');

        $rows = Comodato::query()->with(['cliente:id,nome', 'produto:id,descricao'])
            ->when($request->query('situacao'), fn ($b, $s) => $b->where('situacao', $s))
            ->when($request->query('cliente_id'), fn ($b, $c) => $b->where('cliente_id', $c))
            ->orderByDesc('data_emprestimo')->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'comodato.edit');
        $d = $request->validate([
            'cliente_id' => ['required', 'integer', new ExisteNoTenant(Cliente::class)],
            'produto_id' => ['required', 'integer', new ExisteNoTenant(Produto::class)],
            'setor_id' => ['nullable', 'integer', new ExisteNoTenant(Setor::class)],
            'quantidade' => 'required|numeric|gt:0',
            'data_emprestimo' => 'nullable|date',
            'nome_representante' => 'nullable|string|max:255',
            'cpf_representante' => 'nullable|string|max:14',
            'rg_representante' => 'nullable|string|max:20',
            'data_vencimento' => 'nullable|date',
        ]);
        $d['empresa_id'] = $request->user()->empresa_id;
        $d['grupo_id'] = $request->user()->grupo_id;

        return response()->json(['data' => $this->service->emprestar($d, $request->user()->id)], 201);
    }

    /**
     * GET /comodatos/{id} — o comodato com extrato e versões do contrato.
     *
     * É o que a gaveta de detalhe consome: sem o extrato o operador não tem como
     * ver que houve devolução parcial, nem qual versão do contrato está valendo.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'comodato.view');

        $comodato = Comodato::query()->with(['cliente:id,nome', 'produto:id,descricao'])->findOrFail($id);

        $movimentos = ComodatoMovimento::query()
            ->where('comodato_id', $comodato->id)
            ->with('usuario:id,name')
            ->orderByDesc('id')
            ->get();

        // Quais devoluções já foram anuladas — a tela precisa saber para não
        // oferecer "estornar" duas vezes na mesma linha.
        $estornados = $movimentos
            ->whereNotNull('estorna_id')
            ->pluck('estorna_id')
            ->all();

        return response()->json(['data' => [
            'comodato' => $comodato,
            'em_posse' => $this->service->emPosse($comodato),
            'movimentos' => $movimentos->map(fn ($m) => array_merge($m->toArray(), [
                'estornado' => in_array($m->id, $estornados, true),
            ])),
            'contratos' => $this->service->contratos($comodato),
        ]]);
    }

    /**
     * GET /comodatos/{id}/contrato — o documento que protege o vasilhame.
     *
     * Sem `?versao=`, imprime a versão VIGENTE (a última emitida). Com, imprime
     * aquela versão como ela foi emitida — é assim que se tira segunda via do
     * papel que o cliente assinou antes da devolução parcial.
     *
     * Permissao de leitura: imprimir nao altera nada. Quem consulta o comodato
     * precisa poder gerar o contrato para colher a assinatura.
     */
    public function contrato(Request $request, int $id, ComodatoPdfService $pdf): Response
    {
        $this->autorizar($request, 'comodato.view');

        $comodato = Comodato::query()->findOrFail($id);

        $versao = $this->versaoPedida($request, $comodato);

        return response($pdf->contrato($comodato, $versao), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="comodato-'.$comodato->id
                .($versao !== null ? '-v'.$versao->versao : '').'.pdf"',
        ]);
    }

    /** POST /comodatos/{id}/devolver — parcial ou total. */
    public function devolver(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'comodato.edit');
        $d = $request->validate([
            'quantidade' => 'required|numeric|gt:0',
            'data' => 'nullable|date',
            'observacao' => 'nullable|string|max:255',
        ]);

        $comodato = Comodato::query()->findOrFail($id);
        $atualizado = $this->service->devolver(
            $comodato,
            (float) $d['quantidade'],
            $request->user()->id,
            $d['data'] ?? null,
            $d['observacao'] ?? null,
        );

        return response()->json(['data' => $atualizado]);
    }

    /**
     * POST /comodatos/{id}/movimentos/{movimento}/estornar — desfaz uma
     * devolução lançada errada.
     */
    public function estornar(Request $request, int $id, int $movimento): JsonResponse
    {
        $this->autorizar($request, 'comodato.estornar');
        $d = $request->validate(['observacao' => 'nullable|string|max:255']);

        $mov = ComodatoMovimento::query()
            ->where('comodato_id', $id)
            ->findOrFail($movimento);

        return response()->json([
            'data' => $this->service->estornar($mov, $request->user()->id, $d['observacao'] ?? null),
        ]);
    }

    /** GET /comodatos/{id}/movimentos/{movimento}/recibo — prova da entrega, para o cliente. */
    public function recibo(Request $request, int $id, int $movimento, ComodatoPdfService $pdf): Response
    {
        $this->autorizar($request, 'comodato.view');

        $mov = ComodatoMovimento::query()
            ->where('comodato_id', $id)
            ->findOrFail($movimento);

        return response($pdf->reciboDevolucao($mov), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="devolucao-'.$mov->id.'.pdf"',
        ]);
    }

    /**
     * POST /comodatos/{id}/acrescentar — mais vasilhames no MESMO comodato.
     *
     * Sem isto o operador criava um comodato novo, e o cliente ficava com dois
     * contratos para a mesma relação.
     */
    public function acrescentar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'comodato.edit');
        $d = $request->validate([
            'quantidade' => 'required|numeric|gt:0',
            'observacao' => 'nullable|string|max:255',
            // Omitido = acréscimo no mesmo vasilhame (comportamento anterior).
            // Informado = o cliente passou a ter também outro tipo, e o service
            // decide entre somar na linha existente ou abrir a do produto novo.
            'produto_id' => 'nullable|integer',
        ]);

        $comodato = Comodato::query()->findOrFail($id);

        $alvo = $this->service->acrescentarProduto(
            $comodato,
            (int) ($d['produto_id'] ?? $comodato->produto_id),
            (float) $d['quantidade'],
            $request->user()->id,
            $d['observacao'] ?? null,
        );

        return response()->json(['data' => $alvo->load(['cliente', 'produto'])]);
    }

    /** POST /comodatos/{id}/renovar — novo vencimento + contrato reemitido. */
    public function renovar(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'comodato.edit');
        $d = $request->validate([
            'data_vencimento' => 'required|date',
            'nome_representante' => 'nullable|string|max:255',
            'cpf_representante' => 'nullable|string|max:14',
            'rg_representante' => 'nullable|string|max:20',
        ]);

        $comodato = Comodato::query()->findOrFail($id);

        return response()->json([
            'data' => $this->service->renovar($comodato, $d['data_vencimento'], $request->user()->id, $d),
        ], 201);
    }

    /**
     * GET /comodatos/vigilancia — o painel de giro por cliente.
     *
     * Lê a última avaliação de cada cliente; não recalcula. O cálculo é do cron
     * — fazer na requisição varreria o histórico de pedidos a cada F5.
     */
    public function vigilancia(Request $request): JsonResponse
    {
        $this->autorizar($request, 'comodato.view');

        $empresaId = (int) $request->user()->empresa_id;

        $ultimas = ComodatoAvaliacao::query()
            ->with('cliente:id,nome')
            ->where('empresa_id', $empresaId)
            ->whereIn('id', function ($q) use ($empresaId) {
                $q->selectRaw('max(id)')
                    ->from('comodato_avaliacoes')
                    ->where('empresa_id', $empresaId)
                    ->groupBy('cliente_id');
            })
            ->when($request->query('classificacao'), fn ($b, $c) => $b->where('classificacao', $c))
            ->orderByRaw("case classificacao when 'CRITICO' then 1 when 'ATENCAO' then 2 else 3 end")
            ->orderByDesc('em_posse')
            ->get();

        return response()->json([
            'data' => $ultimas,
            'config' => ComodatoConfig::daEmpresa($empresaId),
        ]);
    }

    /** GET /comodatos/vigilancia/{cliente} — a série histórica de um cliente. */
    public function historicoVigilancia(Request $request, int $cliente): JsonResponse
    {
        $this->autorizar($request, 'comodato.view');

        return response()->json([
            'data' => ComodatoAvaliacao::query()
                ->where('cliente_id', $cliente)
                ->orderByDesc('referencia')
                ->limit(24)
                ->get(),
        ]);
    }

    /** PUT /comodatos/config — a régua da vigilância. */
    public function salvarConfig(Request $request): JsonResponse
    {
        $this->autorizar($request, 'comodato.config');

        $d = $request->validate([
            'dias_janela' => 'required|integer|min:30|max:730',
            'giro_minimo' => 'required|numeric|min:0|max:999',
            'giro_critico' => 'required|numeric|min:0|max:999',
            'queda_atencao' => 'required|numeric|min:1|max:100',
            'queda_critica' => 'required|numeric|min:1|max:100',
            'dias_sem_compra_alerta' => 'required|integer|min:7|max:730',
            'posse_minima_vigiada' => 'required|numeric|min:1',
            'dias_aviso_vencimento' => 'required|integer|min:1|max:365',
            'ativo' => 'boolean',
        ]);

        $config = ComodatoConfig::updateOrCreate(
            ['empresa_id' => $request->user()->empresa_id],
            array_merge($d, ['grupo_id' => $request->user()->grupo_id]),
        );

        return response()->json(['data' => $config]);
    }

    /**
     * GET /comodatos/vinculos — o pareamento casco ↔ gás, para conferência.
     *
     * A vigilância inteira depende deste par: sem ele não há como perguntar
     * "o cliente com 13 vasilhames P13 comprou quanto de P13?". A inferência
     * acerta o caso comum, mas um par errado aqui acusa de desvio um cliente
     * que compra normal — por isso a lista existe para ser conferida por gente.
     */
    public function vinculos(Request $request, VinculoVasilhame $vinculo): JsonResponse
    {
        $this->autorizar($request, 'comodato.view');

        $empresaId = (int) $request->user()->empresa_id;

        $catalogo = Produto::query()
            ->where('ativo', true)
            ->where('empresa_id', $empresaId)
            ->get();

        $linhas = $catalogo
            ->filter(fn (Produto $p) => $vinculo->ehVasilhame($p))
            ->map(function (Produto $p) use ($vinculo, $catalogo) {
                $sugeridos = $vinculo->conteudosDe($p, $catalogo);

                return [
                    'id' => $p->id,
                    'descricao' => $p->descricao,
                    'capacidade' => $vinculo->capacidade($p->descricao),
                    'produto_retornavel_id' => $p->produto_retornavel_id === null
                        ? null
                        : (int) $p->produto_retornavel_id,
                    'sugeridos' => $sugeridos,
                    // Em posse hoje: dimensiona o estrago de um par errado.
                    // Duas somas em vez de uma expressão porque `coalesce` dentro
                    // de sum() se comporta diferente entre sqlite e Postgres.
                    'em_comodato' => $this->emComodato($p->id),
                ];
            })
            ->sortByDesc('em_comodato')
            ->values();

        // Só os candidatos a conteúdo — a lista do seletor.
        $conteudos = $catalogo
            ->filter(fn (Produto $p) => $vinculo->ehConteudo($p) && ! $vinculo->ehVasilhame($p))
            ->map(fn (Produto $p) => [
                'id' => $p->id,
                'descricao' => $p->descricao,
                'capacidade' => $vinculo->capacidade($p->descricao),
            ])
            ->sortBy('descricao')
            ->values();

        return response()->json(['data' => $linhas, 'conteudos' => $conteudos]);
    }

    /** Vasilhames deste produto ainda em poder de clientes. */
    private function emComodato(int $produtoId): float
    {
        $base = Comodato::query()
            ->where('produto_id', $produtoId)
            ->whereIn('situacao', ['ATIVO', 'PARCIAL']);

        return round(
            (float) (clone $base)->sum('quantidade')
                - (float) (clone $base)->sum('quantidade_devolvida'),
            3,
        );
    }

    /**
     * PUT /comodatos/vinculos/{produto} — confirma ou corrige um par.
     *
     * `null` desfaz o vínculo: é a saída para o caso ambíguo que a heurística
     * não deve adivinhar sozinha.
     */
    public function salvarVinculo(Request $request, int $produto): JsonResponse
    {
        $this->autorizar($request, 'comodato.config');

        $d = $request->validate([
            'produto_retornavel_id' => ['nullable', 'integer', new ExisteNoTenant(Produto::class)],
        ]);

        $empresaId = (int) $request->user()->empresa_id;

        $vasilhame = Produto::query()
            ->where('empresa_id', $empresaId)
            ->findOrFail($produto);

        $conteudoId = $d['produto_retornavel_id'] ?? null;

        if ($conteudoId !== null) {
            // O par não atravessa empresa. `exists:produtos,id` sozinho deixaria
            // apontar para o produto de outro tenant — o consumo seria medido
            // contra um id que nunca aparece nos pedidos daqui, dando giro zero
            // e alerta falso contra a carteira inteira.
            $conteudo = Produto::query()
                ->where('empresa_id', $empresaId)
                ->find($conteudoId);

            if ($conteudo === null) {
                return response()->json([
                    'message' => 'O produto de recarga precisa ser da mesma empresa.',
                ], 422);
            }
        }

        $vasilhame->forceFill(['produto_retornavel_id' => $conteudoId])->save();

        return response()->json(['data' => $vasilhame->only([
            'id', 'descricao', 'produto_retornavel_id',
        ])]);
    }

    /** POST /comodatos/{id}/reemitir — versão nova sem mexer em saldo. */
    public function reemitir(Request $request, int $id): JsonResponse
    {
        $this->autorizar($request, 'comodato.edit');

        $comodato = Comodato::query()->findOrFail($id);

        return response()->json([
            'data' => $this->service->reemitirContrato($comodato, $request->user()->id),
        ], 201);
    }

    /** POST /comodatos/{id}/contratos/{contrato}/assinado — a via assinada voltou. */
    public function marcarAssinado(Request $request, int $id, int $contrato): JsonResponse
    {
        $this->autorizar($request, 'comodato.edit');

        $versao = ComodatoContrato::query()
            ->where('comodato_id', $id)
            ->findOrFail($contrato);

        return response()->json(['data' => $this->service->marcarAssinado($versao)]);
    }

    /**
     * Versão a imprimir: a pedida, ou a vigente.
     *
     * Devolve null quando o comodato não tem versão nenhuma — o caso dos 975
     * migrados do legado, que imprimem do estado atual como sempre imprimiram.
     */
    private function versaoPedida(Request $request, Comodato $comodato): ?ComodatoContrato
    {
        $pedida = $request->query('versao');

        if ($pedida !== null) {
            return ComodatoContrato::query()
                ->where('comodato_id', $comodato->id)
                ->where('versao', (int) $pedida)
                ->with('movimento')
                ->firstOrFail();
        }

        return ComodatoContrato::query()
            ->where('comodato_id', $comodato->id)
            ->with('movimento')
            ->orderByDesc('versao')
            ->first();
    }
}
