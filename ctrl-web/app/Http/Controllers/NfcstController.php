<?php

namespace App\Http\Controllers;

use App\Nfcst;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class NfcstController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Nfcst $cs)
    {
        $this->authorize('view', $cs);
        $nfcst = Nfcst::all();
        return view('nf.nfcst.nfcst')->with(compact('nfcst'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Nfcst $cs)
    {
        $this->authorize('create', $cs);
        $this->validate($request, [
            'descricao' => 'required|max:100',
            'codigo' => 'required|max:3|unique:nficms,codigo,NULL,id'
        ]);

        $data = $request->only('codigo', 'descricao');
        if (strlen($data['descricao']) > 100) {
            return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        }

        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;

        $nfcst = Nfcst::create($data);

        return 'OK|' . $nfcst->id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        $nfcst = Nfcst::find($id);
        $this->authorize('update', $nfcst);
        $this->validate($request, [
            'descricao' => 'required|max:100',
            'codigo' => 'required|max:3|unique:nficms,codigo,' . $nfcst->id . ',id'
        ]);

        $data = $request->only('codigo', 'descricao');

        if (strlen($data['descricao']) > 100) {
            return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        }

        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;

        $nfcst->update($data);

        return 'OK|' . $nfcst->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $nfcst
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $nfcst = Nfcst::find($id);
        $this->authorize('delete', $nfcst);

        DB::beginTransaction();
        try {
            $nfcst->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }
}
