<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Contamovimentotipo;
use Illuminate\Http\Request;

class ContamovimentotipoController extends Controller
{

	public function index(Contamovimentotipo $tip)
	{	
		$this->authorize('view',$tip);
		$contamovimentos = Contamovimentotipo::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->get();
		return view('financeiro.contamovimento.contamovimentotipo', compact('contamovimentos'));
	}

	public function store(Request $request, Contamovimentotipo $tip)
	{
		$this->authorize('create',$tip);
		$this->validate($request, [
			'descricao' => 'required'
			]);

		try {
			$data = $request->all();
			$data = $this->dadosExtras($data);
			Contamovimentotipo::create($data);
		} catch (\Exception $e) {
			return $e->getMessage();
		}
		return "OK|";
	}

	public function update(Request $request, $id, Contamovimentotipo $tip)
	{
		$this->authorize('update',$tip);
		$this->validate($request, [
			'descricao' => 'required'
			]);

		try {
			$contamovimento = Contamovimentotipo::find($id);
			$this->authorize('igualdade',$contamovimento);
			$data = $request->all();
			$data = $this->dadosExtras($data);
			$contamovimento->update($data);
		} catch (\Exception $e) {
			return $e->getMessage();
		}
		return "OK|";
	}

	private function dadosExtras($data)
	{
		$data['ativo'] = isset($data['ativo']) ? 1 : 0;
		$data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
		$data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
		switch ($data['tipo']) {
			case '1':
			$data['cartao'] = 1;
			$data['cheque'] = 0;
			$data['convenio'] = 0;
			$data['valegas'] = 0;
			break;
			case '2':
			$data['cheque'] = 1;
			$data['cartao'] = 0;
			$data['convenio'] = 0;
			$data['valegas'] = 0;
			break;
			case '3':
			$data['convenio'] = 1;
			$data['cartao'] = 0;
			$data['cheque'] = 0;
			$data['valegas'] = 0;
			break;
			case '4':
			$data['valegas'] = 1;
			$data['cartao'] = 0;
			$data['cheque'] = 0;
			$data['convenio'] = 0;
			break;
			default:
			break;
		}
		unset($data['id']);
		unset($data['tipo']);
		return $data;
	}

	public function destroy($id, Contamovimentotipo $tip)
	{
		$this->authorize('delete',$tip);
		$contamov = Contamovimentotipo::find($id);
		$this->authorize('igualdade',$contamov);
        DB::beginTransaction();
        try {
            $contamov->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
	}
}
