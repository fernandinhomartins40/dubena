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
        $empresaId = Session::get('empresa_padrao')->id;

        // FASE M1 (fix Postgres): no update, id vazio fazia a regra unique virar
        // "...,empresabems,descricao,,id,..." → SQL `"id" <> ''`, que o Postgres
        // rejeita em coluna integer (SQLSTATE 22P02 → 500). Normaliza p/ 'NULL'
        // (Laravel interpreta como "ignore nada"). Mesmo fix do ClienteRequest.
        $except = $this->request->get('id');
        if ($except === null || $except === '') {
            $except = 'NULL';
        }

        return [
            'descricao'              => 'required|unique:empresabems,descricao,' . $except . ',id,empresa_id,' . $empresaId,
            'numeroserie'            => 'required|unique:empresabems,numeroserie,' . $except . ',id,empresa_id,' . $empresaId,
            'datacadastro'           => 'required',
            'valororiginal'          => 'required',
            'depreciacaoporcentagem' => 'required',
            'tipodepreciacao'        => 'required',
            'depreciacaodias'        => 'required',
        ];
    }

    function messages()
    {
        return [
        ];
    }

}
