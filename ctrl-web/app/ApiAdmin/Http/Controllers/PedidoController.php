<?php

namespace App\ApiAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Pedido;
use App\Http\Controllers\PedidoController as PedidoLegacy;
use App\Http\Requests\PedidoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * F9 (SPA React) — API admin de PEDIDOS (o ativo mais caro).
 * Auditado: PedidoController legado (1661 ln). A CRIAÇÃO/atualização DELEGA ao
 * PedidoController@store/@update legado (motor de orquestração atômica:
 * estoque+financeiro+NF+vale-gás PRESERVADO — caracterizado por PedidoBaselineTest).
 * Não reescreve o motor. RBAC: pedido.*. Contratos do app mobile intactos (api.php).
 */
class PedidoController extends Controller
{
    private function autorizar(Request $request, string $acao): void
    {
        abort_unless($request->user()->podeRecurso('pedido', $acao), 403, 'Sem permissão.');
    }

    private function escopo($query, Request $request)
    {
        return $query->where('pedidos.empresa_id', $request->user()->empresa_id);
    }

    /**
     * Popula a sessão (empresa ativa + permissões) que o motor legado e a
     * PedidoPolicy exigem (a API admin é stateless).
     */
    private function contexto(Request $request): void
    {
        Session::put('empresa_padrao', \App\Empresa::find($request->user()->empresa_id));
        Session::put('permissoes', collect([(object) [
            'descricao' => 'pedido.index', 'criar' => 1, 'editar' => 1, 'deletar' => 1, 'visualizar' => 1,
        ]]));
        \Auth::login($request->user());
    }

    /** GET /api/admin/pedidos?status=&q=&page= — lista para painel/Kanban. */
    public function index(Request $request)
    {
        $this->autorizar($request, 'view');
        $q = trim((string) $request->query('q', ''));
        $situacaoId = (int) $request->query('situacao_id', 0);

        $page = $this->escopo(Pedido::query(), $request)
            ->leftJoin('clientes as c', 'c.id', '=', 'pedidos.cliente_id')
            ->leftJoin('pedidosituacaos as s', 's.id', '=', 'pedidos.pedidosituacao_id')
            ->when($situacaoId, fn ($w) => $w->where('pedidos.pedidosituacao_id', $situacaoId))
            ->when($q !== '', fn ($w) => $w->where('c.nome', 'ilike', '%' . $q . '%')->orWhere('pedidos.id', (int) $q))
            ->orderByDesc('pedidos.datahora')
            ->paginate(min((int) $request->query('per_page', 30), 100), [
                'pedidos.id', 'pedidos.datahora', 'pedidos.valorvenda', 'pedidos.pedidosituacao_id',
                'c.nome as cliente', 's.descricao as situacao', 's.fechadoconcluido', 's.fechadocancelado',
            ]);

        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    /** GET /api/admin/pedidos/kanban — colunas por situação com contagem/total. */
    public function kanban(Request $request)
    {
        $this->autorizar($request, 'view');
        $empresaId = (int) $request->user()->empresa_id;
        $grupo = (int) (optional($request->user()->empresa)->grupo_id ?? $request->user()->grupo_id);

        $situacoes = DB::table('pedidosituacaos')->where('grupo_id', $grupo)->where('ativo', 1)
            ->orderBy('id')->get(['id', 'descricao', 'fechadoconcluido', 'fechadocancelado']);

        $colunas = $situacoes->map(function ($s) use ($empresaId) {
            $base = fn () => DB::table('pedidos')->where('pedidos.empresa_id', $empresaId)->where('pedidosituacao_id', $s->id);
            return [
                'situacao_id' => $s->id,
                'descricao'   => $s->descricao,
                'total'       => $base()->count(),
                'valor'       => (float) $base()->sum('valorvenda'),
                'pedidos'     => $base()->leftJoin('clientes as c', 'c.id', '=', 'pedidos.cliente_id')
                    ->orderByDesc('pedidos.datahora')->limit(20)
                    ->get(['pedidos.id', 'pedidos.valorvenda', 'pedidos.datahora', 'c.nome as cliente']),
            ];
        });
        return response()->json(['data' => $colunas]);
    }

    /** GET /api/admin/pedidos/{id} — ficha (jornada: cliente, itens, pagamento, NF). */
    public function show(Request $request, $id)
    {
        $this->autorizar($request, 'view');
        $pedido = $this->escopo(Pedido::query(), $request)->findOrFail($id);

        $itens = DB::table('pedidoitems as pi')
            ->leftJoin('produtos as p', 'p.id', '=', 'pi.produto_id')
            ->where('pi.pedido_id', $pedido->id)
            ->get(['pi.id', 'pi.quantidade', 'pi.precovendaunitario', 'pi.precovendatotal', 'p.descricao as produto']);

        $extra = [
            'cliente'   => optional(\App\Cliente::find($pedido->cliente_id))->nome,
            'situacao'  => optional(\App\Pedidosituacao::find($pedido->pedidosituacao_id))->descricao,
            'condicao'  => $pedido->condicaopagamento_id ? DB::table('condicaopagamentos')->where('id', $pedido->condicaopagamento_id)->value('descricao') : null,
            'itens'     => $itens,
            'tem_nf'    => DB::table('nfemitidas')->where('financeiro_id', $pedido->financeiro_id)->exists(),
        ];
        return response()->json(['data' => array_merge($pedido->toArray(), $extra)]);
    }

    /** GET /api/admin/pedidos/situacoes */
    public function situacoes(Request $request)
    {
        $this->autorizar($request, 'view');
        $grupo = (int) (optional($request->user()->empresa)->grupo_id ?? $request->user()->grupo_id);
        return response()->json(['data' => DB::table('pedidosituacaos')->where('grupo_id', $grupo)->where('ativo', 1)->orderBy('id')->get(['id', 'descricao', 'fechadoconcluido', 'fechadocancelado'])]);
    }

    /**
     * POST /api/admin/pedidos — cria via motor legado (orquestração atômica).
     * Recebe payload limpo do React e monta o formato esperado pelo store legado.
     */
    public function store(Request $request)
    {
        $this->autorizar($request, 'create');
        $payload = $this->montarPayload($request);
        $this->contexto($request);

        $legacy = app(PedidoLegacy::class);
        $pedidoRequest = PedidoRequest::create('/api/pedido', 'POST', $payload);
        $pedidoRequest->setUserResolver(fn () => $request->user());

        $resp = $legacy->store($pedidoRequest, new Pedido(), true);
        return $this->traduzir($resp, $request);
    }

    /** PUT /api/admin/pedidos/{id} — atualiza/transiciona via motor legado. */
    public function update(Request $request, $id)
    {
        $this->autorizar($request, 'edit');
        $this->escopo(Pedido::query(), $request)->findOrFail($id);
        $payload = $this->montarPayload($request);
        $this->contexto($request);

        $legacy = app(PedidoLegacy::class);
        $pedidoRequest = PedidoRequest::create("/api/pedido/$id", 'POST', $payload);
        $pedidoRequest->setUserResolver(fn () => $request->user());

        $resp = $legacy->update($pedidoRequest, $id);
        return $this->traduzir($resp, $request, $id);
    }

    /**
     * Monta o payload do form legado a partir do JSON do React.
     * itens: [{produto_id, quantidade, preco_unitario}] → produtospedido [[id,desc,preco,qtd]].
     */
    private function montarPayload(Request $request): array
    {
        $data = $request->validate([
            'cliente_id'           => 'required|integer',
            'condicaopagamento_id' => 'required|integer',
            'pedidooperacao_id'    => 'required|integer',
            'pedidosituacao_id'    => 'required|integer',
            'entregasetor_id'      => 'required|integer',
            'colaborador_id'       => 'required|integer',
            'entregarua_id'        => 'required|integer',
            'entregabairro_id'     => 'required|integer',
            'entregacidade_id'     => 'required|integer',
            'entreganumero'        => 'required|string|max:10',
            'ufentrega'            => 'required|string|max:2',
            'valorvenda'           => 'required|numeric',
            'valordesconto'        => 'nullable|numeric',
            'entregataxa'          => 'nullable|numeric',
            'entregatelefone'      => 'nullable|string|max:20',
            'latitude'             => 'nullable|numeric',
            'longitude'            => 'nullable|numeric',
            'itens'                => 'required|array|min:1',
            'itens.*.produto_id'      => 'required|integer',
            'itens.*.quantidade'      => 'required|numeric|min:0.0001',
            'itens.*.preco_unitario'  => 'required|numeric',
        ]);

        $lat = $data['latitude'] ?? 0;
        $lng = $data['longitude'] ?? 0;
        $produtospedido = json_encode(array_map(fn ($i) => [
            (string) $i['produto_id'], '', number_format((float) $i['preco_unitario'], 2, ',', '.'), $i['quantidade'],
        ], $data['itens']));

        return [
            'empresa_id'        => (int) $request->user()->empresa_id,
            'cliente_id'        => $data['cliente_id'],
            'condicaopagamento_id' => $data['condicaopagamento_id'],
            'pedidooperacao_id' => $data['pedidooperacao_id'],
            'pedidosituacao_id' => $data['pedidosituacao_id'],
            'entregasetor_id'   => $data['entregasetor_id'],
            'colaborador_id'    => $data['colaborador_id'],
            'entregarua_id'     => $data['entregarua_id'],
            'entregabairro_id'  => $data['entregabairro_id'],
            'entregacidade_id'  => $data['entregacidade_id'],
            'entreganumero'     => $data['entreganumero'],
            'ufentrega'         => $data['ufentrega'],
            'entregalatitude'   => $lat,
            'entregalongitude'  => $lng,
            'entregatelefone'   => $data['entregatelefone'] ?? '',
            'entregataxa'       => number_format((float) ($data['entregataxa'] ?? 0), 2, ',', '.'),
            'valorvenda'        => number_format((float) $data['valorvenda'], 2, ',', '.'),
            'valordesconto'     => number_format((float) ($data['valordesconto'] ?? 0), 2, ',', '.'),
            'datahoraacao'      => date('d/m/Y H:i:s'),
            'datahoraprevisaoentrega' => date('d/m/Y H:i:s'),
            'numerocartao'      => '',
            'produtospedido'    => $produtospedido,
        ];
    }

    /** Traduz o retorno do controller legado (array internalResponse* ou string "OK|id") em JSON. */
    private function traduzir($resp, Request $request, $id = null)
    {
        // store legado (isApi=true) devolve ARRAY via internalResponseSuccess/Error.
        if (is_array($resp)) {
            if (($resp['status'] ?? '') === 'OK') {
                $pedidoId = (is_array($resp['data'] ?? null) ? ($resp['data']['id'] ?? null) : (is_object($resp['data'] ?? null) ? $resp['data']->id : null)) ?? $id;
                return response()->json(['data' => ['id' => $pedidoId]], $id ? 200 : 201);
            }
            return response()->json(['message' => $resp['msg'] ?? 'Erro ao processar o pedido.'], 422);
        }
        // update legado devolve string "OK|id" ou mensagem de erro.
        if (is_string($resp) && str_starts_with($resp, 'OK|')) {
            return response()->json(['data' => ['id' => (int) substr($resp, 3)]], $id ? 200 : 201);
        }
        return response()->json(['message' => is_string($resp) ? $resp : 'Erro ao processar o pedido.'], 422);
    }
}
