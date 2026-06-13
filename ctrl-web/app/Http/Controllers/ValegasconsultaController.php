<?php

namespace App\Http\Controllers;

use App\Valegas;
use App\Cliente;
use App\Valegassituacao;
use Illuminate\Support\Facades\Session;

class ValegasconsultaController extends Controller
{

    public function index(Valegas $vl)
    {
        $this->authorize('viewConsulta',$vl);
        $situacoes = Valegassituacao::orderBy('descricao')->get()->pluck('descricao', 'id')->prepend('Selecione', '');
        $clientes = Cliente::rightJoin('valegasvendas as vendas','vendas.cliente_id','clientes.id')
                        ->where(['clientes.ativo' => 1, 'clientes.empresa_id' => Session::get('empresa_padrao')->id])
                        ->orderBy('nome')->select('clientes.nome', 'clientes.id')->pluck('clientes.nome', 'clientes.id')->prepend('Selecione', '');
        $valegasitens = Valegas::with('valeGasSituacao')->where('empresa_id', Session::get('empresa_padrao')->id);

        if (isset($_GET['codigo']) && !empty($_GET['codigo']))
            $valegasitens->where('codigo', $_GET['codigo']);

        if (isset($_GET['situacao']) && !empty($_GET['situacao']))
            $valegasitens->where('valegassituacao_id', $_GET['situacao']);

        if (isset($_GET['cliente_id']) && !empty($_GET['cliente_id']))
            $valegasitens->where('cliente_id', $_GET['cliente_id']);

        $valegasitens = $valegasitens->orderBy("valegas.id", "desc")->take(300)->get();
        return View('valegas.consulta.valegasconsulta', compact('valegasitens', 'situacoes', 'clientes'));
    }

}
