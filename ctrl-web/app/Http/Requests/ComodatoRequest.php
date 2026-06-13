<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class ComodatoRequest extends Request
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
      'tipo' => 'required',
      'cliente_id' => 'required|not_in:0',
      'produtos' => 'required|min:3|string',
      'datacontrato' => 'required',
      'datavencimento' => 'required'
    ];
  }

  public function messages(){
    return [
      'produtos.min' => 'Você deve adicionar pelo menos um produto.'
    ];
  }

}
