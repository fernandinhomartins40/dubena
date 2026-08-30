<?php

namespace App\Http\Requests;

use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoOperacao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Rules\ExisteNoTenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de pedido. Itens como array ANINHADO de DTO nomeado.
 */
class PedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $criando = $this->isMethod('post');

        return [
            // `ExisteNoTenant` no lugar de `exists:tabela,id`: a regra nativa
            // valida contra a tabela inteira, e um pedido da empresa A era
            // aceito com `cliente_id` da empresa B — inclusive entre tenants.
            'cliente_id' => [$criando ? 'required' : 'sometimes', 'integer', new ExisteNoTenant(Cliente::class)],
            'pedidosituacao_id' => [$criando ? 'required' : 'sometimes', 'integer', new ExisteNoTenant(PedidoSituacao::class)],
            'pedidooperacao_id' => ['nullable', 'integer', new ExisteNoTenant(PedidoOperacao::class)],
            'setor_id' => ['nullable', 'integer', new ExisteNoTenant(Setor::class)],
            'atendente_user_id' => 'nullable|integer|exists:users,id',
            'entregador_user_id' => 'nullable|integer|exists:users,id',
            'datahora' => 'nullable|date',
            'entrega_urgente' => 'nullable|boolean',
            'entrega_telefone' => 'nullable|string|max:30',
            'entrega_taxa' => 'nullable|numeric|gte:0',
            'entrega_troco_para' => 'nullable|numeric|gte:0',
            'observacao' => 'nullable|string',

            'itens' => ($criando ? 'required|array|min:1' : 'nullable|array'),
            'itens.*.produto_id' => ['required_with:itens', 'integer', new ExisteNoTenant(Produto::class)],
            'itens.*.quantidade' => 'required_with:itens|numeric|gt:0',
            'itens.*.preco_unitario' => 'nullable|numeric|gte:0',
            'itens.*.desconto' => 'nullable|numeric|gte:0',
        ];
    }
}
