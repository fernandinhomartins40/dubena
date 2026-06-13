<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Tipodocumento;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class TipodocumentoController extends Controller
{


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Tipodocumento $doc)
    {
        $this->authorize('view',$doc);
        $tipodocumentos = Tipodocumento::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        return view('documento.tipodocumentos', compact('tipodocumentos'));
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
    public function store(Request $request, Tipodocumento $doc)
    {
        $this->authorize('create',$doc);
        $this->validate($request, [
            'descricao' => 'required|unique:tipodocumentos,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $tipodocumento = Tipodocumento::create($data);
        return 'OK|' . $tipodocumento->id;
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
    public function update(Request $request, $id, Tipodocumento $doc)
    {
        $this->authorize('update',$doc);
        $tipodocumento = Tipodocumento::findOrFail($id);
        $this->authorize('igualdade',$tipodocumento);
        $this->validate($request, [
            'descricao' => 'required|unique:tipodocumentos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $tipodocumento->update($data);
        return 'OK|' . $tipodocumento->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Tipodocumento $doc)
    {
        $this->authorize('delete',$doc);
        $tipodocumento = Tipodocumento::find($id);
        $this->authorize('igualdade',$tipodocumento);
        DB::beginTransaction();
        try {
            Tipodocumento::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
