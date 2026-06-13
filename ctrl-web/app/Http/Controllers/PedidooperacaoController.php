<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Http\Requests;
use App\Pedidooperacao;
use Illuminate\Http\Request;

class PedidooperacaoController extends Controller
{

    private $grupo_id;
    private $pedidoOperacoes;
    private $status;

    function index(Pedidooperacao $pop)
    {   
        $this->authorize('view',$pop);
        $this->definition();
        $pedidooperacoes = $this->pedidoOperacoes;
        $status = $this->status;
        return View('pedido.operacao.operacoes', compact('status', 'pedidooperacoes'));
    }

    public function store(Request $request, Pedidooperacao $pop)
    {
        $this->authorize('create',$pop);
        $this->definition();
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:pedidooperacaos,descricao,null,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'status' => 'required',
            ]);

        $data = $request->all();
        $data['convenio'] = 0;
        $data['disk'] = 0;
        $data['gasbolso'] = 0;
        $data['pdv'] = 0;
        $data['vendadireta'] = 0;
        switch ($data['status']) {
            case "0":
            $data['convenio'] = 1;
            break;
            case "1":
            $data['disk'] = 1;
            break;
            case "2":
            $data['gasbolso'] = 1;
            break;
            case "3":
            $data['pdv'] = 1;
            break;
            case "4":
            $data['vendadireta'] = 1;
            break;
            default:
            return 'Selecione o Status';
        }
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $pedidooperacao = Pedidooperacao::create($data);
        return 'OK|' . $pedidooperacao->id;
    }
    
    public function update(Request $request, $id, Pedidooperacao $pop)
    {
        $this->authorize('update',$pop);
        $pedidooperacao = Pedidooperacao::findOrFail($id);
        $this->authorize('igualdade',$pedidooperacao);
        $this->definition();
        $this->validate($request, [
            'descricao' => 'required|max:100|unique:pedidooperacaos,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'status' => 'required',
            ]);
        $data = $request->all();
        $data['convenio'] = 0;
        $data['disk'] = 0;
        $data['gasbolso'] = 0;
        $data['pdv'] = 0;
        $data['vendadireta'] = 0;
        switch ($data['status']) {
            case "0":
            $data['convenio'] = 1;
            break;
            case "1":
            $data['disk'] = 1;
            break;
            case "2":
            $data['gasbolso'] = 1;
            break;
            case "3":
            $data['pdv'] = 1;
            break;
            case "4":
            $data['vendadireta'] = 1;
            break;
            default:
            return 'Selecione o Status';
        }
        if (!isset($data['ativo'])) {
            $data['ativo'] = 0;
        }
        if (!isset($data['portaria'])) {
            $data['portaria'] = 0;
        }
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $pedidooperacao->update($data);
        return 'OK|' . $pedidooperacao->id;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Pedidooperacao $pop)
    {
        $this->authorize('delete',$pop);
        $pedidooperacao = Pedidooperacao::find($id);
        $this->authorize('igualdade',$pedidooperacao);
        $this->definition();
        DB::beginTransaction();
        try {
            $pedidooperacao->delete();
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
        $this->pedidoOperacoes = Pedidooperacao::where([
            ['grupo_id', $this->grupo_id]
            ])->get();
        $this->status = [
        '0' => 'Convênio',
        '1' => 'Disk',
        '3' => 'PDV',
        '2' => 'Vale Gás',
        '4' => 'Venda Direta'
        ];
    }

}