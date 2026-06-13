<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Http\Requests;
use App\Tipocombustivel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TipocombustivelController extends Controller
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
    public function index(Tipocombustivel $tip)
    {
        $this->authorize('view',$tip);
        $tipocombustivels = Tipocombustivel::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        return view('combustivel.tipocombustivels', compact('tipocombustivels'));
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
    public function store(Request $request, Tipocombustivel $tip)
    {
        $this->authorize('create',$tip);
        $this->validate($request, [
            'descricao' => 'required|unique:tipocombustivels,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $tipocombustivel = Tipocombustivel::create($data);
        return 'OK|' . $tipocombustivel->id;
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
    public function update(Request $request, $id, Tipocombustivel $tip)
    {
        $this->authorize('update',$tip);
        $tipocombustivel = Tipocombustivel::findOrFail($id);
        $this->authorize('igualdade',$tipocombustivel);
        $this->validate($request, [
            'descricao' => 'required|unique:tipocombustivels,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        //dd($data);
        $tipocombustivel->update($data);
        return 'OK|' . $tipocombustivel->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Tipocombustivel $tip)
    {
        $this->authorize('delete',$tip);
        $tipocombustivel = Tipocombustivel::find($id);
        $this->authorize('igualdade',$tipocombustivel);
        DB::beginTransaction();
        try {
            $tipocombustivel->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }
}
