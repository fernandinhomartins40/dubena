<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\Session;

class EmpresaBensRequest extends Request
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
        if ($this->method === 'POST') {
            return [
                'descricao' => 'required|unique:empresabems,descricao,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
                'numeroserie' => 'required|unique:empresabems,numeroserie,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
                'datacadastro' => 'required',
                'valororiginal' => 'required',
                'depreciacaoporcentagem' => 'required',
                'tipodepreciacao' => 'required',
                'depreciacaodias' => 'required'
            ];
        } else {
            return [
                'descricao' => 'required|unique:empresabems,descricao,' . $this->request->get('id') . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
                'numeroserie' => 'required|unique:empresabems,numeroserie,' . $this->request->get('id') . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
                'datacadastro' => 'required',
                'valororiginal' => 'required',
                'depreciacaoporcentagem' => 'required',
                'tipodepreciacao' => 'required',
                'depreciacaodias' => 'required'];
        }
    }

    function messages()
    {
        return [
        ];
    }

}
