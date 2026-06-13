<?php

namespace App\Http\Requests;

class FeriadoRequest extends Request
{

    public function rules()
    {
        $rules = [
            'descricao' => 'required',
            'data'      => 'required'
        ];
        if ($this->request->get("_method_") === "POST") {
            if ($this->request->get("ativo")) {
                $rules['data'] = 'sometimes|required|unique:feriados,data,NULL,id,ativo,1';
            }
        } else {
            if ($this->request->get("ativo")) {
                $rules['data'] = 'sometimes|required|unique:feriados,data,' . $this->request->get("id") . ',id,ativo,1';
            }
        }
        return $rules;
    }
}
