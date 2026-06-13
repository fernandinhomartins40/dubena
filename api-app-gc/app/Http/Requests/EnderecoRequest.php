<?php

/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 19/07/2018
 * Time: 17:08
 */

namespace App\Http\Requests;

class EnderecoRequest extends Request
{

    public function rules()
    {
        return [
            'numero'            => 'required',
            'titulo'            => 'required|min:3|max:95',
            'rua'               => 'required|min:3|max:95',
            'cliente_id'        => 'required',
            'bairro'            => 'required|min:3|max:95',
            'uf'                => 'required|min:2|max:2',
            'cidade'            => 'required|min:3|max:95'
        ];
    }

}