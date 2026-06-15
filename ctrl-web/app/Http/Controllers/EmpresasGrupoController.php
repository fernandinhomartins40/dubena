<?php

namespace App\Http\Controllers;

//use Request;
use Illuminate\Http\Request;
use App\EmpresasGrupo;
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
  public function index(EmpresasGrupo $empg)
  {
    $this->authorize('view',$empg);
    $empresasgrupos = EmpresasGrupo::select('id','descricao','ativo','logo')->get();
    return view('empresas.empresasgrupo',compact('empresasgrupos'));
  }

  /**
  * Show the form for creating a new resource.
  *
  * @return \Illuminate\Http\Response
  */
  public function create()
  {
    return view('empresas.empresasgrupo');
  }

  /**
  * Store a newly created resource in storage.
  *
  * @param  \Illuminate\Http\Request  $request
  * @return \Illuminate\Http\Response
  */
  public function store(Request $request, EmpresasGrupo $empg)
  {
    //$input = Request::all();
    $this->authorize('create',$empg);
    $this->validate($request, [
      'descricao' => 'required'
    ]);
    $data = $request->only('descricao', 'ativo', 'logo');

    $img = explode( ',', $data["logo"] );
    $data["logo"] = str_replace('data:image/png;base64,', '', $data["logo"]);
    $data["logo"] = str_replace(' ', '+', $data["logo"]);

    $grupo = EmpresasGrupo::create($data);
    if(count($img)>=2){
      $imga = base64_decode($img[1]);
      \App\Helpers\BlobWriter::update('empresas_grupos', $grupo->id, 'logoimg', $imga);
    }
    
    //return $this->index();
    return "OK|";
    //      return \Redirect::route('empresas_grupo.index')->withMessageSuccess('Grupo de Empresas cadastrado com sucesso!');
  }

  /**
  * Display the specified resource.
  *
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
  public function show($id,EmpresasGrupo $empg)
  {
    $this->authorize('view',$empg);
    $EmpresaGrupo = EmpresasGrupo::find($id);
    return view('empresas.empresasgrupo',compact('EmpresaGrupo')); //->withEmpresasGrupo($EmpresasGrupo);
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
  public function update(Request $request, $id,EmpresasGrupo $empg)
  {
    $this->authorize('update',$empg);
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

    //return view('empresas.empresasgrupo_form',compact('EmpresaGrupo'));
    //return $this->index();
    //return \Redirect::route('empresas_grupo.index')->withMessageSuccess('Grupo de Empresas atualizado com sucesso!');
    return "OK|";
  }

  /**
  * Remove the specified resource from storage.
  *
  * @param  int  $id
  * @return \Illuminate\Http\Response
  */
  public function destroy($id,EmpresasGrupo $empg)
  {
    $this->authorize('delete',$empg);
    DB::beginTransaction();
    try {
      $EmpresaGrupo = EmpresasGrupo::findOrFail($id);
      $EmpresaGrupo->delete();
    } catch (\Exception $e) {
      DB::rollback();
      return \Redirect::route('empresas_grupo.index')->withMessageDanger('O registro não pôde ser excluído pois está sendo usado!');
    }
    DB::commit();
    return \Redirect::route('empresas_grupo.index');
  }
  public function form(Request $request)
  {
    // console.log($request);
    $EmpresasGrupo = EmpresasGrupo::all();
    return view('empresas.empresasgrupo',compact('EmpresasGrupo'));
  }
}
