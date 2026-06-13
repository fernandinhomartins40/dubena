<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Http\Requests;
use App\EmpresasGrupo;
use App\Motivonaovenda;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class MotivonaovendaController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Motivonaovenda $mot)
    {
        $this->authorize('view',$mot);
        $motivonaovendas = Motivonaovenda::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();

        
        return view('vendas.motivonaovendas', compact('motivonaovendas'));
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
    public function store(Request $request, Motivonaovenda $mot)
    {
        $this->authorize('create',$mot);
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:motivonaovendas,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $motivonaovenda = Motivonaovenda::create($data);
        return 'OK|' . $motivonaovenda->id;
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
     * Show the form for xediting the specified resource.
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
    public function update(Request $request, $id, Motivonaovenda $mot)
    {
        $this->authorize('update',$mot);
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:motivonaovendas,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;

        $motivonaovenda = Motivonaovenda::findOrFail($id);
        $motivonaovenda->update($data);
        return 'OK|' . $motivonaovenda->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Motivonaovenda $mot)
    {
        $this->authorize('delete',$mot);
        DB::beginTransaction();
        try {
            Motivonaovenda::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}