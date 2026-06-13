<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 02/08/2018
 * Time: 10:51
 */

namespace App\Http\Requests;

class PedidoRequest extends Request
{

    public function rules()
    {
        return [
            "condicaopagamento_id"  => "required",
            "cliente_id"            => "required",
            "produtosJson"          => "required",
            "endereco_id"           => "required",
            "datahoraprevisao"      => "required"
        ];
    }
}