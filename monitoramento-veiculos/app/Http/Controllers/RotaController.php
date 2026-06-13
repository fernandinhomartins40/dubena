<?php

namespace App\Http\controllers;

//use Request;
use DB;
use Image;
use App\Rua;
use Session;
use App\Regiao;
use App\Estado;
use App\Cidade;
use App\Empresa;
use App\Bairro;
use App\EmpresasGrupo;
use App\Veiculo;
use App\Veiculotipo;
use App\Setor;
use App\User;
use App\Http\Requests;
use App\Empresaconfig;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Collection;

class RotaController extends controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public $maps = Array(
      'tempo_parado'=>60,
      'latitude'=>0,
      'longitude'=>0,
      'start_zoom'=>15,
      'control_large'=>"false",
  		'control_small'=>"true",
  		'control_type'=> "true",
  		'control_zoom'=>"false",
  		'control_scale'=>"true",
  		'overview'=>"false",
      'traffic_info'=>"false"
    );

    public function index()
    {
        $empresas = \Auth::user()->empresas()->orderBy('nome_informal')->pluck('nome_informal','id')->prepend("Selecione","");

        $veiculos = Veiculo::where('grupo_id', Session::get('empresa_padrao')->grupo_id)
                           ->where('ativo', true)->get();
        $maps = (object) $this->maps;
        $maps->longitude=Session::get('empresa_padrao')->longitude;
        $maps->latitude=Session::get('empresa_padrao')->latitude;
        $maps->keygooglemaps=Session::get('empresa_padrao')->keygooglemaps;
        if($maps->keygooglemaps==null){
            $maps->keygooglemaps=Session::get('config')->keygooglemaps;
        }
        return view('rota.rota', compact('empresas', 'veiculos', 'maps'));
    }
}
