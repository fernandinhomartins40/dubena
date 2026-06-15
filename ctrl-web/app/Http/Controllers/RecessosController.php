<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Recesso;
use App\Recessotipo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tiporecessos;

class RecessosController extends Controller 
{

    public function index(Recesso $rec) {
        $this->authorize('view',$rec);

        $tiporecessos = Recessotipo::where([
                    ['grupo_id', Session::get('empresa_padrao')->grupo_id],
                    ['ativo',1]
                ])->get()->pluck('descricao', 'id');
        
        $recessos = Recesso::where('empresa_id', Session::get('empresa_padrao')->id)->get();
        return view('recessos.recessos', compact('recessos', 'tiporecessos'));
    }

    public function store(Request $request, Recesso $rec) {
        $this->authorize('create',$rec);
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:recessos,descricao,null,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'tipo_id' => 'required',
        ]);
        $data = $request->all();
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["datainicio"] = insertDataOracle($data["datainicio"]);
        $data["datafinal"] = insertDataOracle($data["datafinal"]);
        Recesso::create($data);
        return 'OK|';
    }

    public function update(Request $request, $id, Recesso $rec) {
        $this->authorize('update',$rec);
        $recessos = Recesso::findOrFail($id);
        $this->authorize('igualdade',$recessos);
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:recessos,descricao,'. $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'tipo_id' => 'required',
        ]);

        $data = $request->all();
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["datainicio"] = insertDataOracle($data["datainicio"]);
        $data["datafinal"] = insertDataOracle($data["datafinal"]);
        $recessos->update($data);
        return 'OK|' . $recessos->id;
    }

    public function destroy($id, Recesso $rec) {
        $this->authorize('delete',$rec);
        $recessos = Recesso::findOrFail($id);
        $this->authorize('igualdade',$recessos);
        DB::begintransaction();
        try {
            $recessos->delete();
            DB::commit();
        } catch (\Exception $ex) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        return 'OK|';
    }

}
