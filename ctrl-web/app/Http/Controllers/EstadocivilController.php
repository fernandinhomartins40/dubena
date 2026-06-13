<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Estadocivil;
use App\Http\Requests;
use App\EmpresasGrupo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class EstadocivilController extends Controller
{
   
   /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Estadocivil $est)
    {
        $this->authorize('view',$est);
        $estadocivils = Estadocivil::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();

            //dd(Menu::menus());
        return view('estadocivil.estadocivils', compact('estadocivils'));
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
    public function store(Request $request,Estadocivil $est)
    {
        $this->authorize('create',$est);
        $this->validate($request, [
            'descricao' => 'required|unique:estadocivils,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $estadocivil = Estadocivil::create($data);
        return 'OK|' . $estadocivil->id;
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
    public function update(Request $request, $id, Estadocivil $est)
    {
        $this->authorize('update',$est);
        $estadocivil = Estadocivil::find($id);
        $this->authorize('igualdade',$estadocivil);
        $this->validate($request, [
            'descricao' => 'required|unique:estadocivils,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $estadocivil->update($data);
        return 'OK|' . $estadocivil->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Estadocivil $est)
    {
        $this->authorize('delete',$est);
        $estadocivil = Estadocivil::find($id);
        $this->authorize('igualdade',$estadocivil);
        DB::beginTransaction();
        try {
            $estadocivil->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
