<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Nfpis;
use App\Http\Requests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use App\Http\Controllers\Controller;

class NfpisController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Nfpis $pi)
    {
        $this->authorize('view',$pi);
        $nfpis = Nfpis::all();
        return view('nf.nfpis.nfpis', compact('nfpis'));
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
    public function store(Request $request, Nfpis $pi)
    {
        $this->authorize('create',$pi);
        $this->validate($request, [
            'descricao' => 'required|max:100',
            'codigo' => 'required|max:3|unique:nfpis,codigo,null,id'
            ]);

        $data = $request->only('codigo', 'descricao');
        if(strlen($data['descricao']) > 100) {
            return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        }
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $nfpis = Nfpis::create($data);
        return 'OK|' . $nfpis->id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Nfpis $pi)
    {
        $this->authorize('update',$pi);
        $this->validate($request, [
            'descricao' => 'required|max:100',
            'codigo' => 'required|max:3|unique:nfpis,codigo,' . $id . ',id'
            ]);
        $data = $request->only('codigo', 'descricao');
        if(strlen($data['descricao']) > 100) {
            return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        }
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $nfpis = Nfpis::findOrFail($id);
        $nfpis->update($data);
        return 'OK|' . $nfpis->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Nfpis $pi)
    {
        $this->authorize('delete',$pi);
        DB::beginTransaction();
        try {
            Nfpis::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
