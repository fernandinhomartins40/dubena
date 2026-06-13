<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Ocorrenciasremessas;

class OcorrenciasremessasController extends Controller
{
    public function index()
    {
    	$tipo = $this->getTipo();
    	$ocorrenciasremessas = [];

    	if (count($_GET) > 0) 
    		$ocorrenciasremessas = $this->getOcorrencias();


    	return view('financeiro.boletos.ocorrencias.ocorrencias_index', compact('ocorrenciasremessas', 'tipo'));
    }

    private function getTipo()
    {
    	return ['' => 'Selecione', 2 => 'Pré-crítica', 0 => 'Remessa', 1 => 'Retorno'];
    }

    private function getOcorrencias()
    {
    	$data = [];
    	$data['codigo_banco'] = $_GET['codigo_banco_search'];
        if(!empty($_GET['tipo_search']) || $_GET['tipo_search'] === '0')
            $data['tipo'] = $_GET['tipo_search'];
    	return Ocorrenciasremessas::where($data)->get();
    }

    public function store(Request $request)
    {
    	return $this->createOrUpdate($request);
    }

    public function update(Request $request, $id)
    {
    	return $this->createOrUpdate($request, $id);
    }

    public function createOrUpdate($request, $id = null)
    {
    	$rules = [
		            'descricao' 				=> 'required|max:255',
		            'codigo' 					=> 'required|max:99',
		            'tipo' 						=> 'required',
		            'codigo_banco' 				=> 'required|numeric|min:0|max:999'
				];
				
    	$this->validate($request, $rules);
    	try {
	    	$data = $request->all();
	    	$data['uso_banco'] = isset($data['uso_banco']);
	    	if(is_null($id))
	    		Ocorrenciasremessas::create($data);
	    	else
				Ocorrenciasremessas::find($id)->update($data);
    	} catch (\Exception $e) {
    		return getErrorsException($e);
    	}
    	return "OK|";
    }

    public function destroy($id)
    {
    	try {
    		Ocorrenciasremessas::find($id)->delete();
    	} catch (\Exception $e) {
    		return getErrorsException($e);
    	}
		return "OK|";
    }
}
