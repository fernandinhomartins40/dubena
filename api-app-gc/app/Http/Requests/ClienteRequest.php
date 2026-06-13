<?php

namespace App\Http\Requests;

class ClienteRequest extends Request
{

    public function rules()
    {
        return [
            'telefone'              => 'required',
            // 'nome'                  => 'required|min:3|minwords:2|regex:/^[A-zÀ-ú\s]+$/',
            'nome'                  => 'required',
            'pushregistration_id'   => 'required|min:1'
        ];
    }
}
