<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente\Cliente;
use App\Models\Pedido\Pedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sub-recursos do cliente (N2): interações, convênio + dependentes, preços, histórico.
 * Todos escopados pela relação do cliente (empresa via BelongsToTenant).
 */
class ClienteSubrecursoController extends Controller
{
    // ── Interações ──
    public function interacoes(Request $request, int $clienteId): JsonResponse
    {
        $this->autorizar($request, 'cliente.view');
        $cliente = Cliente::query()->findOrFail($clienteId);

        return response()->json(['data' => $cliente->interacoes()->latest()->get()]);
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

        return response()->json([
            'convenio' => $cliente->convenio,
            'convenio_ativo' => $cliente->convenio_ativo,
            'convenio_limite' => $cliente->convenio_limite !== null ? (float) $cliente->convenio_limite : null,
            'dependentes' => $cliente->dependentes()->get(),
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

        return response()->json(['data' => $cliente->precos()->get()]);
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

        $data = $pedidos->map(fn (Pedido $p) => [
            'pedido_id' => $p->id,
            'data' => optional($p->datahora)->toDateTimeString(),
            'situacao' => $p->situacao?->descricao,
            'efeito' => $p->situacao?->efeito?->value,
            'itens' => $p->itens_count,
            'valor_venda' => (float) $p->valor_venda,
            'valor_desconto' => (float) $p->valor_desconto,
        ])->all();

        return response()->json(['data' => $data]);
    }

    private function autorizar(Request $request, string $chave): void
    {
        abort_unless($request->user()->temPermissao($chave), 403, 'Sem permissão.');
    }
}
