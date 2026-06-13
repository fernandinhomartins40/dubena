<?php

namespace App\Http\Controllers;

use DB;
use App\Http\Requests;
use App\Pedidosituacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class PedidosituacaoController extends Controller
{

    private $status;
    private $grupo_id;
    private $pedidoSituacoes;

    function index(Pedidosituacao $sid)
    {
        $this->authorize('view',$sid);
        $this->definition();
        $pedidosituacoes = $this->pedidoSituacoes;
        $status = $this->status;
        return View('pedido.situacao.situacoes', compact('status', 'pedidosituacoes'));
    }

    public function store(Request $request, Pedidosituacao $sid)
    {
        $this->authorize('create',$sid);
        $this->definition();
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:pedidosituacaos,descricao,null,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'status' => 'required',
            ]);

        $data = $request->all();
        $tipo = $this->dadosExtras($data);
        if (is_array($tipo)){
            $data = array_merge($data, $tipo);
        } else {
            return $tipo;
        }
        $pedidosituacao = Pedidosituacao::create($data);
        return 'OK|' . $pedidosituacao->id;
    }

    public function update(Request $request, $id, Pedidosituacao $sid)
    {
        $this->authorize('update',$sid);
        $pedidosituacao = Pedidosituacao::findOrFail($id);
        $this->authorize('igualdade',$pedidosituacao);
        $this->definition();
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:pedidosituacaos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'status' => 'required',
            ]);
        $data = $request->all();
        $tipo = $this->dadosExtras($data);
        if (is_array($tipo)){
            $data = array_merge($data, $tipo);
        } else {
            return $tipo;
        }
        $pedidosituacao->update($data);
        return 'OK|' . $pedidosituacao->id;
    }
    private function dadosExtras($data){
        $data['entregafinalizada'] = 0;
        $data['entregacancelada'] = 0;
        $data['entregapendente'] = 0;
        $data['fechadocancelado'] = 0;
        $data['fechadoconcluido'] = 0;
        $data['entregatranferida'] = 0;
        $data['ementrega'] = 0;
        $data['androidusa'] = isset($data['androidusa']) ? 1 : 0;
        $data['valegas'] = isset($data['valegas']) ? 1 : 0;
        $data['solicitacartaoautorizacao'] = isset($data['solicitacartaoautorizacao']) ? 1 : 0;
        switch ($data['status']) {
            case "0":
            $data['entregafinalizada'] = 1;
            break;
            case "1":
            $data['entregacancelada'] = 1;
            break;
            case "2":
            $data['entregapendente'] = 1;
            break;
            case "3":
            $data['fechadoconcluido'] = 1;
            break;
            case "4":
            $data['fechadocancelado'] = 1;
            break;
            case "5":
            $data['entregatranferida'] = 1;
            break;
            case "6":
            $data['ementrega'] = 1;
            break;
            case "7":
            $data['pedidorecebidomovel'] = 1;
            break;
            case "8":
            $data['pedidolidomovel'] = 1;
            break;
            default:
            return 'Selecione o Status';
        }
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;

        return $data;
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Pedidosituacao $sid)
    {
        $this->authorize('delete',$sid);
        $situacao = Pedidosituacao::find($id);
        $this->authorize('igualdade',$situacao);
        DB::beginTransaction();
        try {
            $situacao->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    private function definition()
    {
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->pedidoSituacoes = Pedidosituacao::where([
            ['grupo_id', $this->grupo_id]
            ])->get();
        $this->status = [
        '0' => 'Entrega Finalizada',
        '1' => 'Entrega Cancelada',
        '2' => 'Entrega Pendente',
        '3' => 'Fechado Concluído',
        '4' => 'Fechado Cancelado',
        '5' => 'Entrega Transferida',
        '6' => 'Em Entrega',
        '7' => 'Pedido recebido no Móvel',
        '8' => 'Pedido Lido no Móvel'
        ];
    }
    
public function buscaPorEmpresa($empresa_id)
{
    try {
        return Pedidosituacao::where([['ativo', 1], ['empresa_id', $empresa_id]])->get(['descricao', 'id']);
    } catch (\Exception $e) {
        return $e->getMessage();
    }
}
}