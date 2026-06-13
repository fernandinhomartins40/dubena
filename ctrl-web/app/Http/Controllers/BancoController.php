<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Banco;
use App\EmpresasGrupo;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class BancoController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Banco $bank)
    {
        $this->authorize('view', $bank);
        $bancos = Banco::where([
                    ['grupo_id', Session::get('empresa_padrao')->grupo_id],
                ])->get();
        return view('banco.bancos', compact('bancos'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Banco $bank)
    {
        $this->authorize('create', $bank);
        $data = $request->only('codigo', 'descricao', 'site', 'ativo');
        $this->validate($request, [
            'descricao' => 'required|unique:bancos,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'site' => 'unique:bancos,site,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'codigo' => 'required|max:5|unique:bancos,codigo,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
        ]);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $banco = Banco::create($data);
        return 'OK|' . $banco->id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Banco $bank)
    {
        $this->authorize('update', $bank);
        $this->validate($request, [
            'descricao' => 'required|unique:bancos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'site' => 'unique:bancos,site,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'codigo' => 'required|max:5|unique:bancos,codigo,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
        ]);
        $data = $request->only('codigo', 'descricao', 'site', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $banco = Banco::findOrFail($id);
        $banco->update($data);
        return 'OK|' . $banco->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Banco $bank)
    {
        $this->authorize('delete', $bank);
        DB::beginTransaction();
        try {
            Banco::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
