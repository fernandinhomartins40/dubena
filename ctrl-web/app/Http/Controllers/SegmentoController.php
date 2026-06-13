<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Menu;
use App\Empresa;
use App\Segmento;
use App\EmpresasGrupo;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class SegmentoController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Segmento $seg)
    {
        $this->authorize('view',$seg);
        $segmentos = Segmento::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();

        return view('segmentos.segmentos', compact('segmentos'));
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
    public function store(Request $request, Segmento $seg)
    {
        $this->authorize('create',$seg);
        $this->validate($request, [
            'descricao' => 'required|unique:segmentos,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $segmento = Segmento::create($data);
        return 'OK|' . $segmento->id;
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
    public function update(Request $request, $id, Segmento $seg)
    {
        $this->authorize('update',$seg);
        $this->validate($request, [
            'descricao' => 'required|unique:segmentos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        //dd($data);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;

        $segmento = Segmento::findOrFail($id);
        $segmento->update($data);
        return 'OK|' . $segmento->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Segmento $seg)
    {
        $this->authorize('delete',$seg);
        DB::beginTransaction();
        try {
            Segmento::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
