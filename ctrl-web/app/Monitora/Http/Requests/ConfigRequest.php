<?php

namespace App\Monitora\Http\Requests;
use App\Monitora\Http\Requests\Request;

class ConfigRequest extends Request {

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return true;
    }

    public function rules() {
        $request = $this->request->all();
        $rules = [
            "urlsistemaweb" => "required",
        ];
        return $rules;
    }

    public function messages(){
        return [
        ];
    }

}


