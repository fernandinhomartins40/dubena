<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Cargo;
use App\EmpresasGrupo;
use App\Http\Requests;
use Illuminate\Http\Request;

class CargoController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Cargo $car)
    {
        $this->authorize('view',$car);
        $cargos = Cargo::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();

        return view('cargo.cargos', compact('cargos'));
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
    public function store(Request $request, Cargo $car)
    {
        $this->authorize('create',$car);
        $this->validate($request, [
            'descricao' => 'required|unique:cargos,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $cargo = Cargo::create($data);
        return 'OK|' . $cargo->id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Cargo $car)
    {
        $this->authorize('update',$car);
        $this->validate($request, [
            'descricao' => 'required|unique:cargos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        //dd($data);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $cargo = Cargo::findOrFail($id);
        $cargo->update($data);
        return 'OK|' . $cargo->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Cargo $car)
    {
        $this->authorize('delete',$car);
        DB::beginTransaction();
        try {
            Cargo::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}