<?php

namespace App\Http\Controllers\Api\Legado;

use App\Domain\Cobranca\BoletoPdfService;
use App\Domain\Fiscal\DanfePdfService;
use App\Domain\Venda\CentralVendasService;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteTelefone;
use App\Models\Cobranca\Boleto;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Mobile\AppDevice;
use App\Models\Pedido\Pedido;
use App\Models\Produto\Produto;
use App\Models\Rh\Colaborador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * F0 — ponte para o NFWEB (app de venda consultiva, React Native).
 *
 * O NFWEB é o app que está mais vivo dos dois legados: último build 17/07/2025,
 * é o único que realmente imprime (§2.9) e roda contra `adm.gasemcasa.com.br`.
 * Esta ponte é o que permite apontá-lo ao erp-novo sem republicar.
 *
 * **Envelope `data`, não `dados`.** Ao contrário do MovelApp, o NFWEB lê
 * `responseHttp.data` (`legado-nfweb/src/helper/Http.js:164`) e trata `OK` e
 * `OPS` como resposta válida — por isso o grupo usa `dialeto.legado:data`.
 *
 * **O que muda em relação ao legado, de propósito:**
 *
 *  - **`savePedido` vira SOLICITAÇÃO, não pedido.** No legado o vendedor fechava
 *    o pedido direto, com preço livre (`MobileRepository::getPreco:602` devolve o
 *    valor que o app manda). A regra do cliente é outra: o franqueado solicita e
 *    a Central decide. Manter o comportamento antigo aqui recriaria exatamente o
 *    problema que a Central existe para resolver.
 *  - **Preço vem do cadastro.** O app continua enviando o campo, e ele é ignorado.
 *  - **Sem usuário-mestre.** O legado autentica com `DEFAULT_USER_SYSTEM` e confia
 *    no `colaborador_id` do corpo (`savePedido:329`); aqui é sempre o token.
 *
 * **Cobertura: as 18 rotas do legado.** Nenhuma função do app fica sem destino —
 * o app tem de continuar inteiro depois de apontar para cá. Onde o erp-novo
 * resolve por outro caminho (e-mail, PDF), a ponte adapta em vez de omitir.
 */
class PonteNfwebController extends Controller
{
    public function __construct(private CentralVendasService $central) {}

    /**
     * POST nfweb/init — carga inicial do app (colaborador, produtos, condições).
     *
     * O legado resolve o colaborador pelo TELEFONE
     * (`NfwebController::onOpenNfwebApp:188`). Aqui o usuário já vem do token, e
     * o telefone é usado só para conferir — trocar de identidade pelo corpo da
     * requisição é o buraco que não se reproduz.
     */
    public function init(Request $request): JsonResponse
    {
        $user = $request->user();
        $colaborador = Colaborador::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('user_id', $user->id)
            ->first();

        if ($colaborador === null) {
            return response()->json(['message' => 'Seu cadastro não foi encontrado, entre em contato com a revenda.'], 422);
        }

        if (! $colaborador->ativo) {
            return response()->json(['message' => 'Seu cadastro foi inativado, entre em contato com a revenda.'], 422);
        }

        // `envia_app_nf` veio migrado do legado e é a flag que decide o que
        // aparece neste app — respeitá-la mantém o catálogo igual ao de hoje.
        $produtos = Produto::query()
            ->where('ativo', true)
            ->where('envia_app_nf', true)
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'preco_venda'])
            ->map(fn (Produto $p) => [
                'id' => $p->id,
                'descricao' => $p->descricao,
                // O app lê `precovenda` (nome do legado), não `preco_venda`.
                'precovenda' => (float) $p->preco_venda,
            ])->all();

        // A CondicaoPagamento do erp-novo não tem `enviaappnf` nem
        // `pedidosituacaoappnf_id` (o legado usava os dois para filtrar e para
        // decidir a situação inicial). Aqui vão as ativas, e a situação do pedido
        // é decidida pela Central no aceite — que é o novo fluxo.
        $pagamentos = CondicaoPagamento::query()
            ->where('ativo', true)
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'dias_primeira'])
            ->map(fn (CondicaoPagamento $c) => [
                'id' => $c->id,
                'descricao' => $c->descricao,
                'dias' => (int) ($c->dias_primeira ?? 0),
            ])->all();

        return response()->json(['data' => [
            'colaborador' => [
                'id' => $colaborador->id,
                'nome' => $colaborador->nome,
                'telefone' => $colaborador->telefone,
            ],
            'revenda' => [
                'id' => $user->empresa_id,
                'setor_id' => $colaborador->setor_estoque_id,
            ],
            'produtos' => $produtos,
            'pagamentos' => $pagamentos,
            'operacoes' => [],
            'veiculos' => [],
        ]]);
    }

    /** POST nfweb/getCliente — busca por nome/documento, no setor do vendedor. */
    public function getCliente(Request $request): JsonResponse
    {
        $termo = trim((string) $request->input('termo', $request->input('nome', '')));

        $clientes = Cliente::query()
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
            'cnpjcpf' => $c->cpf ?: ($c->cnpj ?: ''),
            'endereco' => trim(($c->endereco ?? '').', '.($c->numero ?? ''), ', '),
            'observacoes' => $c->observacoes ?? '',
        ])->all()]);
    }

    /**
     * POST nfweb/savePedido — vira SOLICITAÇÃO à Central.
     *
     * Mudança deliberada de comportamento (ver cabeçalho): o legado fechava o
     * pedido na hora, com preço e desconto livres. A resposta mantém a forma que
     * o app espera (`id` + mensagem), então a tela dele continua funcionando —
     * mas o que nasce é uma solicitação, e quem fecha é a Central.
     */
    public function savePedido(Request $request): JsonResponse
    {
        $payload = $request->input('pedido', []);

        $clienteId = (int) data_get($payload, 'cliente.id', 0);
        if ($clienteId === 0) {
            return response()->json(['message' => 'Cliente não informado.'], 422);
        }

        $itens = collect(data_get($payload, 'produtos', []))
            ->map(fn ($p) => [
                'produto_id' => (int) data_get($p, 'id'),
                'quantidade' => (float) data_get($p, 'qtde', 0),
            ])
            ->filter(fn ($i) => $i['produto_id'] > 0 && $i['quantidade'] > 0)
            ->values()
            ->all();

        if ($itens === []) {
            return response()->json(['message' => 'Informe ao menos um produto.'], 422);
        }

        $desconto = (float) str_replace(',', '.', (string) data_get($payload, 'desconto', 0));

        $solicitacao = $this->central->solicitar($request->user(), [
            'cliente_id' => $clienteId,
            'itens' => $itens,
            'condicaopagamento_id' => data_get($payload, 'pagamento.id'),
            'desconto_solicitado' => max($desconto, 0),
            'observacao' => data_get($payload, 'observacoes'),
            'justificativa' => $desconto > 0 ? 'Solicitado pelo app de vendas.' : null,
        ]);

        return response()->json(['data' => [
            'id' => $solicitacao->id,
            'mensagem' => 'Solicitação nº '.$solicitacao->id.' enviada à central de vendas.',
        ]]);
    }

    /** POST nfweb/getParcelasVencidasCliente — pendência financeira do cliente. */
    public function parcelasVencidas(Request $request): JsonResponse
    {
        $clienteId = (int) $request->input('cliente_id', 0);
        if ($clienteId === 0) {
            return response()->json(['message' => 'Campo "cliente_id" não informado.'], 422);
        }

        // Mesma consulta do legado (getParcelasVencidasCliente:284): em aberto e
        // vencidas, mais antigas primeiro. Informativo — não bloqueia a venda,
        // exatamente como hoje.
        // `financeiroparcelas` (sem underscore) e coluna `vencimento` — nomes
        // conferidos na migration 0006_01_01_000000, não presumidos.
        // `whereDate` e não comparação direta: o CLAUDE.md registra que comparar
        // datetime com string perde o último dia.
        $parcelas = \DB::table('financeiroparcelas as p')
            ->join('financeiros as f', 'f.id', '=', 'p.financeiro_id')
            ->where('f.empresa_id', (int) $request->user()->empresa_id)
            ->where('f.cliente_id', $clienteId)
            ->where('p.baixado', false)
            ->whereDate('p.vencimento', '<', now()->startOfDay())
            ->orderBy('p.vencimento')
            ->get(['p.vencimento', 'f.documento', 'f.descricao', 'p.valor']);

        return response()->json(['data' => $parcelas->map(fn ($p) => [
            'datavencimento' => Carbon::parse($p->vencimento)->format('d/m/Y'),
            'documento' => $p->documento ?? '',
            'descricao' => $p->descricao ?? '',
            'valor' => (float) $p->valor,
        ])->all()]);
    }

    /** GET nfweb/pedidoConsulta — os pedidos do vendedor. */
    public function pedidoConsulta(Request $request): JsonResponse
    {
        $pedidos = Pedido::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->where('atendente_user_id', $request->user()->id)
            ->with(['cliente:id,nome', 'situacao:id,descricao'])
            ->latest('datahora')
            ->limit(100)
            ->get();

        return response()->json(['data' => $pedidos->map(fn (Pedido $p) => [
            'id' => $p->id,
            'cliente' => $p->cliente?->nome ?? '',
            'datahora' => optional($p->datahora)->format('d/m/Y H:i'),
            'valorvenda' => (float) $p->valor_venda,
            'situacao' => $p->situacao?->descricao ?? '',
        ])->all()]);
    }

    /** GET nfweb/nfeConsulta — a nota de um pedido (o app usa para imprimir). */
    public function nfeConsulta(Request $request): JsonResponse
    {
        $pedidoId = (int) $request->input('pedido_id', 0);

        $nota = NotaFiscal::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->when($pedidoId > 0, fn ($q) => $q->where('pedido_id', $pedidoId))
            ->latest('id')
            ->with('itens.produto:id,descricao')
            ->first();

        if ($nota === null) {
            return response()->json(['message' => 'Nota não encontrada para este pedido.'], 422);
        }

        return response()->json(['data' => [[
            'id' => $nota->id,
            'nfnumero' => $nota->numero,
            'nfserie' => $nota->serie,
            'chaveacesso' => $nota->chave,
            'nfsituacao_id' => $nota->situacao?->value === 'AUTORIZADA' ? 100 : 0,
            'descricaosituacao' => $nota->situacao?->value ?? '',
            'vnf' => (float) $nota->valor_total,
        ]]]);
    }

    /** GET nfweb/visualizarDanfe — PDF (passa intacto pelo dialeto). */
    public function visualizarDanfe(Request $request, DanfePdfService $danfe): Response
    {
        return $this->pdfDaNota($request, $danfe, 'inline');
    }

    /**
     * GET nfweb/baixarDanfe — mesmo PDF, como download.
     *
     * O legado tem as duas rotas porque a tela oferece "ver" e "baixar"; só muda
     * o `Content-Disposition`. Manter as duas evita mexer no app.
     */
    public function baixarDanfe(Request $request, DanfePdfService $danfe): Response
    {
        return $this->pdfDaNota($request, $danfe, 'attachment');
    }

    /** POST nfweb/login — o app já autentica por token; aqui é só o eco do perfil. */
    public function login(Request $request): JsonResponse
    {
        return $this->init($request);
    }

    /** GET nfweb/getCadastros — listas para o formulário de cliente. */
    public function getCadastros(Request $request): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;

        return response()->json(['data' => [
            // O app monta selects com estas listas. Vazias não quebram a tela —
            // ele só não oferece a opção — mas cidade/bairro/rua são o que o
            // cadastro de cliente exige de fato.
            'cidades' => \DB::table('cidades')->orderBy('descricao')->limit(500)->get(['id', 'descricao', 'uf']),
            'bairros' => \DB::table('bairros')->where('empresa_id', $empresaId)->orderBy('descricao')->limit(500)->get(['id', 'descricao']),
            'ruas' => \DB::table('ruas')->where('empresa_id', $empresaId)->orderBy('descricao')->limit(1000)->get(['id', 'descricao']),
            'segmentos' => \DB::table('segmentos')->where('empresa_id', $empresaId)->orderBy('descricao')->get(['id', 'descricao']),
            'tipospessoa' => [
                ['id' => 1, 'tipopessoacadastro' => 'F', 'descricao' => 'Pessoa Física'],
                ['id' => 2, 'tipopessoacadastro' => 'J', 'descricao' => 'Pessoa Jurídica'],
            ],
        ]]);
    }

    /**
     * POST nfweb/saveCliente — cadastro em campo.
     *
     * Preserva as regras do legado (`saveCliente:1516`): telefone duplicado
     * rejeita, PF e PJ preenchem campos diferentes, e o cliente nasce no setor
     * do vendedor.
     */
    public function saveCliente(Request $request): JsonResponse
    {
        $c = $request->input('cliente', []);
        $nome = trim((string) data_get($c, 'nome', ''));

        if ($nome === '') {
            return response()->json(['message' => 'Informe o nome do cliente.'], 422);
        }

        $empresaId = (int) $request->user()->empresa_id;
        $fones = collect(data_get($c, 'telefones', []))
            ->map(fn ($t) => preg_replace('/\D/', '', (string) data_get($t, 'telefone', '')))
            ->filter()
            ->values();

        // Regra do legado: telefone já usado por outro cliente rejeita o cadastro
        // — é como a revenda evita dois cadastros para a mesma casa.
        if ($fones->isNotEmpty()) {
            // Normalização em PHP, não em SQL: `regexp_replace` é do Postgres e
            // NÃO existe no sqlite — a comparação quebraria nos testes e passaria
            // despercebida até produção. O CLAUDE.md já registra esse tipo de
            // divergência entre os dois bancos.
            $existe = \DB::table('clientetelefones as ct')
                ->join('clientes as cl', 'cl.id', '=', 'ct.cliente_id')
                ->where('cl.empresa_id', $empresaId)
                ->pluck('ct.telefone')
                ->map(fn ($t) => preg_replace('/\D/', '', (string) $t))
                ->intersect($fones)
                ->isNotEmpty();

            if ($existe) {
                return response()->json(['message' => 'Telefone informado já existe em outro cliente.'], 422);
            }
        }

        $tipo = (string) data_get($c, 'tipopessoa.tipopessoacadastro', 'F');
        $sexoId = (int) data_get($c, 'sexo.id', 0);

        $dados = [
            'empresa_id' => $empresaId,
            'grupo_id' => $request->user()->grupo_id,
            'nome' => $nome,
            'ativo' => true,
            'cep' => data_get($c, 'cep'),
            'uf' => data_get($c, 'uf.uf'),
            'cidade_id' => data_get($c, 'cidade.id'),
            'bairro_id' => data_get($c, 'bairro.id'),
            'rua_id' => data_get($c, 'rua.id'),
            'numero' => data_get($c, 'numero'),
            'complemento' => data_get($c, 'complemento'),
            'ponto_referencia' => data_get($c, 'pontoreferencia'),
            'observacoes' => data_get($c, 'observacoes'),
            'email' => data_get($c, 'email'),
        ];

        // PF e PJ preenchem campos mutuamente exclusivos (regra do legado): quem
        // é físico não tem CNPJ/IE, e vice-versa.
        if ($tipo === 'J') {
            $dados['cnpj'] = preg_replace('/\D/', '', (string) data_get($c, 'cnpj', ''));
            $dados['inscricao_estadual'] = data_get($c, 'ie');
        } else {
            $dados['cpf'] = preg_replace('/\D/', '', (string) data_get($c, 'cpf', ''));
            $dados['rg'] = data_get($c, 'rg');
            $dados['sexo'] = $sexoId === 1 ? 'M' : ($sexoId === 2 ? 'F' : null);
            $dados['data_nascimento'] = data_get($c, 'data_nascimento');
        }

        $cliente = Cliente::create(array_filter($dados, fn ($v) => $v !== null));

        // F6-06A — pelo MODEL, não por `DB::table()->insert()`.
        //
        // A escrita crua pula os model events, e com eles o
        // `ClienteIdentidadeObserver`: o telefone entrava na tabela sem virar
        // traço de identidade. O efeito é silencioso e cumulativo — o cliente
        // fica invisível ao motor de deduplicação, e o próximo cadastro com o
        // mesmo telefone vira duplicata **sem nem ser comparado**.
        //
        // Também perdia o `empresa_id` herdado por relação, deixando o telefone
        // órfão de tenant.
        foreach ($fones as $fone) {
            ClienteTelefone::create([
                'cliente_id' => $cliente->id,
                'telefone' => $fone,
            ]);
        }

        return response()->json(['data' => ['id' => $cliente->id, 'nome' => $cliente->nome]]);
    }

    /** POST nfweb/saveClienteObs — observação do cliente (o vendedor anota na visita). */
    public function saveClienteObs(Request $request): JsonResponse
    {
        $cliente = Cliente::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->find((int) $request->input('cliente_id', 0));

        if ($cliente === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 422);
        }

        $cliente->forceFill(['observacoes' => $request->input('observacoes')])->save();

        return response()->json(['data' => ['id' => $cliente->id]]);
    }

    /** POST nfweb/changeVeiculo — vincula o veículo ao vendedor. */
    public function changeVeiculo(Request $request): JsonResponse
    {
        $veiculoId = (int) $request->input('veiculo_id', 0);
        $empresaId = (int) $request->user()->empresa_id;

        $colaborador = Colaborador::query()
            ->where('empresa_id', $empresaId)->where('user_id', $request->user()->id)->first();

        if ($colaborador === null || $veiculoId === 0) {
            return response()->json(['message' => 'Veículo ou colaborador não encontrado.'], 422);
        }

        // Regra do legado (`changeVeiculo:303`): UM veículo por colaborador —
        // vincular um desvincula os outros. Importa para a placa da NF-e.
        \DB::table('veiculos')->where('empresa_id', $empresaId)
            ->where('colaborador_id', $colaborador->id)->update(['colaborador_id' => null]);
        \DB::table('veiculos')->where('empresa_id', $empresaId)
            ->where('id', $veiculoId)->update(['colaborador_id' => $colaborador->id]);

        return response()->json(['data' => ['veiculo_id' => $veiculoId]]);
    }

    /** POST nfweb/changeRegistrationId — token de push do aparelho. */
    public function changeRegistrationId(Request $request): JsonResponse
    {
        $token = (string) $request->input('registration_id', $request->input('token', ''));

        if ($token !== '') {
            AppDevice::updateOrCreate(
                ['user_id' => $request->user()->id, 'device_id' => $request->input('device_id', 'nfweb')],
                ['empresa_id' => $request->user()->empresa_id, 'push_token' => $token, 'plataforma' => 'android'],
            );
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * GET nfweb/pedidosReport — o que o vendedor vendeu no período.
     *
     * Regra do legado (`pedidosReport:1126`): só os pedidos DELE, entregues, no
     * intervalo. `whereDate` e não comparação de string — o CLAUDE.md registra
     * que `whereBetween` em datetime perde o último dia.
     */
    public function pedidosReport(Request $request): JsonResponse
    {
        $inicio = $request->input('inicio', now()->startOfMonth()->toDateString());
        $fim = $request->input('fim', now()->toDateString());

        $pedidos = Pedido::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->where('atendente_user_id', $request->user()->id)
            ->where('estoque_movimentado', true)
            ->whereDate('datahora', '>=', $inicio)
            ->whereDate('datahora', '<=', $fim)
            ->with(['cliente:id,nome'])
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
                'valorvenda' => (float) $p->valor_venda,
            ])->all(),
        ]]);
    }

    /** GET nfweb/pedidoDuplicata — as parcelas do pedido (o app imprime). */
    public function pedidoDuplicata(Request $request): JsonResponse
    {
        $pedido = Pedido::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->find((int) $request->input('pedido_id', 0));

        if ($pedido === null || $pedido->financeiro_id === null) {
            return response()->json(['message' => 'Este pedido não tem título financeiro.'], 422);
        }

        $parcelas = \DB::table('financeiroparcelas')
            ->where('financeiro_id', $pedido->financeiro_id)
            ->orderBy('numero')
            ->get(['id', 'numero', 'vencimento', 'valor', 'baixado']);

        return response()->json(['data' => $parcelas->map(fn ($p) => [
            'id' => $p->id,
            'numero' => $p->numero,
            'datavencimento' => Carbon::parse($p->vencimento)->format('d/m/Y'),
            'valor' => (float) $p->valor,
            'baixado' => (bool) $p->baixado,
        ])->all()]);
    }

    /** GET nfweb/visualizarBoleto — PDF do boleto da parcela. */
    public function visualizarBoleto(Request $request, BoletoPdfService $pdf): Response
    {
        $boleto = Boleto::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail((int) $request->input('boleto_id', $request->input('id', 0)));

        return response($pdf->gerar($boleto), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="boleto-'.$boleto->id.'.pdf"',
        ]);
    }

    /**
     * GET nfweb/enviarEmail — manda a nota ao cliente.
     *
     * O legado montava e disparava o e-mail no próprio request. Aqui delega ao
     * caminho que o erp-novo já tem, que enfileira — o vendedor não fica
     * esperando o SMTP responder na porta do cliente.
     */
    public function enviarEmail(Request $request): JsonResponse
    {
        $nota = NotaFiscal::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->find((int) $request->input('nfemitida_id', $request->input('id', 0)));

        if ($nota === null) {
            return response()->json(['message' => 'Nota não encontrada.'], 422);
        }

        $destino = $request->input('email') ?: $nota->cliente?->email;
        if (! $destino) {
            return response()->json(['message' => 'Cliente sem e-mail cadastrado.'], 422);
        }

        Log::info('nfweb: envio de nota por e-mail solicitado', [
            'nota_id' => $nota->id, 'destino' => $destino, 'user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => ['enviado' => true, 'destino' => $destino]]);
    }

    private function pdfDaNota(Request $request, DanfePdfService $danfe, string $disposicao): Response
    {
        $nota = NotaFiscal::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail((int) $request->input('nfemitida_id', $request->input('id', 0)));

        return response($danfe->gerar($nota), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposicao.'; filename="danfe-'.$nota->numero.'.pdf"',
        ]);
    }
}
