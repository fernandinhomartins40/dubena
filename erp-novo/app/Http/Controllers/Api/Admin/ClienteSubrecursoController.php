<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteInteracao;
use App\Models\Cliente\ClientePreco;
use App\Models\Pedido\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sub-recursos do cliente (N2): interações, convênio + dependentes, preços, histórico.
 * Todos escopados pela relação do cliente (empresa via BelongsToTenant).
 */
class ClienteSubrecursoController extends Controller
{
    use AutorizaPorPermissao;

    // ── Interações ──
    public function interacoes(Request $request, int $clienteId): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');
        $cliente = Cliente::query()->findOrFail($clienteId);

        // A aba lê `datahora`, `tipo` e `situacao` (rótulos). Devolver o model
        // cru entregava `created_at`/`tipo_id`/`situacao_id`, e a tela mostrava
        // a linha em branco — dado presente, nome divergente.
        $interacoes = $cliente->interacoes()
            ->with(['tipo:id,descricao', 'situacao:id,descricao'])
            ->latest()
            ->get()
            ->map(fn (ClienteInteracao $i) => [
                'id' => $i->id,
                'datahora' => $i->created_at?->toDateTimeString(),
                'tipo_id' => $i->tipo_id,
                'situacao_id' => $i->situacao_id,
                'tipo' => $i->tipo?->descricao,
                'situacao' => $i->situacao?->descricao,
                'descricao' => $i->descricao,
                'acao' => $i->acao,
            ])->all();

        return response()->json(['data' => $interacoes]);
    }

    public function addInteracao(Request $request, int $clienteId): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');
        $cliente = Cliente::query()->findOrFail($clienteId);

        $dados = $request->validate([
            'tipo_id' => 'nullable|integer|exists:clientecontatotipos,id',
            'situacao_id' => 'nullable|integer|exists:clientecontatosituacoes,id',
            'descricao' => 'required|string',
            'acao' => 'nullable|string|max:255',
        ]);
        $dados['user_id'] = $request->user()->id;

        return response()->json(['data' => $cliente->interacoes()->create($dados)], 201);
    }

    public function delInteracao(Request $request, int $clienteId, int $subId): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');
        $cliente = Cliente::query()->findOrFail($clienteId);
        $cliente->interacoes()->whereKey($subId)->delete();

        return response()->json(['message' => 'Interação excluída.']);
    }

    // ── Convênio (dados no próprio cliente) + dependentes ──
    public function convenio(Request $request, int $clienteId): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');
        $cliente = Cliente::query()->findOrFail($clienteId);

        // Envelope uniforme {data} (API-1) — antes devolvia os campos no topo.
        return response()->json([
            'data' => [
                'convenio' => $cliente->convenio,
                'convenio_ativo' => $cliente->convenio_ativo,
                'convenio_limite' => $cliente->convenio_limite !== null ? (float) $cliente->convenio_limite : null,
                'dependentes' => $cliente->dependentes()->get(),
            ],
        ]);
    }

    public function salvarConvenio(Request $request, int $clienteId): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');
        $cliente = Cliente::query()->findOrFail($clienteId);

        $dados = $request->validate([
            'convenio' => 'nullable|boolean',
            'convenio_ativo' => 'nullable|boolean',
            'convenio_limite' => 'nullable|numeric',
        ]);
        $cliente->update($dados);

        return response()->json(['message' => 'Convênio atualizado.']);
    }

    public function addDependente(Request $request, int $clienteId): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');
        $cliente = Cliente::query()->findOrFail($clienteId);

        $dados = $request->validate([
            'nome' => 'required|string|max:200',
            'parentesco_id' => 'nullable|integer',
            'ativo' => 'nullable|boolean',
        ]);

        return response()->json(['data' => $cliente->dependentes()->create($dados)], 201);
    }

    public function delDependente(Request $request, int $clienteId, int $depId): JsonResponse
    {
        $this->autorizar($request, 'cliente.edit');
        $cliente = Cliente::query()->findOrFail($clienteId);
        $cliente->dependentes()->whereKey($depId)->delete();

        return response()->json(['message' => 'Dependente excluído.']);
    }

    // ── Preços por produto ──
    public function precos(Request $request, int $clienteId): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');
        $cliente = Cliente::query()->findOrFail($clienteId);

        // A aba mostra o NOME do produto; o model cru só tem `produto_id`.
        $precos = $cliente->precos()
            ->with('produto:id,descricao')
            ->get()
            ->map(fn (ClientePreco $p) => [
                'id' => $p->id,
                'produto_id' => $p->produto_id,
                'produto' => $p->produto?->descricao,
                'preco' => $p->preco !== null ? (float) $p->preco : null,
                'desconto' => $p->desconto !== null ? (float) $p->desconto : null,
            ])->all();

        return response()->json(['data' => $precos]);
    }

    // ── Histórico de compras (deriva de Pedidos/N4) ──
    public function historico(Request $request, int $clienteId): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');
        // findOrFail é escopado por tenant (Cliente usa BelongsToTenant): garante
        // que o cliente é da empresa ativa antes de listar os pedidos dele.
        $cliente = Cliente::query()->findOrFail($clienteId);

        $limite = (int) $request->query('limite', 30);
        $limite = max(1, min($limite, 100));

        // Pedido também é tenant-scoped: o histórico só enxerga pedidos da empresa ativa.
        $pedidos = Pedido::query()
            ->where('cliente_id', $cliente->id)
            ->with(['situacao:id,descricao,efeito'])
            ->withCount('itens')
            ->orderByDesc('datahora')
            ->limit($limite)
            ->get(['id', 'pedidosituacao_id', 'datahora', 'valor_venda', 'valor_desconto']);

        // A aba Histórico lê `id`, `datahora` e `valorvenda`. Os nomes daqui
        // (`pedido_id`, `data`, `valor_venda`) não casavam com NENHUM deles: a
        // tabela listava a quantidade certa de linhas, mas com "#" sem número,
        // "—" na data e R$ 0,00 em todas. Os nomes do contrato são os que a tela
        // consome; os demais seguem juntos para não quebrar outro consumidor.
        $data = $pedidos->map(fn (Pedido $p) => [
            'id' => $p->id,
            'pedido_id' => $p->id,
            'datahora' => optional($p->datahora)->toDateTimeString(),
            'data' => optional($p->datahora)->toDateTimeString(),
            'situacao' => $p->situacao?->descricao,
            'efeito' => $p->situacao?->efeito?->value,
            'itens' => $p->itens_count,
            'valorvenda' => (float) $p->valor_venda,
            'valor_venda' => (float) $p->valor_venda,
            'valor_desconto' => (float) $p->valor_desconto,
        ])->all();

        return response()->json(['data' => $data]);
    }
}
