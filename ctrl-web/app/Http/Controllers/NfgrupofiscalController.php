<?php

namespace App\Http\Controllers;

use DB;
use App\Nfgrupofiscal;
use App\Http\Requests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class NfgrupofiscalController extends Controller
{

    function index(Nfgrupofiscal $grf)
    {
        $this->authorize('view',$grf);
        $gruposfiscais = Nfgrupofiscal::where([
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->get();
        return View('nf.grupofiscal.grupofiscal', compact('gruposfiscais'));
    }

    function store(Request $request, Nfgrupofiscal $grf)
    {
        $this->authorize('create',$grf);
        $data = $request->all();
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:nfgrupofiscals,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data['empresa_id'] = Session::get('empresa_padrao')->id;
        $data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
        Nfgrupofiscal::create($data);
        return "OK|";
    }

    function update(Request $request, $id, Nfgrupofiscal $grf)
    {
        $this->authorize('update',$grf);
        $grupofiscal = Nfgrupofiscal::findOrFail($id);
        $this->authorize('igualdade',$grupofiscal);
        $data = $request->all();
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:nfgrupofiscals,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            ]);
        $data['ativo'] = isset($data['ativo']) ? 1 : 0;
        $data['empresa_id'] = Session::get('empresa_padrao')->id;
        $data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;

        $grupofiscal->update($data);
        return "OK|";
    }

    function destroy($id, Nfgrupofiscal $grf)
    {
        $this->authorize('delete',$grf);
        $grupofiscal = Nfgrupofiscal::find($id);
        $this->authorize('igualdade',$grupofiscal);
        DB::beginTransaction();
        try {
            $grupofiscal->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

}
