<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Nficms;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class NficmsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Nficms $ic)
    {
        $this->authorize('view',$ic);
        $nficms = Nficms::all();
        return view('nf.nficms.nficms')->with(compact('nficms'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Nficms $ic)
    {
        $this->authorize('create',$ic);
        $this->validate($request, [
            'descricao' => 'required|max:100',
            'codigo' => 'required|max:3|unique:nficms,codigo,NULL,id'
        ]);

        $data = $request->only('codigo', 'descricao');
        if(strlen($data['descricao']) > 100) {
            return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        }
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $nficms = Nficms::create($data);
        return 'OK|' . $nficms->id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Nficms $ic)
    {
        $this->authorize('update',$ic);
        $this->validate($request, [
            'descricao' => 'required|max:100',
            'codigo' => 'required|max:3|unique:nficms,codigo,' . $id . ',id'
        ]);

        $data = $request->only('codigo', 'descricao');

        if(strlen($data['descricao']) > 100) {
            return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        }
        
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;

        $nficms = Nficms::findOrFail($id);
        $nficms->update($data);
        return 'OK|' . $nficms->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Nficms $ic)
    {
        $this->authorize('delete',$ic);
        DB::beginTransaction();
        try {
        Nficms::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
