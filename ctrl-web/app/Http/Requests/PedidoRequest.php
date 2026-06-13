<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class PedidoRequest extends Request
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function rules()
    {

        return [
            'cliente_id' => 'required',
            'entreganumero' => 'required',
            'condicaopagamento_id' => 'required',
            'pedidooperacao_id' => 'required',
            'pedidosituacao_id' => 'required',
            'valorvenda' => 'required',
            'entregasetor_id' => 'required',
            'colaborador_id' => 'required',
            ];
    }
    public function messages()
    {
        return [
            'entreganumero.required' => 'O campo Nº é obrigatório.',
            'condicaopagamento_id.required' => 'O campo Condição de Pagamento é obrigatório.',
            'pedidooperacao_id.required' => 'O campo Operação é obrigatório.',
            'pedidosituacao_id.required' => 'O campo Status é obrigatório.',
            'valorvenda.required' => 'Adicione produtos para finalizar o pedido.',
            'entregasetor_id.required' => 'O campo Setor é obrigatório.',
            'colaborador_id.required' => 'O campo Colaborador é obrigatório.',
        ];
    }

}
