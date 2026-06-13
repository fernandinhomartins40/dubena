<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Setorcolaboradores;

class SetorcolaboradoresController extends Controller
{

    function buscaAjax($setor_id)
    {
        return Setorcolaboradores::where([
                            ['setors.ativo', 1],
                            ['setors.id', $setor_id]
                        ])
                        ->join('setors', 'setors.id', '=', 'setorcolaboradores.setor_id')
                        ->join('colaboradors', 'colaboradors.id', '=', 'setorcolaboradores.colaborador_id')
                        ->get();
    }

}
