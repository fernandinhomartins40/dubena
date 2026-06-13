<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Nfoperacao;
use App\Nfgrupofiscal;
use App\Nfceconfigpedido;
use Illuminate\Http\Request;

class ConfigNfcePedidoController extends Controller
{
    public function index(Nfceconfigpedido $conf)
    {
        $this->authorize('view',$conf);
        $operacoes = Nfoperacao::where('empresa_id', Session::get("empresa_padrao")->id)->select('id', 'descricao')->orderby('descricao')->get();
        $nfgrupofiscals = Nfgrupofiscal::where('empresa_id', Session::get("empresa_padrao")->id)->where('ativo', true)->orderby('descricao')->select('id', 'descricao')->get()->pluck('descricao', 'id')->prepend('Selecione', '');

        $vinculos = Nfceconfigpedido::whereIn('nfoperacao_id', $operacoes->pluck('id'))->with('nfoperacaonova', 'nfoperacao', 'nfgrupofiscal')->orderby('id')->get();
        $operacoes = $operacoes->pluck('descricao', 'id')->prepend('Selecione', '');
        $operacoes2 = $operacoes;
        return view('nf.configpedidos.configpedidos', compact('operacoes', 'operacoes2', 'nfgrupofiscals', 'vinculos'));
    }
    public function store(Request $request, Nfceconfigpedido $conf)
    {
        $this->authorize('create',$conf);
        $data = $request->all();
        DB::beginTransaction();
        try {
            $nfgrupofiscals = collect(json_decode($data['nfgrupofiscals']))->sortBy(1);
            $deleted = collect(json_decode($data['deleted']));

            Nfceconfigpedido::whereIn('nfoperacao_id', array_merge($nfgrupofiscals->pluck('0')->toArray(), $deleted->toArray()))->delete();
            foreach ($nfgrupofiscals as $prod) {
                $config = [];
                $config['nfoperacao_id'] = $prod[0];
                $config['nfgrupofiscal_id'] = $prod[2];
                $config['nfoperacaonova_id'] = $prod[4];
                Nfceconfigpedido::create($config);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return getErrorsException($e);
        }
        DB::commit();
        return "OK|";
    }

    public function searchConfigNfcePedidoByOperacao($nfoperacao_id)
    {
        return Nfceconfigpedido::where('nfoperacao_id', $nfoperacao_id)->with('nfoperacaonova', 'nfoperacao', 'nfgrupofiscal')->get();
    }
}
