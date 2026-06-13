<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class EstoqueTransferenciasRequest extends Request
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
        return [
            'datahora' => 'required',
            'origemsetor_id' => 'required',
            'destinosetor_id' => 'different:origemsetor_id',
        ];
    }

    function messages()
    {
        return [
            'destinosetor_id.different' => 'O setor de origem não pode ser o mesmo de destino.'
        ];
    }

}
