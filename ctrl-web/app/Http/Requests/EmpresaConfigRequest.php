<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class EmpresaConfigRequest extends Request {

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
            "diastrabalhadosemana"  => "required",
            "setorprincipal_id"     => "required",
            "telacontrolakm"        => "required",
            "client_id"             => "required_with:client_secret",
            "client_secret"         => "required_with:client_id"
        ];
        if(!empty($request["empresaemitenfe"]) || !empty($request["empresaemitenfce"])){
            if(isset($request["pedidoemitenfce"]) && !empty($request["empresaemitenfce"])){
                $nfrules = [
                    "empresaemitenfce" => "required"
                ];
                $rules = array_merge($rules,$nfrules);
            }
        }
        if ($this->request->get("emailremetente")) {
            $erules = [
                "emailremetente"    => "email",
                "emailservidorsmtp" => "required",
                "emailportasmtp"    => "required",
                "emailassunto"      => "required",
                "emailcorpo"        => "required",
                "emailnomeremente"  => "required",
            ];
            $rules = array_merge($rules, $erules);
        }
        return $rules;
    }

    public function messages(){
        return [
            "empresaemitenfce.required"     => "Sua empresa deve emitir NFC-e para este campo estar marcado.",
            "emailremetente.email"          => "Por favor, insira um email válido.",
            "emailservidorsmtp.required"    => "E-Mail Remetente informado, então é necessario informar também o servidor.",
            "emailportasmtp.required"       => "E-Mail Remetente informado, então é necessario informar também a porta.",
            "emailassunto.required"         => "E-Mail Remetente informado, então é necessario informar também o assunto.",
            "emailcorpo.required"           => "E-Mail Remetente informado, então é necessario informar também o corpo.",
            "emailnomeremente.required"     => "E-Mail Remetente informado, então é necessario informar também o nome do remetente.",
        ];
    }

}


