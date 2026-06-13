<?php

namespace App\Http\Controllers;

use App\Nfclastrib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class NfclasstribController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Nfclastrib $ct)
    {
        $this->authorize('view', $ct);
        $nfclastribs = Nfclastrib::all();
        return view('nf.nfclastrib.nfclastrib')->with(compact('nfclastribs'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Nfclastrib $ct)
    {
        $this->authorize('create', $ct);
        $this->validate($request, [
            'descricao' => 'required',
            'codigo' => 'required|max:6|unique:nficms,codigo,NULL,id'
        ]);

        $data = $request->all();
        // if (strlen($data['descricao']) > 100) {
        //     return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        // }

        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["nome"] = str_limit($data["descricao"], 100, "");
        $data["ind_gtribregular"] = isset($data["ind_gtribregular"]) && $data["ind_gtribregular"];
        $data["ind_gcredpresoper"] = isset($data["ind_gcredpresoper"]) && $data["ind_gcredpresoper"];
        $data["ind_gmonopadrao"] = isset($data["ind_gmonopadrao"]) && $data["ind_gmonopadrao"];
        $data["ind_gmonoreten"] = isset($data["ind_gmonoreten"]) && $data["ind_gmonoreten"];
        $data["ind_gmonoret"] = isset($data["ind_gmonoret"]) && $data["ind_gmonoret"];
        $data["ind_gmonodif"] = isset($data["ind_gmonodif"]) && $data["ind_gmonodif"];
        $data["ind_gestornocred"] = isset($data["ind_gestornocred"]) && $data["ind_gestornocred"];

        $nfclastrib = Nfclastrib::create($data);

        return 'OK|' . $nfclastrib->id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $nfclastrib = Nfclastrib::find($id);
        $this->authorize('update', $nfclastrib);
        $this->validate($request, [
            'descricao' => 'required',
            'codigo' => 'required|max:6|unique:nficms,codigo,' . $nfclastrib->id . ',id'
        ]);

        $data = $request->all();

        // if (strlen($data['descricao']) > 100) {
        //     return '<br />O campo Descrição não deve ser maior que 100 caracteres.';
        // }

        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["nome"] = str_limit($data["descricao"], 100, "");
        $data["ind_gtribregular"] = isset($data["ind_gtribregular"]) && $data["ind_gtribregular"];
        $data["ind_gcredpresoper"] = isset($data["ind_gcredpresoper"]) && $data["ind_gcredpresoper"];
        $data["ind_gmonopadrao"] = isset($data["ind_gmonopadrao"]) && $data["ind_gmonopadrao"];
        $data["ind_gmonoreten"] = isset($data["ind_gmonoreten"]) && $data["ind_gmonoreten"];
        $data["ind_gmonoret"] = isset($data["ind_gmonoret"]) && $data["ind_gmonoret"];
        $data["ind_gmonodif"] = isset($data["ind_gmonodif"]) && $data["ind_gmonodif"];
        $data["ind_gestornocred"] = isset($data["ind_gestornocred"]) && $data["ind_gestornocred"];

        $nfclastrib->update($data);

        return 'OK|' . $nfclastrib->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $nfclastrib = Nfclastrib::find($id);
        $this->authorize('delete', $nfclastrib);

        DB::beginTransaction();
        try {
            $nfclastrib->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }
}
