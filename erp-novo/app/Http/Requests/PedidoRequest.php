<?php

namespace App\Http\Requests;

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
            'cliente_id' => ($criando ? 'required' : 'sometimes').'|integer|exists:clientes,id',
            'pedidosituacao_id' => ($criando ? 'required' : 'sometimes').'|integer|exists:pedidosituacoes,id',
            'pedidooperacao_id' => 'nullable|integer|exists:pedidooperacoes,id',
            'setor_id' => 'nullable|integer|exists:setores,id',
            'atendente_user_id' => 'nullable|integer|exists:users,id',
            'entregador_user_id' => 'nullable|integer|exists:users,id',
            'datahora' => 'nullable|date',
            'entrega_urgente' => 'nullable|boolean',
            'entrega_telefone' => 'nullable|string|max:30',
            'entrega_taxa' => 'nullable|numeric|gte:0',
            'entrega_troco_para' => 'nullable|numeric|gte:0',
            'observacao' => 'nullable|string',

            'itens' => ($criando ? 'required|array|min:1' : 'nullable|array'),
            'itens.*.produto_id' => 'required_with:itens|integer|exists:produtos,id',
            'itens.*.quantidade' => 'required_with:itens|numeric|gt:0',
            'itens.*.preco_unitario' => 'nullable|numeric|gte:0',
            'itens.*.desconto' => 'nullable|numeric|gte:0',
        ];
    }
}
