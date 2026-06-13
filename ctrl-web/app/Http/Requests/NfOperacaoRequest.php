<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class NfOperacaoRequest extends Request
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
        $rules = [
            'descricao' => 'required',
            'descricaofiscal' => 'required',
            'cfopie' => 'numeric|min:0',
            'cfop' => 'required|numeric|min:0',
            'tiponf' => 'required',
            'movimentaestoque' => 'required',
            'movimentafinanceiro' => 'required',
            'cadastronf' => 'required',
            'aparecetela' => 'required'
        ];
        
        return $rules;
    }

    function messages()
    {
        return [
            'inputListaProdutosTable.required' => 'Ao menos um produto deve ser adicionado para a transferência.',
            'destinosetor_id.different' => 'O setor de origem não pode ser o mesmo de destino.'
        ];
    }

}
