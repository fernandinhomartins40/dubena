<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class ValeGasRequest extends Request {

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {

        $prevenda = $this->request->get('prevenda');

        $generalrules = [
            'cliente_id'    => 'required',
            'produto_id_hd' => 'required',
            'quantidade_hd' => 'required|min:1|max:300'
        ];

        if ($prevenda != 1) {
            $generalrulesprevenda = [
                'valorunitario'         => 'required',
                'condicaopagamento_id'  => 'required'
            ];

            $rules = array_merge($generalrules, $generalrulesprevenda);
            return $rules;
        } else {
            return $generalrules;
        }
    }
    
    public function messages(){
        return [
            'cliente_id.required'       => 'O campo Ponto de Venda é obrigatório.',
            'produto_id_hd.required'    => 'O Produto é obrigatório.',
            'quantidade_hd.required'    => 'A Quantidade é obrigatório.'
        ];
    }

}
