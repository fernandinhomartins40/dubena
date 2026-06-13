<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Nfipi;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class NfipiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Nfipi $ipi){
        $this->authorize('view',$ipi);
        $nfipis = Nfipi::all();
        return view('nf.nfipi.nfipi', compact('nfipis'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Nfipi $ipi)
    {
        $this->authorize('create',$ipi);
        $this->validate($request, [
            'descricao' => 'required|max:100',
            'codigo' => 'required|max:3|unique:nfipis,codigo,null,id',
            ]);
        $data = $request->only('codigo', 'descricao');
        if(strlen($data['descricao']) > 100) {
            return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        }
        $nfipi = Nfipi::create($data);
        return 'OK|' . $nfipi->id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Nfipi $ipi)
    {
        $this->authorize('update',$ipi);
        $this->validate($request, [
            'descricao' => 'required|max:100',
            'codigo' => 'required|max:3|unique:nfipis,codigo,' . $id . ',id'
            ]);
        $data = $request->only('codigo', 'descricao');
        if(strlen($data['descricao']) > 100) {
            return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        }
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $nfipi = Nfipi::findOrFail($id);
        $nfipi->update($data);
        return 'OK|' . $nfipi->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Nfipi $ipi)
    {
        $this->authorize('delete',$ipi);
        DB::beginTransaction();
        try {
            Nfipi::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
