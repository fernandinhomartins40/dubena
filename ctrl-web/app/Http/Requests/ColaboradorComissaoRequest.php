<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class ColaboradorComissaoRequest extends Request
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
        $rules = [];
        if ($this->request->get('_method') == "PATCH") {
            if ($this->request->get('tonelagem') == '1') {
                $rules = [
                    'datainicio'            => 'required',
                    'datafim'               => 'required',
                    'produto_id'            => 'required',
                ];
            } else {
                $rules = [
                    'datainicio'            => 'required',
                    'datafim'               => 'required',
                    'produto_id'            => 'required',
                    'condicaopagamento_id'  => 'required'
                ];
            }
        } else {
            if ($this->request->get('tonelagem') == '1') {
                $rules = [
                    'datainicio'            => 'required',
                    'datafim'               => 'required',
                    'produto_id'            => 'required',
                ];
            } else {
                $rules = [
                    'datainicio'            => 'required',
                    'datafim'               => 'required',
                    'setor_id'              => 'required|not_in:-1',
                    'produto_id'            => 'required',
                    'condicaopagamento_id'  => 'required',
                ];
            }
        }

        if (! $this->request->get('replicar') && $this->request->get('_method') != "PATCH") {
            $rules = array_merge($rules, ['colaborador_id' => 'required']);
        }

        return $rules;
    }

    function messages()
    {
        return [
        ];
    }

}
