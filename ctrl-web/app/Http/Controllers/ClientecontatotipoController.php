<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Empresa;
use App\EmpresasGrupo;
use App\Http\Requests;
use App\Clientecontatotipo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class ClientecontatotipoController extends Controller
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
    public function index(Clientecontatotipo $cont)
    {
        $this->authorize('view',$cont);
        $clientecontatotipos = Clientecontatotipo::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        return view('clientecontato.clientecontatotipos', compact('clientecontatotipos'));
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
    public function store(Request $request, Clientecontatotipo $cont)
    {
        $this->authorize('create',$cont);
        //$input = Request::all();
        //$data = $request->only1('descricao', 'ativo');
        $this->validate($request, [
            'descricao' => 'required|unique:clientecontatotipos,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $clientecontatotipo = Clientecontatotipo::create($data);
        return 'OK|' . $clientecontatotipo->id;
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
    public function update(Request $request, $id, Clientecontatotipo $cont)
    {
        $this->authorize('update',$cont);
        $this->validate($request, [
            'descricao' => 'required|unique:clientecontatotipos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        //dd($data);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $clientecontatotipo = Clientecontatotipo::findOrFail($id);
        $clientecontatotipo->update($data);
        return 'OK|' . $clientecontatotipo->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Clientecontatotipo $cont)
    {
        $this->authorize('update',$cont);
        DB::beginTransaction();
        try {
            Clientecontatotipo::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
