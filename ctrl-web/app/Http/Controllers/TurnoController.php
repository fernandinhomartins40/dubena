<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\EmpresasGrupo;
use App\Empresa;
use App\Turno;
use App\Http\Controllers\Controller;
use Session;
use Illuminate\Support\Facades\Input;
use DB;

class TurnoController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $turnos = Turno::where([
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->get();
        return view('turno.turnos', compact('turnos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        
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
        //$data = $request->only1('descricao', 'ativo');
        $this->validate($request, [
            'descricao' => 'required|unique:turnos,descricao,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $turno = Turno::create($data);
        return 'OK|' . $turno->id;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        
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
        $this->validate($request, [
            'descricao' => 'required|unique:turnos,descricao,' . $id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
            ]);
        $data = $request->only('descricao', 'ativo');
        //dd($data);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;

        $turno = Turno::findOrFail($id);
        $turno->update($data);
        return 'OK|' . $turno->id;
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
            Turno::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
