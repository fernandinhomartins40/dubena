<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Empresa;
use App\EmpresasGrupo;
use App\Http\Requests;
use App\Documentotipo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class DocumentotipoController extends Controller
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
    public function index(Documentotipo $cont)
    {
        $this->authorize('view',$cont);
        $documentotipos = Documentotipo::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        return view('documentos.documentotipos', compact('documentotipos'));
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
    public function store(Request $request, Documentotipo $cont)
    {
        $this->authorize('create',$cont);
        $this->validate($request, [
            'descricao' => 'required|unique:documentotipos,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'diasalerta');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $documentotipo = Documentotipo::create($data);
        return 'OK|' . $documentotipo->id;
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
    public function update(Request $request, $id, Documentotipo $cont)
    {
        $this->authorize('update',$cont);
        $this->validate($request, [
            'descricao' => 'required|unique:documentotipos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'diasalerta');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $documentotipo = Documentotipo::findOrFail($id);
        $documentotipo->update($data);
        return 'OK|' . $documentotipo->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Documentotipo $cont)
    {
        $this->authorize('update',$cont);
        DB::beginTransaction();
        try {
            Documentotipo::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
