<?php

namespace App\Http\Requests;

class CondicaoPagamentoRequest extends Request
{

    public function rules()
    {
        return [
            "descricao" => "required",
            "tipo"      => "required"
        ];
    }
}
