<?php

namespace App\Http\Requests;

use Session;
use App\Http\Requests\Request;

class ManutencaoRequest extends request{
    
    
    public function authorize(){
        return true;
    }
    
    public function rules(){
        $alerta = $this->request->get('alertaantesoleo');
        
        if($alerta == 1){
            $rules = ['kmalertaantesoleo' => 'required'];
            return $rules;
        }else{
            $rules = [];
            return $rules;
        }
    }   
    
}