<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 02/08/2018
 * Time: 10:51
 */

namespace App\Api\Http\Requests;


class ProdutoRequest extends Request
{

    public function rules()
    {
        return [
            'descricao' => 'required'
        ];
    }
}
