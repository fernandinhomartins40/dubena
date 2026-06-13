<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class NfImpostoRequest extends Request
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            "nfoperacao_id"     => "required",
            "grupofiscal_id"    => "required",
            "nfcofins_id"       => "required",
            "nfcofinsaliq"      => "required",
            "nfcofinsbase"      => "required",
            "pfnfcofins_id"     => "required",
            "pfnfcofinsaliq"    => "required",
            "pfnfcofinsbase"    => "required",
            "nficms_id_pj"      => "required",
            "pfnficms_id"       => "required",
            "nfpis_id"          => "required",
            "nfpisaliq"         => "required",
            "nfpisbase"         => "required",
            "pfnfpis_id"        => "required",
            "pfnfpisaliq"       => "required",
            "pfnfpisbase"       => "required"
        ];
        return $rules;
    }

    function messages()
    {
        return [
            "piscofinsnatreceita.required" => "O campo Natureza Receita é obrigatório caso o campo Gerar Crédito estiver marcado.",
        ];
    }
}
