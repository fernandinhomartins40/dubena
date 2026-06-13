<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Produto;
use App\Unidademedida;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UnidademedidaController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Unidademedida $un)
    {
        $this->authorize('view',$un);
        $unidademedidas = Unidademedida::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        return view('general.unidademedidas', compact('unidademedidas'));
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
    public function store(Request $request, Unidademedida $un)
    {
        $this->authorize('create',$un);
        $this->validate($request, [
            'descricao' => 'required|unique:unidademedidas,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'sigla' => 'required'
            ]);
        $data = $request->only('descricao', 'sigla', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $unidademedida = Unidademedida::create($data);
        return 'OK|' . $unidademedida->id;
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
    public function update(Request $request, $id, Unidademedida $un)
    {
        $this->authorize('update',$un);
        $unidademedida = Unidademedida::findOrFail($id);
        $this->authorize('igualdade',$unidademedida);
        $this->validate($request, [
            'descricao' => 'required|unique:unidademedidas,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'sigla' => 'required'
            ]);
        $data = $request->only('descricao', 'sigla', 'ativo');

        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $unidademedida->update($data);

        return 'OK|' . $unidademedida->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Unidademedida $un)
    {
        $this->authorize('delete',$un);
        $unidademedida = Unidademedida::find($id);
        $this->authorize('igualdade',$unidademedida);
        DB::beginTransaction();
        try {
            $unidademedida->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
