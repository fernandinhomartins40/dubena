<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class SetorRequest extends Request
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'descricao' => 'required',
            'cidade_id' => 'required',
            'bairro_id' => 'required',
            'rua_id' => 'required',
            'cep' => 'required',
            'estoqueproprio' => 'required',
            'numero' => 'required'
        ];
    }

    function messages()
    {
        return [
            'inputListaProdutosTable.required' => 'Ao menos um produto deve ser adicionado para a requisição.',
        ];
    }

}
