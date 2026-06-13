<?php

namespace App\Http\Controllers;

use Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Parentesco;
use DB;

class ParentescoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Parentesco $par)
    {
        $this->authorize('view',$par);
        $parentescos = Parentesco::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        //dd(Menu::menus());
        return view('parentesco.parentescos', compact('parentescos'));
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
    public function store(Request $request, Parentesco $par)
    {
        $this->authorize('create',$par);
        $this->validate($request, [
            'descricao' => 'required|unique:parentescos,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $parentesco = Parentesco::create($data);
        return 'OK|' . $parentesco->id;
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
    public function update(Request $request, $id, Parentesco $par)
    {
        $this->authorize('update',$par);
        $this->validate($request, [
            'descricao' => 'required|unique:parentescos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        //dd($data);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $parentesco = Parentesco::findOrFail($id);
        $parentesco->update($data);
        return 'OK|' . $parentesco->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Parentesco $par)
    {
        $this->authorize('delete',$par);
        DB::beginTransaction();
        try {
            Parentesco::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
