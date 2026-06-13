<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Telefonetipo;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class TelefonetipoController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Telefonetipo $tipo)
    {
        $this->authorize('view',$tipo);
        $telefonetipos = Telefonetipo::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        return view('telefone.telefonetipos', compact('telefonetipos'));
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
    public function store(Request $request, Telefonetipo $tipo)
    {
        $this->authorize('create',$tipo);
        $this->validate($request, [
            'descricao' => 'required|unique:telefonetipos,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->all();
        $data['celular'] = isset($data['celular']) ? true : false;
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $telefonetipo = Telefonetipo::create($data);
        return 'OK|' . $telefonetipo->id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Telefonetipo $tipo)
    {
        $this->authorize('update',$tipo);
        $this->validate($request, [
            'descricao' => 'required|unique:telefonetipos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->all();
        $data['celular'] = isset($data['celular']) ? true : false;
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
		$data["ativo"] = isset($data["ativo"]);
        $telefonetipo = Telefonetipo::findOrFail($id);
        $telefonetipo->update($data);
        return 'OK|' . $telefonetipo->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Telefonetipo $tipo)
    {
        $this->authorize('delete',$tipo);
        DB::beginTransaction();
        try {
            Telefonetipo::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
