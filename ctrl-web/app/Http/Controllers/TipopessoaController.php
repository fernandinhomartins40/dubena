<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Menu;
use App\Empresa;
use App\Tipopessoa;
use App\EmpresasGrupo;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class TipopessoaController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Tipopessoa $tipo)
    {
        $this->authorize('view',$tipo);
        $tipopessoas = Tipopessoa::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();

        //dd(Menu::menus());
        return view('pessoa.tipopessoas', compact('tipopessoas'));
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
    public function store(Request $request, Tipopessoa $tipo)
    {
        $this->authorize('create',$tipo);
        $this->validate($request, [
            'descricao' => 'required|unique:tipopessoas,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo', 'tipopessoacadastro');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $tipopessoa = Tipopessoa::create($data);
        return 'OK|' . $tipopessoa->id;
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
    public function update(Request $request, $id, Tipopessoa $tipo)
    {
        $this->authorize('update',$tipo);
        $this->validate($request, [
            'descricao' => 'required|unique:tipopessoas,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo', 'tipopessoacadastro');
        //dd($data);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;

        $tipopessoa = Tipopessoa::findOrFail($id);
        $tipopessoa->update($data);
        return 'OK|' . $tipopessoa->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Tipopessoa $tipo)
    {
        $this->authorize('delete',$tipo);
        DB::beginTransaction();
        try {
            Tipopessoa::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
