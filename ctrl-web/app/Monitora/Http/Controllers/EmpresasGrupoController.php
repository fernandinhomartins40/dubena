<?php

namespace App\Monitora\Http\Controllers;
//use Request;
use Illuminate\Http\Request;
use App\Monitora\Models\EmpresasGrupo;
use App\Http\Controllers\Controller;
use App\Http\Requests;
use Session;
use DB;
use Illuminate\Support\Facades\Redirect;

class EmpresasGrupoController extends Controller
{
  public function __construct()
  {
    $this->middleware('auth');
  }
  /**
  * Display a listing of the resource.
  *
  * @return \Illuminate\Http\Response
  */
  public function index()
  {
    $empresasgrupos = EmpresasGrupo::select('id','descricao','ativo','logo')->get();
    return view('monitora.empresas.empresasgrupo',compact('empresasgrupos'));
  }

  /**
  * Show the form for creating a new resource.
  *
  * @return \Illuminate\Http\Response
  */
  public function create()
  {
    return view('monitora.empresas.empresasgrupo');
  }

  /**
  * Store a newly created resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function store(Request $request)
  {
    //$input = Request::all();
    $this->validate($request, [
      'descricao' => 'required'
    ]);
    $data = $request->only('descricao', 'ativo', 'logo');

    $img = explode( ',', $data["logo"] );
    $data["logo"] = str_replace('data:image/png;base64,', '', $data["logo"]);
    $data["logo"] = str_replace(' ', '+', $data["logo"]);

    // cria PRIMEIRO; o logo (bytea) precisa do id do registro recém-criado.
    // (antes usava $id indefinido e gravava o logo antes do create.)
    $grupo = EmpresasGrupo::create($data);

    if(count($img)>=2){
      $imga = base64_decode($img[1]);
      \App\Helpers\BlobWriter::update('empresas_grupos', $grupo->id, 'logoimg', $imga);
    }

    //return $this->index();
    return "OK|";
    //      return \Redirect::route('monitora.empresas_grupo.index')->withMessageSuccess('Grupo de Empresas cadastrado com sucesso!');
  }

  /**
  * Display the specified resource.
  *
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
  public function show($id)
  {
    $EmpresaGrupo = EmpresasGrupo::find($id);
    return view('monitora.empresas.empresasgrupo',compact('EmpresaGrupo')); //->withEmpresasGrupo($EmpresasGrupo);
  }

  /**
  * Show the form for editing the specified resource.
  *
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
  public function edit($id)
  {
    //
  }

  /**
  * Update the specified resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
  public function update(Request $request, $id)
  {
    //$bookUpdate=Request::all();
    //$request = Request::all();
    $this->validate($request, [
      'descricao' => 'required'
    ]);
    $data = $request->only('id', 'descricao', 'ativo', 'logo');

    $img = explode( ',', $data["logo"] );
    $data["logo"] = str_replace('data:image/png;base64,', '', $data["logo"]);
    $data["logo"] = str_replace(' ', '+', $data["logo"]);

    if(count($img)>=2){
      $imga = base64_decode($img[1]);
      \App\Helpers\BlobWriter::update('empresas_grupos', $id, 'logoimg', $imga);
    }

    $EmpresaGrupo = EmpresasGrupo::findOrFail($id);
    $EmpresaGrupo->update($data);

    //return view('monitora.empresas.empresasgrupo_form',compact('EmpresaGrupo'));
    //return $this->index();
    //return \Redirect::route('monitora.empresas_grupo.index')->withMessageSuccess('Grupo de Empresas atualizado com sucesso!');
    return "OK|";
  }

  /**
  * Remove the specified resource from storage.
  *
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
  public function destroy($id)
  {

    DB::beginTransaction();
    try {
      $EmpresaGrupo = EmpresasGrupo::findOrFail($id);
      $EmpresaGrupo->delete();
    } catch (\Exception $e) {
      DB::rollback();
      return \Redirect::route('monitora.empresas_grupo.index')->withMessageDanger('O registro não pôde ser excluído pois está sendo usado!');
    }
    DB::commit();
    return \Redirect::route('monitora.empresas_grupo.index');
  }
  public function form(Request $request)
  {
    // console.log($request);
    $EmpresasGrupo = EmpresasGrupo::all();
    return view('monitora.empresas.empresasgrupo',compact('EmpresasGrupo'));
  }
}
