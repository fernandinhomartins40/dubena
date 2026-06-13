<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Tipoexame;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class TipoexameController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Tipoexame $ex)
    {   
        $this->authorize('view',$ex);
        $tipoexames = Tipoexame::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        return view('exame.tipoexames', compact('tipoexames'));
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
    public function store(Request $request,Tipoexame $ex)
    {
        $this->authorize('create',$ex);
        $this->validate($request, [
            'descricao' => 'required|unique:tipoexames,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->all();
        $data['admissional'] = isset($data['admissional']);
        $data['ativo'] = isset($data['ativo']);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $tipoexame = Tipoexame::create($data);
        return 'OK|' . $tipoexame->id;
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
    public function update(Request $request, $id, Tipoexame $ex)
    {
        $this->authorize('update',$ex);
        $this->validate($request, [
            'descricao' => 'required|unique:tipoexames,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->all();
        
        $data['admissional'] = isset($data['admissional']);
        $data['ativo'] = isset($data['ativo']);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $tipoexame = Tipoexame::findOrFail($id);
        $tipoexame->update($data);
        return 'OK|' . $tipoexame->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Tipoexame $ex)
    {
        $this->authorize('delete',$ex);
        DB::beginTransaction();
        try {
            Tipoexame::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
