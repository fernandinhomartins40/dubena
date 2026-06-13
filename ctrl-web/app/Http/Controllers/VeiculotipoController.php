<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Veiculotipo;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class VeiculotipoController extends Controller
{

    protected $tipos = [
      1 => 'Carro',
      2 => 'Caminhão',
      3 => 'Caminhonete',
      4 => 'Moto',
      5 => 'Outros'
    ];
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Veiculotipo $tip)
    {
      $this->authorize('view',$tip);
      $veiculotipos = Veiculotipo::where([
        ['grupo_id', Session::get('empresa_padrao')->grupo_id]
        ])->get();
      $tipos = $this->tipos;
      return view('veiculos.veiculotipos',compact('veiculotipos', 'tipos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Veiculotipo $tip)
    {
      $this->authorize('create',$tip);
      $this->validate($request, [
        'descricao' => 'required|unique:veiculotipos,descricao,NULL,id,grupo_id,'.Session::get('empresa_padrao')->grupo_id,
        ]);
      $data = $request->only('descricao', 'ativo', 'tiporastreamento');
      $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
      $data["empresa_id"] = Session::get('empresa_padrao')->id;
      DB::beginTransaction();
      try{
        $veiculotipo = Veiculotipo::create($data);
      }catch(\Exception $e){
        DB::rollback();
        return 'ERRO' . $e->getMessage();
      }
      DB::commit();
      return 'OK|' . $veiculotipo->id;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
    public function update(Request $request, $id, Veiculotipo $tip)
    {
      $this->authorize('update',$tip);
      $veiculotipo = Veiculotipo::findOrFail($id);
      $this->authorize('igualdade',$veiculotipo);
      $this->validate($request, [
        'descricao' => 'required|unique:veiculotipos,descricao,'.$id.',id,grupo_id,'.Session::get('empresa_padrao')->grupo_id,
        ]);
      $data = $request->only('descricao', 'ativo', 'tiporastreamento');
      $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
      DB::beginTransaction();
      try{
        $veiculotipo->update($data);
      }catch(\Exception $e){
        DB::rollback();
        return 'ERRO|' . $e->getMessage();
      }
      DB::commit();
      return 'OK|' . $veiculotipo->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Veiculotipo $tip)
    {
      $this->authorize('delete',$tip);
      $veiculotipo = Veiculotipo::find($id);
      $this->authorize('igualdade',$veiculotipo);
      DB::beginTransaction();
      try {
        $veiculotipo->delete();
      } catch (\Exception $e) {
        DB::rollback();
        return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
      }
      DB::commit();
      return 'OK|';
    }
  }
