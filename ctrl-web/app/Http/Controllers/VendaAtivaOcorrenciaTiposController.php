<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Illuminate\Http\Request;
use App\Vendaativaocorrenciatipo;
use App\Http\Controllers\Controller;

class VendaAtivaOcorrenciaTiposController extends Controller
{
    /*     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Vendaativaocorrenciatipo $vatipo)
    {
        $this->authorize('view',$vatipo);
        $vendaativaocorrenciatipos = Vendaativaocorrenciatipo::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        return view('vendas.vendaativaocorrenciatipos', compact('vendaativaocorrenciatipos'));
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
    public function store(Request $request, Vendaativaocorrenciatipo $vatipo)
    {
        $this->authorize('create',$vatipo);
        $this->validate($request, [
            'descricao' => 'required|unique:vendaativaocorrenciatipos,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id
            ]);

        $data = $request->only('descricao', 'cliente_ativo', 'ativo');
        $data["cliente_ativo"] = isset($data["cliente_ativo"]);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $vendaativaocorrenciatipos = Vendaativaocorrenciatipo::create($data);

        return 'OK|' . $vendaativaocorrenciatipos->id;
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
    public function update(Request $request, $id, Vendaativaocorrenciatipo $vatipo)
    {
        $this->authorize('update',$vatipo);
        $vendaativaocorrenciatipos = Vendaativaocorrenciatipo::findOrFail($id);
        $this->authorize('igualdade',$vendaativaocorrenciatipos);
        $this->validate($request, [
            'descricao' => 'required|unique:vendaativaocorrenciatipos,descricao,' . $id . ' ,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
        ]);
        
        $data = $request->only('descricao', 'cliente_ativo', 'ativo');
        $data["cliente_ativo"] = isset($data["cliente_ativo"]);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;

        $vendaativaocorrenciatipos->update($data);

        return 'OK|' . $vendaativaocorrenciatipos->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id,Vendaativaocorrenciatipo $vatipo)
    {
        $this->authorize('delete',$vatipo);
        $ocorrencia = Vendaativaocorrenciatipo::find($id);
        $this->authorize('igualdade',$ocorrencia);
        DB::beginTransaction();
        try {
            $ocorrencia->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
