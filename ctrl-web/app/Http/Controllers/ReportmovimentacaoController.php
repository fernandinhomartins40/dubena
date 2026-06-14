<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Setor;
use App\Produto;
use App\Services\CarbonCustom as Carbon;
use App\Estoquefechamento;
use Illuminate\Http\Request;
use App\Estoquesetorhistorico;
use App\Estoquefechamentosetor;
use App\Repository\SelectRepository;

class ReportmovimentacaoController extends Controller
{
    private $filtros;
    private $grupo_id;
    private $empresa_id;
    private $repository;

    function __construct(SelectRepository $repository){
        $this->repository = $repository;
    }
    
    public function index(){
        $this->definition();
        $setores = $this->repository->getSetores()->prepend("Selecione","");
        $produtos = $this->repository->getProdutos()->prepend('Selecione','');

        return view('reports.estoque.movimentacoes.mov_index')->with(compact('setores','produtos'));
    }

    public function movimentacaoFiltro(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"] == "1";
        $titulo = "Relatório de Movimentações de Estoque";
        $movimentacoes = $this->buscaMovimentacoes($get);
        $filtro = $this->filtros;
        if($tipo){
            return view('reports.estoque.movimentacoes.mov_pdf')->with(compact('titulo','filtro','movimentacoes'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.estoque.movimentacoes.mov_pdf', compact('titulo','filtro','movimentacoes'));
            return $pdf->setPaper('a4','landscape')->stream();
        }
    }

    private function buscaMovimentacoes($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $produto_id = $filtro["produto"];
        $setor_id = $filtro["setor"];
        $tipo = $filtro["filtro"];
        $tipodata = $tipo == 'C' ? 'datahoracompetencia' : 'datahora';

        $saldoInicial = $this->getSaldo($datainicio, $datafim, $setor_id, $produto_id);
        $mov = $this->getHistorico($datainicio, $datafim, $produto_id, $setor_id, $tipodata);
        $movimentacoes = $this->formarTabela($mov, $saldoInicial);
        $this->setFiltros($datainicio, $datafim, $setor_id, $produto_id,$tipo);
        return $movimentacoes;
    }

    private function getSaldo($datainicio,$datafim,$setor_id,$produto_id){
        $empresa = Session::get('empresa_padrao')->id;
        $query = $this->getQueryHistorico($datainicio, $empresa, $setor_id, $produto_id);
        $queryBkp = $this->getQueryHistoricoBackup($datainicio, $empresa, $setor_id, $produto_id);
        $saldo = collect(DB::select($query))->first();
        if(is_null($saldo->quantidade)){
            $saldo = collect(DB::select($queryBkp))->first();
        }
        return $saldo->quantidade;
    }

    private function getHistorico($datainicio,$datafim,$produto_id,$setor_id,$tipodata){
        $movimentacao = DB::table('estoquesetorhistoricos as historico')
                    ->join('produtos','historico.produto_id','produtos.id')
                    ->join('setors as setor','historico.setor_id','setor.id')
                    ->join('users','historico.user_id','users.id')
                    ->whereBetween("historico.$tipodata",[$datainicio,$datafim])
                    ->where([
                        ['historico.setor_id',$setor_id],
                        ['historico.produto_id',$produto_id],
                        ['historico.empresa_id',$this->empresa_id]
                    ])
                    ->select('historico.id','historico.setor_id','setor.descricao as setor','historico.movimentacao','users.name','historico.entidade',
                        'produtos.descricao as produto','historico.quantidade','historico.entidade_id','historico.motivo',"historico.$tipodata as data")
                    ->orderBy("historico.$tipodata")->get();

        return $movimentacao;
    }

    private function formarTabela($movimentacao,$saldoInicial){
        $movimentacoes = collect([]);
        $setanterior = null;
        $saldo = $saldoInicial;
        $count = 0;
        $first = true;
        foreach ($movimentacao as $mov) {
            $count++;
            $last = count($movimentacao) == $count;
            if(is_null($setanterior) || $setanterior != $mov->setor_id){
                $objsetor = (object) array();
                $objsetor->setor_id = $mov->setor_id;
                $objsetor->setor = $mov->setor;
                $movimentacoes->push($objsetor);
            }
            $objnormal = (object) array();
            $objnormal->data = requestDataOracle($mov->data,true,true,false);
            $objnormal->movimentacao = $mov->movimentacao == "SAIDA" ? "SAÍDA" : "ENTRADA";
            $objnormal->quantidade = $mov->quantidade;
            $objnormal->produto = $mov->produto;
            $objnormal->origem = $mov->entidade_id;
            $objnormal->motivo = $mov->motivo;
            $objnormal->user = $mov->name;
            $objnormal->inicial = $saldo;
            $objnormal->first = $first;
            $objnormal->last = $last;
            $saldo = $mov->movimentacao == "ENTRADA" ? $saldo + $objnormal->quantidade : $saldo - $objnormal->quantidade;
            $objnormal->final = $saldo;
            $movimentacoes->push($objnormal);
            $setanterior = $mov->setor_id;
            $first = false;
        }
        return $movimentacoes;
    }

    private function setFiltros($datainicio,$datafim,$setor_id,$produto_id,$tipo){
        $this->filtros = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $this->filtros .= ", Setor: " . Setor::where('id',$setor_id)->pluck('descricao')->first();
        $this->filtros .= ", Produto: " . Produto::where('id',$produto_id)->pluck('descricao')->first();
        $this->filtros .= $tipo === 'C' ? ", Filtrado Por: Data de Competência" : ", Filtrado Por: Data de Movimentação";
    }

    private function definition(){
        //define id da empresa
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //define grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //define empresa repository
        $this->repository->setEmpresa($this->empresa_id);
        //define grupo repository
        $this->repository->setGrupo($this->grupo_id);
    }

    private function getQueryHistorico($datainicio,$empresa,$setor_id,$produto_id)
    {
        $query = "select sum(quantidade) as quantidade ".
        "from( ".
          "select quantidade ".
          "from estoquefechamentosetors ".
          "where empresa_id = $empresa and setor_id = $setor_id and produto_id = $produto_id and ".
          // Oracle "(select id from (ordenado) where rownum<=1)" = pega o mais
          // recente. Postgres: order by ... desc LIMIT 1 direto na subquery.
          "estoquefechamento_id = (select id ".
                "from estoquefechamentos where empresa_id = $empresa and ".
                "datahorafechamento::date <= to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') and ".
                "reaberto = 0 ".
                "order by datahorafechamento desc limit 1) ".
          
          "union all ".
          
          "select sum((case when movimentacao = 'ENTRADA' then quantidade else quantidade * -1 end)) as quantidade ".
          "from estoquesetorhistoricos hist ".
          "where empresa_id = $empresa and setor_id = $setor_id and produto_id = $produto_id and ".
          "datahoracompetencia::date > (select max(datahorafechamento) from estoquefechamentos where empresa_id = $empresa and ".
            "datahorafechamento::date < to_date('$datainicio','yyyy-mm-dd hh24:mi:ss')) and ".
          "datahoracompetencia::date < to_date('$datainicio','yyyy-mm-dd hh24:mi:ss') ".
        ") total";

        return $query;
    }

    private function getQueryHistoricoBackup($datainicio,$empresa,$setor_id,$produto_id)
    {
        $query = "select sum((case when movimentacao = 'ENTRADA' then quantidade else quantidade * -1 end)) as quantidade " .
            "from estoquesetorhistoricos hist " .
            "where empresa_id = $empresa and " .
            "setor_id = $setor_id and produto_id = $produto_id and " .
            "datahoracompetencia::date <= to_date('$datainicio','yyyy-mm-dd hh24:mi:ss')";
        return $query;
    }
}