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

class RastreamentoController extends controller
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
        //dd(\Auth::user()->empresas->pluck('id')->toArray());
        $qtmaxpagina = 8;
        $dados = Array();
        $setorscoll = new Collection();
        $veiculoscoll = new Collection();
        foreach(\Auth::user()->empresas as $empresa){
            //if($empresa->id==2)
            //dd($empresa->activesetors);
            $countsetors = floor(count($empresa->activesetors)/$qtmaxpagina) + ((count($empresa->activesetors)%$qtmaxpagina)>0?1:0);
            $countveiculos = floor(count($empresa->activeveiculos)/$qtmaxpagina) + ((count($empresa->activeveiculos)%$qtmaxpagina)>0?1:0);
            if($countsetors > 0 || $countveiculos > 0){
                $emp = Array();
                $emp["id"] = $empresa->id;
                $emp["nome_informal"] = $empresa->nome_informal;
                if($countsetors > 0){
                    $setorspagina = Array();
                    $setorsmeiapagina = Array();
                    $setors = Array();
                    $j=0;
                    $k=0;
                    foreach($empresa->activesetors as $setor){
                        array_push($setors, [$setor->id, $setor->descricao]);
                        $j++;
                        $k++;
                        if($k==$qtmaxpagina/2){
                            $k=0;
                            array_push($setorsmeiapagina, $setors);
                            $setors = Array();
                        }
                        if($j==$qtmaxpagina){
                            $j=0;
                            array_push($setorspagina, $setorsmeiapagina);
                            $emp["setorspaginas"] = $setorspagina;
                            $setorsmeiapagina = Array();
                        }
                        //echo $j."<br>";
                    }
                    if($k!=0){
                        array_push($setorsmeiapagina, $setors);
                    }
                    if($j!=0){
                        array_push($setorspagina, $setorsmeiapagina);
                        $emp["setorspaginas"] = $setorspagina;
                        //dd('aqui');
                    }
                }
                $veiculos = Array();
                if($countveiculos > 0){
                    $veiculospagina = Array();
                    $veiculosmeiapagina = Array();
                    $veiculos = Array();
                    $j=0;
                    $k=0;
                    foreach($empresa->activeveiculos as $veiculo){
                        array_push($veiculos, [$veiculo->id, $veiculo->descricao, $veiculo->veiculotipo->imagem_parado]);
                        $j++;
                        $k++;
                        if($k==$qtmaxpagina/2){
                            $k=0;
                            array_push($veiculosmeiapagina, $veiculos);
                            $veiculos = Array();
                        }
                        if($j==$qtmaxpagina){
                            $j=0;
                            array_push($veiculospagina, $veiculosmeiapagina);
                            $emp["veiculospaginas"] = $veiculospagina;
                            $veiculosmeiapagina = Array();
                        }
                    }
                    if($k!=0){
                        array_push($veiculosmeiapagina, $veiculos);
                    }
                    if($j!=0){
                        array_push($veiculospagina, $veiculosmeiapagina);
                        $emp["veiculospaginas"] = $veiculospagina;
                    }
                }
                array_push($dados, $emp);
                $setorscoll = $setorscoll->merge($empresa->activesetors);
                $veiculoscoll = $veiculoscoll->merge($empresa->activeveiculos);
            }
        }
        $veiculos = Veiculo::where('grupo_id', Session::get('empresa_padrao')->grupo_id)
            ->where('ativo', true)->get();
        //$veiculos = $veiculoscoll;
        $setors = $setorscoll; //Setor::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->get();
        $maps = (object) $this->maps;
        $maps->longitude=Session::get('empresa_padrao')->longitude;
        $maps->latitude=Session::get('empresa_padrao')->latitude;
        $maps->tempo_parado=Session::get('empresa_padrao')->tempoparado;
        $maps->tempo_parado=Session::get('empresa_padrao')->tempoparado;
        $maps->tempo_refresh=Session::get('empresa_padrao')->temporefresh;
        $maps->keygooglemaps=Session::get('empresa_padrao')->keygooglemaps;
        if($maps->keygooglemaps==null){
            $maps->keygooglemaps=Session::get('config')->keygooglemaps;
        }
        //dd($maps);
        return view('rastreamento.rastreamento', compact('qtmaxpagina', 'dados', 'veiculos', 'setors', 'maps'));
    }
}
