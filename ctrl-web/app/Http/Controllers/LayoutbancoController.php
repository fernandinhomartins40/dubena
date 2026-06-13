<?php

namespace App\Http\Controllers;

use DB;
use App\Layoutbanco;
use Illuminate\Http\Request;

class LayoutbancoController extends Controller
{

    public function index(Layoutbanco $lay)
    {
        $this->authorize('view', $lay);
        $layouts = Layoutbanco::all();
        return view('general.layoutbancos', compact('layouts'));
    }

    public function store(Request $request, Layoutbanco $lay)
    {
        $this->authorize('create', $lay);
        return $this->insertOrUpdate($request);
    }

    public function update(Request $request, $id, Layoutbanco $lay)
    {
        $this->authorize('update', $lay);
        return $this->insertOrUpdate($request, $id);
    }

    private function insertOrUpdate($request, $id = null)
    {
        //max de 200 porque o banco aceita 250 BITES
        $rules = [
            'descricao'                 => 'required|max:200',
            'cnab'                      => 'required',
            'minimodiasprotesto'        => 'required|numeric|min:0|max:99',
            'maximodiasprotesto'        => 'required|numeric|min:0|max:99',
            'minimodiasbaixadevolucao'  => 'required|numeric|min:0|max:99',
            'maximodiasbaixadevolucao'  => 'required|numeric|min:0|max:99',
            'boletoposicoesnossonumero' => 'required|numeric|min:0|max:99',
            'codigo_banco'              => 'required|numeric|min:0|max:999'
        ];

        $this->validate($request, $rules);

        try {
            $data = $request->all();
            $data['ativo'] = isset($data['ativo']) ? true : false;
            
            if(strlen($data['descricao']) > 200)
                throw new \Exception ("O campo Descrição deve possuir mais de 200 caracteres.");
            
            if ($id != null)
                Layoutbanco::find($id)->update($data);
            else
                Layoutbanco::create($data);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        return "OK|";
    }

    public function destroy($id, Layoutbanco $lay)
    {
        $this->authorize('delete', $lay);
        DB::beginTransaction();
        try {
            Layoutbanco::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return "O registro não pôde ser excluído pois está sendo usado!";
        }
        DB::commit();
        return 'OK|';
    }

}
