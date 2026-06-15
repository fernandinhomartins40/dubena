<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Recessotipo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TiporecessosController extends Controller {

    protected $tiporecessos;
    protected $empresa_id;
    protected $grupo_id;

    public function index(Recessotipo $tip) {
        $this->authorize('view',$tip);
        $this->definition();
        $tiporecessos = $this->tiporecessos;
        return view('recessos.tiporecessos', compact('tiporecessos'));
    }

    public function store(Request $request, Recessotipo $tip) {
        $this->authorize('create',$tip);
        $this->definition();
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:recessotipos,descricao,null,id,grupo_id,' . $this->grupo_id,
            'legenda' => 'required|max:3|unique:recessotipos,legenda,null,id,grupo_id,' . $this->grupo_id,
        ]);
        $data = $request->all();
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $data["ativo"] = isset($data["ativo"]) ? $data["ativo"] : 0;
        Recessotipo::create($data);
        return 'OK|';
    }

    public function update(Request $request, $id, Recessotipo $tip) {
        $this->authorize('update',$tip);
        $tiporecessos = recessotipo::findOrFail($id);
        $this->authorize('igualdade',$tiporecessos);
        $this->definition();
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:recessotipos,descricao,'. $id . ',id,grupo_id,' . $this->grupo_id,
            'legenda' => 'required|max:3|unique:recessotipos,legenda,'. $id . ',id,grupo_id,' . $this->grupo_id,
        ]);
        $data = $request->all();
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $data["ativo"] = isset($data["ativo"]) ? $data["ativo"] : 0;
        $tiporecessos->update($data);
        return 'OK|' . $tiporecessos->id;
    }
   
    public function destroy($id, Recessotipo $tip) {
        $this->authorize('delete',$tip);
        $tiporecesso = recessotipo::find($id);
        $this->authorize('igualdade',$tiporecesso);
        DB::begintransaction();
        try{
            $tiporecesso->delete();
        } catch (\Exception $ex) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    private function definition() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->tiporecessos = Recessotipo::where('grupo_id', $this->grupo_id)->get();
    }

}
