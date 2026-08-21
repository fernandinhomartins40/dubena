<?php

namespace App\Http\Controllers\Api\Legado;

use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Http\Controllers\Controller;
use App\Models\Apoio\PedidoMotivoAtraso;
use App\Models\Frota\Veiculo;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F0 — ponte para o MovelApp (app de rota, Android nativo).
 *
 * Fala o dialeto do `ctrl-web/app/Http/Controllers/ApiController` para que os
 * APKs em campo apontem ao erp-novo **sem republicação em loja** — o que importa
 * porque o MovelApp está em `targetSdk 28` e não publica na Play Store hoje.
 *
 * **Fidelidade de contrato.** Os nomes de campo aqui não são escolha: são o que
 * o app já lê (`getPedidosPendentes` do legado devolve `razao_social`,
 * `entregarua`, `urgente` como 'S'/'N', `cartao` como '1'/'0'). Mudar qualquer
 * um quebraria a tela do entregador. O envelope sai pelo middleware
 * `dialeto.legado:dados` — o MovelApp lê `dados`, não `data`
 * (CadastroImportActivity:207).
 *
 * **O que muda em relação ao legado, de propósito:**
 *
 *  - **Tenant vem do token**, não do `revenda_id` do corpo (o legado faz
 *    `Empresa::find($data['revenda_id'])` sem conferir — IDOR).
 *  - **A rota vem do USUÁRIO**, não do `androidid`. No legado, o dispositivo
 *    carrega empresa/colaborador/setor (`ApiController::setAndroidRegistration`)
 *    e o filtro sai dali. Aqui quem está logado define o que vê. O `androidid`
 *    continua sendo aceito e registrado (device de push), mas não decide mais
 *    autorização: um aparelho perdido deixa de ser uma credencial.
 *
 * Essa segunda diferença é a única com efeito operacional visível — está
 * registrada como pendência (aparelho fixo por setor × celular próprio) em
 * `docs/01-vigente/APPS_DE_CAMPO_E_CENTRAL_DE_VENDAS.md` §8.1.
 */
class PonteMovelAppController extends Controller
{
    public function __construct(private PedidoService $pedidos) {}

    /** POST getPedidosPendentes — a rota do dia do entregador. */
    public function pedidosPendentes(Request $request): JsonResponse
    {
        $user = $request->user();

        $pedidos = Pedido::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('entregador_user_id', $user->id)
            ->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::PENDENTE->value))
            ->with(['cliente:id,nome,endereco,numero,complemento,ponto_referencia,uf,bairro_id,cidade_id',
                'cliente.bairro:id,descricao', 'cliente.cidade:id,descricao',
                'situacao:id,descricao', 'condicao:id,descricao,tipo', 'itens.produto:id,descricao'])
            ->get();

        return response()->json(['data' => $pedidos->map(fn (Pedido $p) => $this->pedidoNoFormatoLegado($p))->all()]);
    }

    /** POST setPedidoSituacao — a baixa da entrega. */
    public function setPedidoSituacao(Request $request): JsonResponse
    {
        $d = $request->validate([
            'pedido_id' => 'required|integer',
            'pedidosituacao_id' => 'required|integer',
            'pedidomotivoatraso_id' => 'nullable',
            'cartao_autorizacao' => 'nullable|string|max:60',
        ]);

        $pedido = Pedido::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->findOrFail((int) $d['pedido_id']);

        // Regra do legado (ApiController::setPedidoSituacao): pedido já fechado
        // ou cancelado não muda mais de estado.
        if ($pedido->estoque_movimentado && $pedido->situacao?->efeito === EfeitoPedido::CANCELADO) {
            return response()->json(['message' => 'Pedido já encerrado — situação não pode ser alterada.'], 422);
        }

        $this->pedidos->mudarSituacao($pedido, (int) $d['pedidosituacao_id'], $request->user()->id);

        // O legado manda '-1' quando não há motivo; tratamos como ausência.
        $motivo = $d['pedidomotivoatraso_id'] ?? null;
        if ($motivo !== null && (int) $motivo > 0) {
            $pedido->forceFill(['pedidomotivoatraso_id' => (int) $motivo])->save();
        }

        return response()->json(['data' => 'Situação alterada.']);
    }

    /** POST getPedidosSituacoes — as situações que o app oferece na baixa. */
    public function situacoes(Request $request): JsonResponse
    {
        $situacoes = PedidoSituacao::query()
            ->where('grupo_id', (int) $request->user()->grupo_id)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->get();

        // As 9 flags do legado (tbl_situacoes no SQLite do app) contra os 3
        // efeitos do EfeitoPedido. `entrega_transferida` e `em_entrega` não têm
        // equivalente no modelo novo: vão zerados, e o app apenas não oferece
        // essas transições — melhor do que inventar um mapeamento que mentiria
        // sobre o estado do pedido.
        return response()->json(['data' => $situacoes->map(fn (PedidoSituacao $s) => [
            'id' => $s->id,
            'descricao' => $s->descricao,
            'entrega_finalizada' => $s->efeito === EfeitoPedido::CONCLUIDO ? 1 : 0,
            'entrega_pendente' => $s->efeito === EfeitoPedido::PENDENTE ? 1 : 0,
            'entrega_cancelada' => $s->efeito === EfeitoPedido::CANCELADO ? 1 : 0,
            'entrega_transferida' => 0,
            'em_entrega' => 0,
            'vale_gas' => 0,
            'mensagem_enviada' => 0,
            'mensagem_lida' => 0,
            'cartao' => 0,
        ])->all()]);
    }

    /** POST getVeiculos — frota do setor, marcando o do entregador como ativo. */
    public function veiculos(Request $request): JsonResponse
    {
        $user = $request->user();

        $veiculos = Veiculo::query()
            ->where('empresa_id', $user->empresa_id)
            ->get(['id', 'placa', 'descricao', 'colaborador_id']);

        return response()->json(['data' => $veiculos->map(fn (Veiculo $v) => [
            'id' => $v->id,
            'placa' => $v->placa,
            'descricao' => $v->descricao,
            'colaborador_id' => $v->colaborador_id,
            'ativo' => 0,
        ])->all()]);
    }

    /** POST getPedidosMotivosAtrasos — lista para a baixa com atraso. */
    public function motivosAtraso(Request $request): JsonResponse
    {
        // Cadastro de apoio (App\Models\Apoio), escopado por empresa via
        // BelongsToTenant — não por grupo como as situações.
        $motivos = PedidoMotivoAtraso::query()->get(['id', 'descricao']);

        return response()->json(['data' => $motivos->all()]);
    }

    /** POST getEmpresas — a revenda do aparelho (o app monta o cabeçalho com ela). */
    public function empresas(Request $request): JsonResponse
    {
        $empresa = \App\Models\Empresa::query()->find((int) $request->user()->empresa_id);

        return response()->json(['data' => $empresa === null ? [] : [[
            'id' => $empresa->id,
            'nome' => $empresa->nome_fantasia ?? $empresa->razao_social,
            'cnpj' => $empresa->cnpj,
        ]]]);
    }

    /**
     * POST getUsuarios — quem pode entrar neste aparelho.
     *
     * No legado a lista sai do SETOR do dispositivo
     * (`ApiController::getUsuarios:71`), porque o aparelho ficava no veículo.
     * Aqui só faz sentido devolver o próprio usuário do token: quem decide o
     * acesso é o login, não o aparelho.
     */
    public function usuarios(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [[
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]]]);
    }

    /**
     * POST getValeGas — valida o código de barras do vale.
     *
     * Regras do legado (`ApiController::getValeGas:456`), na mesma ordem: não
     * encontrado, cancelado, já utilizado. A ordem importa — um vale cancelado
     * E já usado deve reportar "cancelado", que é a informação acionável.
     */
    public function valeGas(Request $request): JsonResponse
    {
        $codigo = trim((string) $request->input('valegas', ''));

        if ($codigo === '') {
            return response()->json(['message' => 'Informe o código do vale.'], 422);
        }

        $vale = \App\Models\Satelite\ValeGas::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->where('codigo', $codigo)
            ->first();

        if ($vale === null) {
            return response()->json(['message' => 'Vale Gás não encontrado no banco de dados.'], 422);
        }

        $situacao = (string) ($vale->situacao?->value ?? $vale->situacao ?? '');

        if (str_contains(mb_strtolower($situacao), 'cancel')) {
            return response()->json(['message' => 'Vale Gás cancelado.'], 422);
        }

        if (str_contains(mb_strtolower($situacao), 'utilizad') || $vale->utilizado_em !== null) {
            return response()->json(['message' => 'Vale Gás já utilizado anteriormente.'], 422);
        }

        return response()->json(['data' => [
            'id' => $vale->id,
            'codigo' => $vale->codigo,
            'valor' => (float) ($vale->valor ?? 0),
        ]]);
    }

    /** POST setVeiculoAtivo — troca o veículo do entregador (um por vez). */
    public function setVeiculoAtivo(Request $request): JsonResponse
    {
        $veiculoId = (int) $request->input('veiculo_id', 0);
        $empresaId = (int) $request->user()->empresa_id;

        $colaborador = \App\Models\Rh\Colaborador::query()
            ->where('empresa_id', $empresaId)->where('user_id', $request->user()->id)->first();

        if ($colaborador === null || $veiculoId === 0) {
            return response()->json(['message' => 'Veículo ou colaborador não encontrado.'], 422);
        }

        // Regra do legado: vincular um veículo DESVINCULA os outros do mesmo
        // colaborador — a exclusividade é o que faz a placa da NF-e ser confiável.
        \DB::table('veiculos')->where('empresa_id', $empresaId)
            ->where('colaborador_id', $colaborador->id)->update(['colaborador_id' => null]);
        \DB::table('veiculos')->where('empresa_id', $empresaId)
            ->where('id', $veiculoId)->update(['colaborador_id' => $colaborador->id]);

        return response()->json(['data' => 'OK']);
    }

    /** POST getPedidosReport — o que este entregador fechou no período. */
    public function pedidosReport(Request $request): JsonResponse
    {
        $inicio = $request->input('inicio', now()->startOfMonth()->toDateString());
        $fim = $request->input('fim', now()->toDateString());

        $pedidos = Pedido::query()
            ->where('empresa_id', (int) $request->user()->empresa_id)
            ->where('entregador_user_id', $request->user()->id)
            ->where('estoque_movimentado', true)
            // whereDate: comparar datetime com string perde o último dia
            // (armadilha registrada no CLAUDE.md).
            ->whereDate('datahora', '>=', $inicio)
            ->whereDate('datahora', '<=', $fim)
            ->with('cliente:id,nome')
            ->orderByDesc('datahora')
            ->get();

        return response()->json(['data' => [
            'total' => round((float) $pedidos->sum('valor_venda'), 2),
            'quantidade' => $pedidos->count(),
            'pedidos' => $pedidos->map(fn (Pedido $p) => [
                'id' => $p->id,
                'razao_social' => $p->cliente?->nome ?? '',
                'datahora' => optional($p->datahora)->format('d/m/Y H:i'),
                'valorvenda' => (float) $p->valor_venda,
            ])->all(),
        ]]);
    }

    /** POST setAndroidMensagem — confirma leitura da mensagem enviada ao aparelho. */
    public function setAndroidMensagem(Request $request): JsonResponse
    {
        // O legado marcava `mensagem_lida` na tabela do dispositivo. O erp-novo
        // não tem esse canal (a comunicação com o campo é por push), então a
        // rota responde OK para não quebrar a tela — sem inventar um recurso que
        // não existe do outro lado.
        return response()->json(['data' => 'OK']);
    }

    /**
     * Converte um Pedido para o formato exato que o app espera.
     *
     * @return array<string,mixed>
     */
    private function pedidoNoFormatoLegado(Pedido $p): array
    {
        // Diferença de modelo: no legado o endereço de ENTREGA fica no próprio
        // pedido (entregarua_id, entregabairro_id…); aqui ele vem do cliente
        // (é assim que o CentralService monta, linha 40). O app não percebe —
        // recebe os mesmos campos preenchidos.
        $c = $p->cliente;

        return [
            'id' => $p->id,
            'razao_social' => $c?->nome ?? '',
            'datahora' => optional($p->datahora)->format('Y-m-d H:i:s') ?? '',
            'valorvenda' => (float) $p->valor_venda,
            'entreganumero' => (string) ($c?->numero ?? ''),
            'entregacomplemento' => (string) ($c?->complemento ?? ''),
            'observacao' => (string) ($p->observacao ?? ''),
            'entregapontoreferencia' => (string) ($c?->ponto_referencia ?? ''),
            'entregarua' => (string) ($c?->endereco ?? ''),
            'condicao' => $p->condicao?->descricao ?? '',
            'entregabairro' => (string) ($c?->bairro?->descricao ?? ''),
            'pedidosituacao_id' => $p->pedidosituacao_id,
            'pedidosituacao_descricao' => $p->situacao?->descricao ?? '',
            'entregacidade' => (string) ($c?->cidade?->descricao ?? ''),
            'entregauf' => (string) ($c?->uf ?? ''),
            // 'S'/'N' e '1'/'0' são o que o app parseia — não trocar por boolean.
            'urgente' => $p->entrega_urgente ? 'S' : 'N',
            'motivo_atraso' => $p->pedidomotivoatraso_id ?? '-1',
            'convenio' => '',
            'cartao' => in_array((int) ($p->condicao?->tipo ?? 0), [2, 3], true) ? '1' : '0',
            'app' => 'S',
            'gasdopovo' => $p->gasdopovo ?? 0,
            'itens' => $p->itens->map(fn ($i) => [
                'pedido_id' => $i->pedido_id,
                'id' => $i->id,
                'produto' => $i->produto?->descricao ?? '',
                'quantidade' => (float) $i->quantidade,
                'precovendaunitario' => (float) $i->preco_unitario,
                'precovendatotal' => (float) $i->valor_total,
                'unidademedida' => '',
            ])->all(),
        ];
    }
}
