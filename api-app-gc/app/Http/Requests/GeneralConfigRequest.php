<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 25/07/2018
 * Time: 14:31
 */

namespace App\Http\Requests;

class GeneralConfigRequest extends Request
{

    public function rules()
    {
        return [
            'keygooglemaps' => 'required',
        ];
    }
}