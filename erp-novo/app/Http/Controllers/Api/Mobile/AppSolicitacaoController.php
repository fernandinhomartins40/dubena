<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Domain\Fiscal\CupomTextoService;
use App\Domain\Identidade\IdentificarOuCriarCliente;
use App\Domain\Venda\CargaFranqueadoService;
use App\Domain\Venda\CentralVendasService;
use App\Domain\Venda\ExtratoRemuneracaoService;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Geografico\Cidade;
use App\Models\Pedido\Pedido;
use App\Models\Produto\Produto;
use App\Models\Rh\Colaborador;
use App\Models\Satelite\ValeGas;
use App\Models\Venda\PedidoSolicitacao;
use App\Rules\ExisteNoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solicitação de venda pelo app (F4) — o caminho do franqueado/industrial.
 *
 * O vendedor em campo monta o pedido e pede o desconto; a Central decide. Nada
 * aqui move estoque ou financeiro: a solicitação é rascunho até o aceite.
 *
 * **O preço vem do servidor, como no resto do app.** O contrato aceita
 * `produto_id` e `quantidade`; o `preco_unitario` é resolvido a partir do
 * cadastro. Aceitar preço do cliente reproduziria o buraco do legado, onde
 * `MobileRepository::getPreco:602` devolve o valor que o app mandou
 * (`if ($isAppNf) return $preco`) e o vendedor define a própria margem.
 */
class AppSolicitacaoController extends Controller
{
    public function __construct(private CentralVendasService $central) {}

    /** GET /app/v1/entregador/solicitacoes — as próprias solicitações. */
    public function index(Request $request): JsonResponse
    {
        $solicitacoes = PedidoSolicitacao::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->where('solicitante_user_id', $request->user()->id)
            ->with(['cliente:id,nome', 'pedido:id'])
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['data' => $solicitacoes]);
    }

    /** POST /app/v1/entregador/solicitacoes — pede à Central. */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cliente_id' => ['required', 'integer', new ExisteNoTenant(Cliente::class)],
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => ['required', 'integer', new ExisteNoTenant(Produto::class)],
            'itens.*.quantidade' => 'required|numeric|gt:0',
            'condicaopagamento_id' => ['nullable', 'integer', new ExisteNoTenant(CondicaoPagamento::class)],
            'setor_id' => ['nullable', 'integer', new ExisteNoTenant(Setor::class)],
            'desconto_solicitado' => 'nullable|numeric|min:0',
            'justificativa' => 'nullable|string|max:500',
            'observacao' => 'nullable|string|max:255',
        ]);

        $s = $this->central->solicitar($request->user(), $d);

        return response()->json(['data' => $s], 201);
    }

    /**
     * GET /app/v1/entregador/clientes — busca para vender.
     *
     * **Sem isto a venda em campo não existe.** O `missao/clientes` só cadastra,
     * e exige missão ativa — o franqueado que passa na porta de um cliente fora
     * de missão não tinha como encontrá-lo. Era o que deixava a tela de
     * solicitação inalcançável.
     *
     * Busca por nome ou documento, como o `getCliente` do NFWEB. Limite de 50:
     * a tela é de celular e o vendedor refina o termo, não rola mil linhas.
     */
    public function clientes(Request $request): JsonResponse
    {
        $d = $request->validate(['termo' => 'nullable|string|max:120']);
        $termo = trim($d['termo'] ?? '');

        $clientes = Cliente::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->where('ativo', true)
            ->when($termo !== '', fn ($q) => $q->where(function ($w) use ($termo) {
                $w->where('nome', 'like', "%{$termo}%")
                    ->orWhere('cpf', 'like', "%{$termo}%")
                    ->orWhere('cnpj', 'like', "%{$termo}%");
            }))
            ->orderBy('nome')
            ->limit(50)
            ->get(['id', 'nome', 'cpf', 'cnpj', 'endereco', 'numero', 'observacoes']);

        return response()->json(['data' => $clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'nome' => $c->nome,
            'documento' => $c->cpf ?: ($c->cnpj ?: ''),
            'endereco' => trim(($c->endereco ?? '').', '.($c->numero ?? ''), ', '),
            'observacoes' => $c->observacoes ?? '',
        ])->all()]);
    }

    /**
     * POST /app/v1/entregador/clientes — cadastro em campo, sem exigir missão.
     *
     * O `missao/clientes` existente amarra o cadastro a uma atribuição de
     * missão. O vendedor industrial e o franqueado cadastram fora disso — e
     * obrigá-los a abrir uma missão para cadastrar seria burocracia inventada.
     */
    public function cadastrarCliente(Request $request, IdentificarOuCriarCliente $identidade): JsonResponse
    {
        $d = $request->validate([
            'nome' => 'required|string|max:160',
            'cpf' => 'nullable|string|max:14',
            'cnpj' => 'nullable|string|max:18',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:60',
            'ponto_referencia' => 'nullable|string|max:120',
            'cidade_id' => ['nullable', 'integer', new ExisteNoTenant(Cidade::class)],
            'telefone' => 'nullable|string|max:30',
            'observacoes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $fone = isset($d['telefone']) ? preg_replace('/\D/', '', $d['telefone']) : null;

        // A TRAVA POR TELEFONE FOI REMOVIDA (era `DomainException` aqui).
        //
        // Regra herdada do NFWEB (`saveCliente:1538`): telefone repetido
        // rejeitava o cadastro. Em campo isso ABORTAVA A VENDA, e o entregador
        // contornava inventando outro numero — sujando a base exatamente quando
        // ela tentava se proteger. Agora o telefone repetido e SINAL, nao trava:
        // o motor de identidade decide entre reconhecer o cliente, criar e
        // enfileirar para revisao, ou criar limpo.
        $resultado = $identidade->executar(
            (int) $user->empresa_id,
            (int) $user->grupo_id,
            array_filter([
                'nome' => $d['nome'],
                'cpf' => isset($d['cpf']) ? preg_replace('/\D/', '', $d['cpf']) : null,
                'cnpj' => isset($d['cnpj']) ? preg_replace('/\D/', '', $d['cnpj']) : null,
                'endereco' => $d['endereco'] ?? null,
                'numero' => $d['numero'] ?? null,
                'complemento' => $d['complemento'] ?? null,
                'ponto_referencia' => $d['ponto_referencia'] ?? null,
                'cidade_id' => $d['cidade_id'] ?? null,
                'observacoes' => $d['observacoes'] ?? null,
                'telefones' => $fone ? [['telefone' => $fone, 'whatsapp' => true]] : null,
            ], fn ($v) => $v !== null),
            'entregador',
        );

        // 200 quando reconheceu cliente existente; 201 quando criou.
        return response()->json(
            ['data' => $resultado->paraArray()],
            $resultado->criado ? 201 : 200,
        );
    }

    /**
     * GET /app/v1/entregador/extrato — quanto ganhei no período (F5).
     *
     * Sempre do PRÓPRIO usuário: o extrato sai de `$request->user()->id`, nunca
     * de um id do payload. Deixar o app escolher de quem é o extrato exporia o
     * ganho de um colega — e é o tipo de coisa que o legado permitia
     * (NfwebController::savePedido:329 confia no `colaborador_id` do corpo).
     */
    public function extrato(Request $request, ExtratoRemuneracaoService $extrato): JsonResponse
    {
        $d = $request->validate([
            'inicio' => 'nullable|date',
            'fim' => 'nullable|date|after_or_equal:inicio',
        ]);

        // Padrão: mês corrente — o recorte que o franqueado quer ver ao abrir.
        $inicio = $d['inicio'] ?? now()->startOfMonth()->toDateString();
        $fim = $d['fim'] ?? now()->toDateString();

        return response()->json([
            'data' => $extrato->doColaborador(
                (int) $request->user()->empresa_id,
                (int) $request->user()->id,
                $inicio,
                $fim,
            ),
        ]);
    }

    /**
     * GET /app/v1/entregador/estoque — o que estou carregando (F5).
     *
     * O franqueado confere a carga no aparelho antes de sair, e acompanha o que
     * resta durante a rota. Sem isso ele depende de contar de cabeça — e a
     * divergência só aparece no acerto, quando já não dá para reconstituir.
     *
     * Sempre o próprio: o colaborador sai do `user()`, nunca de id no payload.
     */
    public function estoque(Request $request, CargaFranqueadoService $carga): JsonResponse
    {
        $colaborador = Colaborador::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($colaborador === null) {
            // Usuário de app sem cadastro de colaborador não carrega mercadoria.
            return response()->json(['data' => ['modo_estoque' => null, 'itens' => []]]);
        }

        return response()->json([
            'data' => [
                'modo_estoque' => $colaborador->modo_estoque?->value,
                'itens' => $carga->emPoder($colaborador),
            ],
        ]);
    }

    /**
     * GET /app/v1/entregador/pedidos/{id}/cupom — comprovante de entrega em
     * TEXTO, para impressora térmica (F8).
     *
     * Fica aqui e não no controller fiscal porque vale para TODOS os perfis: o
     * entregador funcionário também imprime o comprovante, e emitir nota é
     * privilégio do industrial. São coisas diferentes.
     *
     * Só pedido do próprio entregador: sem esse filtro, um id de outro
     * imprimiria dados de cliente alheio.
     */
    public function cupomPedido(Request $request, int $id, CupomTextoService $cupom): JsonResponse
    {
        $d = $request->validate(['largura' => 'nullable|integer|min:24|max:96']);
        $largura = (int) ($d['largura'] ?? CupomTextoService::LARGURA_PADRAO);

        $pedido = Pedido::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->where('entregador_user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'data' => ['largura' => $largura, 'linhas' => $cupom->doPedido($pedido, $largura)],
        ]);
    }

    /**
     * POST /app/v1/entregador/vale-gas/verificar — valida o código do vale.
     *
     * Porta o `GasdeBolsoVerificacaoActivity` do MovelApp. As três recusas na
     * ordem do legado (`ApiController::getValeGas:456`): não encontrado,
     * cancelado, já utilizado — cancelado antes de utilizado porque é a
     * informação acionável para o entregador.
     */
    public function verificarValeGas(Request $request): JsonResponse
    {
        $d = $request->validate(['codigo' => 'required|string|max:60']);

        $vale = ValeGas::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->where('codigo', trim($d['codigo']))
            ->first();

        if ($vale === null) {
            throw new \DomainException('Vale Gás não encontrado.');
        }

        $situacao = mb_strtolower((string) ($vale->situacao?->value ?? $vale->situacao ?? ''));

        if (str_contains($situacao, 'cancel')) {
            throw new \DomainException('Vale Gás cancelado.');
        }

        if (str_contains($situacao, 'utilizad') || $vale->utilizado_em !== null) {
            throw new \DomainException('Vale Gás já utilizado anteriormente.');
        }

        return response()->json(['data' => [
            'id' => $vale->id,
            'codigo' => $vale->codigo,
            'valor' => (float) ($vale->valor ?? 0),
            'validade' => optional($vale->validade)->toDateString(),
        ]]);
    }

    /**
     * GET /app/v1/entregador/relatorio-vendas — o que vendi no período.
     *
     * Porta o `pedidosReport` do NFWEB e o `getPedidosReport` do MovelApp: os
     * dois apps têm a mesma tela, e o vendedor confere o próprio dia antes de
     * encerrar.
     */
    public function relatorioVendas(Request $request): JsonResponse
    {
        $d = $request->validate([
            'inicio' => 'nullable|date',
            'fim' => 'nullable|date|after_or_equal:inicio',
        ]);

        $inicio = $d['inicio'] ?? now()->startOfMonth()->toDateString();
        $fim = $d['fim'] ?? now()->toDateString();
        $userId = $request->user()->id;

        $pedidos = Pedido::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            // Conta como "minha venda" tanto o que entreguei quanto o que
            // atendi — os dois apps legados usam papéis diferentes para a mesma
            // pergunta ("quanto eu fiz hoje").
            ->where(fn ($q) => $q->where('entregador_user_id', $userId)->orWhere('atendente_user_id', $userId))
            ->where('estoque_movimentado', true)
            ->whereDate('datahora', '>=', $inicio)
            ->whereDate('datahora', '<=', $fim)
            ->with('cliente:id,nome')
            ->orderByDesc('datahora')
            ->get();

        return response()->json(['data' => [
            'periodo' => ['inicio' => $inicio, 'fim' => $fim],
            'total' => round((float) $pedidos->sum('valor_venda'), 2),
            'quantidade' => $pedidos->count(),
            'pedidos' => $pedidos->map(fn (Pedido $p) => [
                'id' => $p->id,
                'cliente' => $p->cliente?->nome ?? '',
                'datahora' => optional($p->datahora)->format('d/m/Y H:i'),
                'valor' => (float) $p->valor_venda,
            ])->all(),
        ]]);
    }

    /** POST /app/v1/entregador/solicitacoes/{id}/cancelar — desistiu na porta. */
    public function cancelar(Request $request, int $id): JsonResponse
    {
        $s = PedidoSolicitacao::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail($id);

        return response()->json(['data' => $this->central->cancelar($s, $request->user())]);
    }
}
