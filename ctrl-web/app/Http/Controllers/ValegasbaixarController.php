<?php

namespace App\Http\Controllers;

use App\Valegassituacao;
use App\Valegas;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use App\Services\CarbonCustom as Carbon;

class ValegasbaixarController extends Controller
{

    public function index()
    {
        $situacao = '';
        $situacoes = Valegassituacao::where('descricao', 'BAIXADO')->orWhere('descricao', 'CANCELADO')->orWhere('descricao', 'Baixado')->orWhere('descricao', 'Cancelado')->pluck('descricao', 'id');
        $codigo = '';
        return View('valegas.baixar.valegasbaixar', compact('situacoes', 'situacao', 'codigo'));
    }

    public function store(Request $request)
    {
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
        }
        if (!isset($valegas_id)) {
            return Redirect::to('valegasbaixar')->withErrors('Gás de Bolso não encontrado. Verifique o código e tente novamente!')->withInput();
        }
        $valegas = Valegas::findOrFail($valegas_id);
        $valegas->update(['valegassituacao_id' => $data['situacao'], 'databaixa' => Carbon::now()]);
        return Redirect::to('valegasbaixar')->withMessageSuccess('Operação realizada com sucesso!');
    }

}
