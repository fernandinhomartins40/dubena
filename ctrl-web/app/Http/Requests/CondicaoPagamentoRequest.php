<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class CondicaoPagamentoRequest extends Request
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
        if ($this->request->get('tipo') === '0' or $this->request->get('tipo') === 0) {
            $rules = $this->validateTipo0();
        } else if ($this->request->get('tipo') === '1' or $this->request->get('tipo') === 1) {
            $rules = $this->validateTipo1();
        } else if ($this->request->get('tipo') === '2' or $this->request->get('tipo') === 2) {
            $rules = $this->validateTipo2();
        } else if ($this->request->get('tipo') === '3' or $this->request->get('tipo') === 3) {
            $rules = $this->validateTipo3();
        } else {
            $rules = ['' => ''];
        }
        return $rules;
    }

    private function validateTipo0()
    {
        return [
            'descricao' => 'required',
            'tipo' => 'required',
            'dias_primeira' => 'required|numeric|min:0',
        ];
    }

    private function validateTipo1()
    {
        if ($this->request->get('_method') == 'PATCH') {
            return [
                'descricao' => 'required',
                'num_parcelas' => 'required|numeric|min:1',
            ];
        }
        return [
            'descricao' => 'required',
            'tipo' => 'required',
            'num_parcelas' => 'required|numeric|min:1'
        ];
    }

    private function validateTipo2()
    {
        if ($this->request->get('_method') == 'PATCH') {
            return [
                'descricao' => 'required',
                'taxa' => 'required',
                'dias_primeira' => 'required|numeric|min:1',
            ];
        }
        return [
            'descricao' => 'required',
            'tipo' => 'required',
            'dias_primeira' => 'required|numeric|min:1',
            'taxa' => 'required'
        ];
    }

    private function validateTipo3()
    {

        if ($this->request->get('_method') == 'PATCH') {
            return [
                'descricao' => 'required',
                'dias_primeira' => 'required|numeric|min:1',
                'taxa' => 'required'
            ];
        }
        if ($this->request->get('num_parcelas') === '1') {
            return [
                'descricao' => 'required',
                'tipo' => 'required',
                'dias_primeira' => 'required|numeric|min:1',
                'taxa' => 'required',
                'min_parcelas' => 'required|min:1',
                'max_parcelas' => 'required|min:1',
                'intervalo' => 'required|min:0|max:0'
            ];
        } else {
            return [
                'descricao' => 'required',
                'tipo' => 'required',
                'dias_primeira' => 'required|numeric|min:1',
                'taxa' => 'required',
                'min_parcelas' => 'required|min:1',
                'max_parcelas' => 'required|min:1',
                'intervalo' => 'required|min:1'
            ];
        }
    }

    function messages()
    {
        return [
            'dias_primeira.required' => 'O campo Dias para Pagamento/Dias Primeira Parcela é obrigatório',
            'min_parcelas.required' => 'O campo Min Parcelas é obrigatório',
            'max_parcelas.required' => 'O campo Max Parcelas é obrigatório',
            'num_parcelas.required' => 'O campo Nº de Parcelas é obrigatório',
            'intervalo.required' => 'O campo Invervalo Entre Parcelas é obrigatório',
        ];
    }

}
