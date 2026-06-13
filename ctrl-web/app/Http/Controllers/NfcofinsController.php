<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Nfcofins;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NfcofinsController extends Controller
{

    protected $nfcofins;
    protected $empresa_id;
    protected $grupo_id;

    public function definition(){
        $this->nfcofins = Nfcofins::all();
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Nfcofins $nf)
    {
        $this->authorize('view',$nf);
        $this->definition();
        $nfcofins = $this->nfcofins;
        return view('nf.nfcofins.nfcofins', compact('nfcofins'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Nfcofins $nf)
    {
        $this->authorize('create',$nf);
        $this->validate($request, [
            'descricao' => 'required',
            'codigo' => 'required|max:3|unique:nfcofins,codigo,null,id'
            ]);

        $data = $request->all();

        if(strlen($data['descricao']) > 100) {
            return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        }
        
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $produtoclasse = Nfcofins::create($data);
        return 'OK|' . $produtoclasse->id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Nfcofins $nf)
    {
        $this->authorize('update',$nf);
        $this->validate($request, [
            'descricao' => 'required|max:100',
            'codigo' => 'required|max:3|unique:nfcofins,codigo,' . $id . ',id'
            ]);
        $data = $request->all();
        if(strlen($data['descricao']) > 100) {
            return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        }
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $nfcofins = Nfcofins::findOrFail($id);
        $nfcofins->update($data);
        return 'OK|' . $nfcofins->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Nfcofins $nf)
    {
        $this->authorize('delete',$nf);
        DB::begintransaction();
        try{
            Nfcofins::find($id)->delete();
            DB::commit();
        } catch (Exception $ex) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        return 'OK|';
    }

}
