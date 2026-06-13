<?php

namespace App\Http\Controllers;

use Session;
use Redirect;
use App\Valegas;
use App\Services\CarbonCustom as Carbon;
use App\Valegassituacao;
use Illuminate\Http\Request;

class ValegascancelarController extends Controller
{

    public function index(Valegas $val)
    {
        $this->authorize('view',$val);
        $situacao = '';
        $situacoes = Valegassituacao::Where('descricao', 'CANCELADO')->orWhere('descricao', 'Cancelado')->pluck('descricao', 'id');
        $codigo = '';
        return View('valegas.cancelar.valegascancelar', compact('situacoes', 'situacao', 'codigo'));
    }

    public function store(Request $request, Valegas $val)
    {
        $this->authorize('view', $val);
        $data = $request->all();
        $this->validate($request, [
            'codigo' => 'required',
            'situacao' => 'required'
            ]);
        $valegas = Valegas::where([
            ['empresa_id', Session::get('empresa_padrao')->id],
            ['codigo', $data['codigo']]
            ])->get();
        
        foreach ($valegas as $item) {
            $valegas_id = $item['id'];
            $situacao = strtoupper($item->valeGasSituacao->descricao);
            if($situacao == 'CANCELADO')
                return Redirect::to('valegascancelar')->withErrors('Este Vale Gás já está cancelado.')->withInput();
            if($situacao == 'BAIXADO')
                return Redirect::to('valegascancelar')->withErrors('Este Vale Gás está baixado e não é possível cancela-lo.')->withInput();
        }
        if (!isset($valegas_id)) {
            return Redirect::to('valegascancelar')->withErrors('Gás de Bolso não encontrado. Verifique o código e tente novamente!')->withInput();
        }
        $valegas = Valegas::findOrFail($valegas_id);
        $this->authorize('igualdade',$valegas);
        $valegas->update(['valegassituacao_id' => $data['situacao'], 'databaixa' => Carbon::now()]);
        return Redirect::to('valegascancelar')->withMessageSuccess('Operação realizada com sucesso!');
    }

}
