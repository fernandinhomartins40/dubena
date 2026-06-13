<?php

namespace App\Http\Controllers;

use DB;
use Redirect;
use Exception;
use App\Regiao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class RegiaoController extends Controller
{
    private $grupo_id;
    private $regional;
    
    function index(Regiao $reg){
        $this->definition();
        $this->authorize('view',$reg);
        $regioes = $this->regiao;
        return view('regiao.regiao')->with(compact('regioes'));
    }

    function create(){
        //
    }

    function store(Request $request,Regiao $reg){
        $this->authorize('create',$reg);
        $this->definition();
        $this->validate($request, [
            'descricao' => 'required'
        ]);
        $data = $request->only('descricao','ativo');
        $data["grupo_id"] = $this->grupo_id;
        $data["ativo"] = isset($data["ativo"]) ? $data["ativo"] : "0";
        DB::beginTransaction();
        try{
            $regiao = Regiao::create($data);
        }catch(Exception $ex){
            DB::rollback();
            return $ex->getMessage();
        }
        DB::commit();
        return 'OK|';
    }

    function show($id){
        //
    }

    function edit($id){
        //
    }

    function update(Request $request, $id, Regiao $reg){
        $regiao = Regiao::find($id);
        $this->authorize('update',$regiao);
        $this->definition();
        $this->validate($request, [
            'descricao' => 'required'
        ]);
        $data = $request->only('id','descricao','ativo');
        $data["ativo"] = isset($data["ativo"]) ? $data["ativo"] : "0";
        DB::beginTransaction();
        try{
            $regiao->update($data);
        }catch(Exception $ex){
            DB::rollback();
            return $ex->getMessage();
        }
        DB::commit();
        return 'OK|';
    }

    private function definition(){
        //busca grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //busca todas as regionais
        $this->regiao = Regiao::where('grupo_id',$this->grupo_id)->get();
    }

}
