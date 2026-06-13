<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\EmpresasGrupo;
use App\Http\Requests;
use App\Pedidomotivoatraso;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class PedidomotivoatrasoController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Pedidomotivoatraso $atr)
    {
        $this->authorize('view',$atr);
        $pedidomotivoatraso = Pedidomotivoatraso::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->get();
        //dd(Menu::menus());
        return view('pedido.pedidomotivoatraso', compact('pedidomotivoatraso'));
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
    public function store(Request $request, Pedidomotivoatraso $atr)
    {
        $this->authorize('create',$atr);
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:pedidomotivoatrasos,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $pedidomotivoatraso = Pedidomotivoatraso::create($data);
        return 'OK|' . $pedidomotivoatraso->id;
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
    public function update(Request $request, $id, Pedidomotivoatraso $atr)
    {
        $this->authorize('update',$atr);
        $pedidomotivoatraso = Pedidomotivoatraso::findOrFail($id);
        $this->authorize('igualdade',$pedidomotivoatraso);
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:pedidomotivoatrasos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data = $request->only('descricao', 'ativo');

        $data["ativo"] = isset($data["ativo"]) ? 1 : 0;
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $pedidomotivoatraso->update($data);
        return 'OK|' . $pedidomotivoatraso->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Pedidomotivoatraso $atr)
    {
        $this->authorize('delete',$atr);
        $pedidomotivoatraso = Pedidomotivoatraso::find($id);
        $this->authorize('igualdade',$pedidomotivoatraso);
        DB::beginTransaction();
        try {
            $pedidomotivoatraso->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
