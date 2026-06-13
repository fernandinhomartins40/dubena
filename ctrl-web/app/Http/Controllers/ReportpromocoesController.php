<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Cliente;
use App\Promocao;
use App\Pedidosituacao;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;

class ReportpromocoesController extends Controller
{
    private $grupo_id;
    private $empresa_id;
    private $repositorio;

    function __construct(SelectRepository $repositorio){
        $this->repositorio = $repositorio;
    }

    public function indexAcompanhamento(){
        $this->definition();
        $promocoes = $this->repositorio->getPromocoes()->prepend('Selecione','');
        return view('reports.clientes.promocoes.promo_index')->with(compact('promocoes'));
    }

    public function filtroAcompanhamento(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"];
        $promo = $this->filtrarPromo($get);
        $clientes = $promo["clientes"];
        $filtro = $promo["filtro"];
        $titulo = "Relatório de Acompanhamento de Promoção";
        if($tipo == 1){
            return view('reports.clientes.promocoes.promo_pdf')->with(compact('titulo','clientes','filtro'));
        }
    }

    private function filtrarPromo($filtro){
        $promo_id = $filtro["promocao"];
        $quantidade = $filtro["compras"];
        $operador = $filtro["operador"];
        $opquantidade = $operador == 1 ? '>' : '<';
        
        $situacao = DB::table('pedidosituacaos')
            ->whereRaw('grupo_id = ' . $this->grupo_id . ' and ativo = 1 and (fechadoconcluido = 1 or entregafinalizada = 1)')
            ->pluck('id')->toArray();
        $impsit = implode(',', $situacao);

        $promo = DB::table('clientepromocaos as promo')
                    ->join('promocaos','promo.promocao_id','promocaos.id')
                    ->join('clientes','promo.cliente_id','clientes.id')
                    ->join('pedidos','pedidos.cliente_id','clientes.id')
                    ->leftJoin('setors as setor','clientes.setor_id','setor.id')
                    ->leftJoin('pedidoitems as items',function($join){
                        $join->on('items.pedido_id','pedidos.id');
                        $join->on('items.produto_id','promocaos.produto_id');
                    })
                    ->join('bairros','clientes.bairro_id','bairros.id')
                    ->join('cidades','clientes.cidade_id','cidades.id')
                    ->join('ruas','clientes.rua_id','ruas.id')
                    ->whereIn('pedidos.pedidosituacao_id',$situacao)
                    ->whereRaw('pedidos.datahoraprevisaoentrega between promo.datainicio and promo.datafim')
                    ->where([
                        ['clientes.ativo',1],
                        ['promocaos.ativo',1],
                        ['promo.promocao_id',$promo_id],
                        ['clientes.empresa_id',$this->empresa_id]
                    ])
                    ->select('setor.descricao as setor','clientes.setor_id','clientes.nome','clientes.numero',
                        'cidades.descricao as cidade','bairros.descricao as bairro','ruas.descricao as rua','clientes.id',
                        DB::raw("(SUM(CASE WHEN promocaos.produto_id IS NULL ".
                                        "THEN 1 ".
                                        "ELSE ".
                                            "CASE WHEN items.id IS NULL ".
                                                "THEN 0 ".
                                                "ELSE 1 ".
                                            "END ".
                                    "END)) as compras"),
                        DB::raw('max(pedidos.datahoraprevisaoentrega) as dataultima'),
                        DB::raw("(select listagg(tel.telefone,', ') within group (order by tel.telefone) from clientetelefones tel 
                        where tel.cliente_id = clientes.id and rownum <= 2) as telefone"))
                    ->groupBy('setor.descricao','clientes.setor_id','clientes.nome','clientes.id',
                        'cidades.descricao','bairros.descricao','ruas.descricao','clientes.numero')
                    ->orderBy('setor')->orderBy('clientes.nome')->get();

        $clientes = collect([]);
        $anterior = null;
        $count = 0;
        $nulo = true;
        $countset = 0;
        $sumcom = 0;
        $countpromo = 0;
        foreach($promo as $cliente){
            $quant = $cliente->compras;

            if ($operador == 1) 
                $allowed = $quant > $quantidade;
            else 
                $allowed = $quant <= $quantidade;

            if ($allowed) {
                if(!is_null($anterior) && $anterior != $cliente->setor_id && $nulo){
                    $objtotal = (object) array();
                    $objtotal->clientestotal = $countpromo;
                    $objtotal->comprastotal = $sumcom;
                    $clientes->push($objtotal);
                    $sumcom = 0;
                    $countpromo = 0;
                }
                if($cliente->setor_id == null && !$nulo){
                    $wpro = $promo->where('setor_id',null);
                    $objtotalnull = (object) array();
                    $objtotalnull->clientestotal = $countpromo;
                    $objtotalnull->comprastotal = $sumcom;
                    $clientes->push($objtotalnull);
                    $sumcom = 0;
                    $countpromo = 0;
                }
                if(!is_null($cliente->setor_id) && (is_null($anterior) || $anterior != $cliente->setor_id)){
                    $objsetor = (object) array();
                    $objsetor->setor_id = $cliente->setor_id;
                    $objsetor->setor = $cliente->setor;
                    $clientes->push($objsetor);
                    $countset++;
                    $sumcom = 0;
                    $countpromo = 0;
                }
                if($cliente->setor_id == null && $nulo){
                    $objsetnull = (object) array();
                    $objsetnull->setor_id = $cliente->setor_id;
                    $objsetnull->setor = "Sem Setor";
                    $clientes->push($objsetnull);
                    $nulo = false;
                    $countset++;
                    $sumcom = 0;
                    $countpromo = 0;
                }
                $objnormal = (object) array();
                $objnormal->cliente = $cliente->nome;
                $objnormal->telefone = $cliente->telefone;
                $objnormal->endereco = "$cliente->bairro - $cliente->rua, $cliente->numero";
                $objnormal->ultima = requestDataOracle($cliente->dataultima,false);
                $objnormal->quantidade = $cliente->compras;
                $clientes->push($objnormal);
                $anterior = $cliente->setor_id;
                $count++;
                $countpromo++;
                $sumcom += $cliente->compras;
            }
        }
        if(count($clientes) > 0){
            if($countset > 1){
                $objtotal = (object) array();
                $objtotal->clientestotal = $countpromo;
                $objtotal->comprastotal = $sumcom;
                $clientes->push($objtotal);
            }
            $objtotalgeral = (object) array();
            $objtotalgeral->total = $count;
            $objtotalgeral->totalgeral = $clientes->sum('quantidade');
            $clientes->push($objtotalgeral);
        }
        $filtro = "FIltros: Promoção: " . Promocao::where('id',$promo_id)->pluck('descricao')->first();
        $filtro = $operador == 1 ? $filtro . " com compras acima de $quantidade." : $filtro . " com compras ou abaixo de $quantidade.";
        $retorno["clientes"] = $clientes;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    private function definition(){
        //busca Id da empresa logada
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca grupo da empresa logada
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //set empresa no repositorio
        $this->repositorio->setEmpresa($this->empresa_id);
        //set grupo no repositorio
        $this->repositorio->setGrupo($this->grupo_id);
    }
}