<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 02/08/2018
 * Time: 10:51
 */

namespace App\Api\Http\Requests;

class UserRequest extends Request
{

    public function rules()
    {
        switch ($this->request->get("action")) {
            case "getToken":
                return [
                    "password" => "required"
                ];
            case "saveUser":
                return [
                    'name'              => 'required',
                    'email'             => 'required',
                ];
            default:
                throw new \InvalidArgumentException("Action " . $this->request->get("action") . " is not valid");
        }
    }
}
