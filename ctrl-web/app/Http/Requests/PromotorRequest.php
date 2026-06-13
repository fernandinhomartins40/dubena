<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromotorRequest extends FormRequest
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
        $data = $this->request->alL();

        $isAusente = isset($data["ausente"]) && $data["ausente"];

        if ($isAusente) {
            return [
                "rua_id"    => "required",
                "numero"    => "required",
                "bairro_id"    => "required",
                "setor_id"  => "required"
            ];
        }

        return [];
    }
}
