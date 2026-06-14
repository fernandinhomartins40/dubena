<?php

namespace App\Monitora\Http\Controllers;
//use Request;
use DB;
use Image;
use App\Rua;
use Session;
use App\Regiao;
use App\Estado;
use App\Cidade;
use App\Monitora\Models\Empresa;
use App\Bairro;
use App\Monitora\Models\EmpresasGrupo;
use App\Monitora\Models\Veiculo;
use App\Monitora\Models\Veiculotipo;
use App\Monitora\Models\Setor;
use App\Monitora\Models\User;
use App\Http\Requests;
use App\Empresaconfig;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Collection;

class EventoController extends controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public $maps = Array(
      'tempo_parado'=>180,
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
        //dd(\Auth::guard('monitora')->user()->empresas->pluck('id')->toArray());
        $empresas = \Auth::guard('monitora')->user()->empresas()->orderBy('nome_informal')->pluck('nome_informal','id')->prepend("Selecione","");
        $veiculos = Veiculo::where('grupo_id', Session::get('empresa_padrao')->grupo_id)
                           ->where('ativo', true)->get();
        $maps = (object) $this->maps;
        $maps->longitude=Session::get('empresa_padrao')->longitude;
        $maps->latitude=Session::get('empresa_padrao')->latitude;
        //dd($dados);
        return view('monitora.evento.evento', compact('empresas', 'veiculos', 'maps'));
    }
}
