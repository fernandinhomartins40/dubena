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
use App\Http\Requests;
use App\Config;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class Empresacontroller extends controller {

  protected $msgsValidacao = array(
    'cnpj.required' => 'O campo CNPJ é obrigatório.',
    'cnpj.unique' => 'O CNPJ já esta em uso nesse grupo.',
    );

  /**
  * Display a listing of the resource.
  *
  * @return \Illuminate\Http\Response
  */
  public function index() {
    $Empresas = Empresa::all();
    return view('empresas.empresas', compact('Empresas'));
  }

  /**
  * Show the form for creating a new resource.
  *
  * @return \Illuminate\Http\Response
  */
  public function create() {
    $grupos = EmpresasGrupo::where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id');
    return view('empresas.empresa_form', compact('grupos'));
  }

  public function change($id) {
    $empresa = Empresa::find($id);
    $config = Config::all()->first();
    Session::put('empresa_padrao', $empresa);
    return redirect()->intended('home');
  }

  /**
  * Store a newly created resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function store(Request $request) {
    $rules = [
    'razao_social' => 'required',
    'nome_fantasia' => 'required',
    'cnpj' => 'unique:empresas,cnpj,null,id,ativo,1,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
    ];
    $this->validate($request, $rules, $this->msgsValidacao);
    $data = $request->all();
    if(!isset($data['ativo'])){
      $data['ativo'] = '0';
    }
	$img = explode( ',', $data["logo"] );
    $data["logo"] = str_replace('data:image/png;base64,', '', $data["logo"]);
    $data["logo"] = str_replace(' ', '+', $data["logo"]);
    $empresa = Empresa::create($data);
	if(count($img)>=2){
		$imga = base64_decode($img[1]);
		$teste = DB::table('empresas')->whereId($empresa->id)->updateLob(
		array(),
		array('logoimg'=>$imga)
		);
	}
    return \Redirect::route('empresa.index')->withMessageSuccess('Empresa cadastrada com sucesso!');
  }

  /**
  * Display the specified resource.
  *
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
  public function show($id) {
    $Empresa = Empresa::find($id);
    //dd($Empresa->logo);
    $grupos = EmpresasGrupo::where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id');
    $show = true;
    return view('empresas.empresa_form', compact('Empresa', 'show', 'grupos'));
  }

  /**
  * Show the form for editing the specified resource.
  *
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
  public function edit($id) {
    $Empresa = Empresa::find($id);
    $grupos = EmpresasGrupo::where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id');
    return view('empresas.empresa_form', compact('Empresa', 'grupos'));
  }

  /**
  * Update the specified resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
  public function update(Request $request, $id) {
    $rules = [
    'razao_social' => 'required',
    'nome_fantasia' => 'required',
    'cnpj' => 'unique:empresas,cnpj,' . $id . ',id,ativo,1,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
    ];
    $this->validate($request, $rules, $this->msgsValidacao);
    $data = $request->all();
    if(!isset($data['ativo'])){
      $data['ativo'] = '0';
    }
	$img = explode( ',', $data["logo"] );
    $data["logo"] = str_replace('data:image/png;base64,', '', $data["logo"]);
    $data["logo"] = str_replace(' ', '+', $data["logo"]);
    $Empresa = Empresa::findOrFail($id);
    $Empresa->update($data);
    Session::put('empresa_padrao', $Empresa);
	if(count($img)>=2){
		$imga = base64_decode($img[1]);
		$teste = DB::table('empresas')->whereId($id)->updateLob(
		array(),
		array('logoimg'=>$imga)
		);
	}
    return \Redirect::route('empresa.index')->withMessageSuccess('Empresa atualizada com sucesso!');
  }

  /**
  * Remove the specified resource from storage.
  *
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
  public function destroy($id) {

    DB::beginTransaction();
    try {
      $Empresa = Empresa::findOrFail($id);
      $Empresa->delete();
    } catch (\Exception $e) {
      DB::rollback();
      return \Redirect::route('empresa.index')->withMessageDanger('<br /><br />O registro não pôde ser excluído pois está sendo usado!');
    }
    DB::commit();
    return \Redirect::route('empresa.index');
  }

  public function form(Request $request) {
    $EmpresasGrupo = EmpresasGrupo::all();
    return view('empresas.empresasgrupo_form', compact('EmpresasGrupo'));
  }

  public function carregaempresa($id) {
    $empresa = Empresa::findOrFail($id);
    return $empresa;
  }

}
