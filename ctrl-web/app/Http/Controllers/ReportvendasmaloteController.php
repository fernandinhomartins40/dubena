<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Setor;
use App\Pedido;
use App\Cliente;
use App\Produto;
use App\Segmento;
use App\Services\CarbonCustom as Carbon;
use App\Pedidoitem;
use App\Colaborador;
use App\Pedidooperacao;
use App\Pedidosituacao;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;

class ReportvendasmaloteController extends Controller
{
    private $empresa_id;
    private $repositorio;

    public function __construct(SelectRepository $repositorio){
        $this->repositorio = $repositorio;
    }

    public function vendasMaloteIndex(){
        $this->definition();
        $colaboradores = $this->repositorio->getSetorColaborador()->prepend('Selecione','');
        return view('reports.vendas.colaborador.colaborador_malote')->with(compact('colaboradores'));
    }

    public function vendasMalotePdf(){
        $this->definition();
        $get = $_GET;
        $iframe = $get["iframe"];
        $malote = $this->buscasVendaMalote($get);
        $pedidos = $malote["pedidos"];
        $filtro = $malote["filtro"];
        $titulo = 'Relatório de Malotes por Colaborador';
        if($iframe != 1){
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.colaborador.colaborador_malote_pdf', compact('titulo','filtro','pedidos'));
            return $pdf->stream();
        }else{
            return view('reports.vendas.colaborador.colaborador_malote_pdf')->with(compact('titulo','filtro','pedidos'));
        }
    }

    private function buscasVendaMalote($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $colaborador_id = $filtro["colaborador"] == 0 ? null : $filtro["colaborador"];
        $operadorcol = $colaborador_id == null ? '!=' : '=';
        $realizada = Pedidosituacao::where(['entregafinalizada'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $concluido = Pedidosituacao::where(['fechadoconcluido'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $status = array_merge($realizada,$concluido);
        $pedidos = DB::table('pedidos')->join('condicaopagamentos as condicao','pedidos.condicaopagamento_id','condicao.id')
                        ->join('colaboradors as colaborador','pedidos.colaborador_id','colaborador.id')
                        ->join('pedidoitems as items','pedidos.id','items.pedido_id')
                        ->join('produtos','items.produto_id','produtos.id')
                        ->whereIn('pedidos.pedidosituacao_id',$status)
                        ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                        ->where([
                            ['pedidos.empresa_id',$this->empresa_id],
                            ['pedidos.colaborador_id',$operadorcol,$colaborador_id],
                            ['colaborador.ativo',1],
                            ['items.precovendaunitario','>','0']
                        ])
                        ->select('pedidos.colaborador_id','colaborador.nome as nome',
                            'condicao.id as condicao_id','condicao.descricao as cond_descricao',
                            'pedidos.datahoraprevisaoentrega','pedidos.id as pedido_id',
                            'pedidos.valorvenda','items.quantidade','items.produto_id as produto_id',
                            'items.precovendaunitario','produtos.descricao','items.precovendatotal','pedidos.valordesconto')
                        ->orderBy('colaborador.nome')->orderBy('condicao.descricao')
                        ->orderBy('pedido_id')->get();
        $malote = collect([]);
        $condanterior = null;
        $anterior = null;
        $count = 0;
        $countcol = 0;
        $countcond = 0;
        $cunti = 0;
        foreach($pedidos as $pedido){
            if((!is_null($condanterior) && $condanterior != $pedido->condicao_id) || 
                    (!is_null($condanterior) && $anterior != $pedido->colaborador_id)){
                $wcond = $pedidos->where('condicao_id',$condanterior)
                                    ->where('colaborador_id',$anterior);
                $objtotalcond = (object) array();
                $objtotalcond->quantidadecond = $wcond->sum('quantidade');
                $objtotalcond->totalcond = $wcond->unique('pedido_id')->sum('valorvenda');
                $objtotalcond->condtotaldesc = $wcond->unique('pedido_id')->sum('valordesconto');
                $malote->push($objtotalcond);
            }
            if(!is_null($anterior) && $anterior != $pedido->colaborador_id){
                $wcol = $pedidos->where('colaborador_id',$anterior);
                $objtotalcol = (object) array();
                $objtotalcol->qtdcol = $wcol->sum('quantidade');
                $objtotalcol->totalcol = $wcol->unique('pedido_id')->sum('valorvenda');
                $objtotalcol->coltotaldesc = $wcol->unique('pedido_id')->sum('valordesconto');
                $malote->push($objtotalcol);
            }
            if(is_null($anterior) || $anterior != $pedido->colaborador_id){
                $objcol = (object) array();
                $objcol->colaborador = $pedido->nome;
                $objcol->colaborador_id = $pedido->colaborador_id;
                $malote->push($objcol);
                $countcol++;
            }
            if(is_null($condanterior) || $condanterior != $pedido->condicao_id || $anterior != $pedido->colaborador_id ){
                $objcondicao = (object) array();
                $objcondicao->condicao = $pedido->cond_descricao;
                $objcondicao->condicao_id = $pedido->condicao_id;
                $malote->push($objcondicao);
                $countcond++;
                $cunti = 0;
            }
            $valortotal = $pedidos->unique('pedido_id')->where('pedido_id',$pedido->pedido_id)->sum('valorvenda') + $pedido->valordesconto;
            $objnormal = (object) array();
            $objnormal->pedido_id = $pedido->pedido_id;
            $objnormal->data = requestDataOracle($pedido->datahoraprevisaoentrega,false);
            $objnormal->quantidade = $pedido->quantidade;
            $objnormal->precovenda = $pedido->precovendaunitario * $objnormal->quantidade;
            $objnormal->produto = $pedido->descricao;
            $objnormal->desconto = ($objnormal->precovenda / $valortotal) * $pedido->valordesconto;
            $malote->push($objnormal);
            $condanterior = $pedido->condicao_id;
            $anterior = $pedido->colaborador_id;
            $count++;
            $cunti++;
            if($count == count($pedidos)){
                if($countcond > 1 && $cunti > 1){
                    $wcond = $pedidos->where('condicao_id',$condanterior)
                                    ->where('colaborador_id',$anterior);
                    $objtotalcond = (object) array();
                    $objtotalcond->quantidadecond = $wcond->sum('quantidade');
                    $objtotalcond->totalcond = $wcond->unique('pedido_id')->sum('valorvenda');
                    $objtotalcond->condtotaldesc = $wcond->unique('pedido_id')->sum('valordesconto');
                    $malote->push($objtotalcond);
                }
                if($countcol > 1 && $countcond > 1){
                    $wcol = $pedidos->where('colaborador_id',$anterior);
                    $objtotalcol = (object) array();
                    $objtotalcol->qtdcol = $wcol->sum('quantidade');
                    $objtotalcol->totalcol = $wcol->unique('pedido_id')->sum('valorvenda');
                    $objtotalcol->coltotaldesc = $wcol->unique('pedido_id')->sum('valordesconto');
                    $malote->push($objtotalcol);
                }
                $objtotal = (object) array();
                $objtotal->totalgeral = $malote->sum('precovenda');
                $objtotal->totalquantidade = $malote->sum('quantidade');
                $objtotal->totaldesc = $malote->sum('desconto');
                $malote->push($objtotal);
            }
        }
        $filtro = "Filtro: Período: " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $colaborador_id == null ? $filtro . ", Colaborador: todos." : $filtro . ", Colaborador: " . Colaborador::where('id',$colaborador_id)->pluck('nome')->first() . ".";
        $retorno["pedidos"] = $malote;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    ///////////////// Relatorio Vendas Disk
    public function vendasOperacoes(){
        $this->definition();
        $operacoes = $this->repositorio->getPedidoOperacoes()->prepend('Selecione','');
        $segmentos = $this->repositorio->getSegmentos()->prepend('Selecione','');
        $produtos = $this->repositorio->getProdutos();

        return view('reports.vendas.operacoes.venda_operacoes')->with(compact('operacoes','segmentos','produtos'));
    }

    public function operacoesFiltro(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"];
        $operacao = $this->buscarOperacoes($get);
        $filtro = $operacao["filtro"];
        $operacoes = $operacao["operacoes"];
        $titulo = 'Relatório de Vendas por Operação';
        if($tipo == 1){
            return view('reports.vendas.operacoes.venda_operacoes_pdf')->with(compact('titulo','filtro','operacoes'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.operacoes.venda_operacoes_pdf', compact('titulo','filtro','operacoes'));
            return $pdf->stream();
        }
    }

    private function buscarOperacoes($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $operacao_id = $filtro["operacao"];
        $segmento_id = $filtro["segmento"] == 0 ? null : $filtro["segmento"];
        $produtos_id = $filtro["produto"] == 0 ? null : explode(',',$filtro["produto"]);
        
        $concluido = Pedidosituacao::where(['fechadoconcluido'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $realizado = Pedidosituacao::where(['entregafinalizada'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $situacao = array_merge($concluido,$realizado);
        $pedidos = DB::table('pedidos')->join('pedidoitems as items','items.pedido_id','pedidos.id')
                        ->join('clientes','pedidos.cliente_id','clientes.id')
                        ->join('produtos','items.produto_id','produtos.id')
                        ->join('condicaopagamentos as condicao','pedidos.condicaopagamento_id','condicao.id')
                        ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                        ->whereIn('pedidos.pedidosituacao_id',$situacao)
                        ->where([
                            ['pedidos.empresa_id',$this->empresa_id],
                            ['pedidos.pedidooperacao_id',$operacao_id],
                            ['items.precovendaunitario','>','0']
                        ]);
        if($segmento_id != null)
            $pedidos = $pedidos->where('clientes.segmento_id',$segmento_id);
        if($produtos_id != null)
            $pedidos = $pedidos->whereIn('items.produto_id',$produtos_id);

        $pedidos = $pedidos->select('condicao.descricao as condicao','pedidos.condicaopagamento_id',
                                'items.produto_id','produtos.descricao as produto',
                                DB::raw('sum(items.quantidade) as quantidade'),
                                DB::raw("(sum(items.precovendatotal - (coalesce(pedidos.valordesconto,0))
                                / (coalesce(pedidos.valordesconto,0) + pedidos.valorvenda) * 
                                items.precovendatotal)) as valor_liquido"),
                                DB::raw("((sum(items.precovendatotal - (coalesce(pedidos.valordesconto,0))
                                / (coalesce(pedidos.valordesconto,0) + pedidos.valorvenda) * 
                                items.precovendatotal) / sum(items.quantidade))) as preco_medio"))
                            ->groupBy('condicao.descricao')->groupBy('pedidos.condicaopagamento_id')
                            ->groupBy('items.produto_id')->groupBy('produtos.descricao')
                            ->orderBy('condicao')->orderBy('produto')->get();
        $operacoes = collect([]);
        $anterior = null;
        $count = 0;
        $countcond = 0;
        foreach($pedidos as $pedido){
            if(!is_null($anterior) && $anterior != $pedido->condicaopagamento_id){
                $wcond = $pedidos->where('condicaopagamento_id',$anterior);
                $objtotalcond = (object) array();
                $objtotalcond->quantiatotal = $wcond->sum('quantidade');
                $objtotalcond->vendatotal = $wcond->sum('valor_liquido');
                $operacoes->push($objtotalcond);
            }
            if(is_null($anterior) || $anterior != $pedido->condicaopagamento_id){
                $objcond = (object) array();
                $objcond->condicao_id = $pedido->condicaopagamento_id;
                $objcond->condicao = $pedido->condicao;
                $operacoes->push($objcond);
                $countcond++;
            }
            $objnormal = (object) array();
            $objnormal->produto = $pedido->produto;
            $objnormal->quantidade = $pedido->quantidade;
            $objnormal->valor = $pedido->valor_liquido;
            $objnormal->media = $pedido->preco_medio;
            $operacoes->push($objnormal);
            $anterior = $pedido->condicaopagamento_id;
            $count++;
            if($count == count($pedidos)){
                if($countcond > 1){
                    $wcond = $pedidos->where('condicaopagamento_id',$anterior);
                    $objtotalcond = (object) array();
                    $objtotalcond->quantiatotal = $wcond->sum('quantidade');
                    $objtotalcond->vendatotal = $wcond->sum('valor_liquido');
                    $operacoes->push($objtotalcond);
                }
                $objtotal = (object) array();
                $objtotal->totalquantidade = $operacoes->sum('quantidade');
                $objtotal->totalgeral = $operacoes->sum('valor');
                $operacoes->push($objtotal);
            }
        }
        $filtro = "Filtros: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $filtro . ", Operação: " . Pedidooperacao::where('id',$operacao_id)->pluck('descricao')->first();
        $filtro = $segmento_id == null ? $filtro . ", Segmento: todos" : $filtro . ", Segmento: " . Segmento::where('id',$segmento_id)->pluck('descricao')->first();
        $filtro = $produtos_id == null ? $filtro . ", Produto: todos." : $filtro . ", Produto: " . implode(', ',Produto::whereIn('id',$produtos_id)->pluck('descricao')->toArray());
        $retorno["filtro"] = $filtro;
        $retorno["operacoes"] = $operacoes;
        return $retorno;
    }


    /////////////// Relatorio Vendas Direta
    public function vendasDireta(){
        $this->definition();
        $setores = $this->repositorio->getSetores()->prepend('Selecione','');
        return view('reports.vendas.operacoes.venda_direta')->with(compact('setores'));
    }

    public function diretaFiltro(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"];
        $direta = $this->diretaBusca($get);
        $titulo = 'Relatório de Vendas Direta';
        $diretas = $direta["pedidos"];
        $filtro = $direta["filtro"];
        if($tipo == 1){
            return view('reports.vendas.operacoes.venda_direta_pdf')->with(compact('titulo','filtro','diretas'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.vendas.operacoes.venda_direta_pdf', compact('titulo','filtro','diretas'))->setPaper('a4','landscape');
            return $pdf->stream();
        }
    }

    private function diretaBusca($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $setor = $filtro["setor"] == 0 ? null : $filtro["setor"];
        $concluido = Pedidosituacao::where(['fechadoconcluido'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $realizado = Pedidosituacao::where(['entregafinalizada'=>1,'grupo_id'=>$this->grupo_id])->pluck('id')->toArray();
        $situacao = array_merge($concluido,$realizado);
        $operacao = Pedidooperacao::where('vendadireta',1)->pluck('id')->toArray();
        $clientes = DB::table('clientes')
                        ->leftJoin('setors as setor','clientes.setor_id','setor.id')
                        ->join('pedidos','pedidos.cliente_id','clientes.id')
                        ->join('pedidoitems as items','items.pedido_id','pedidos.id')
                        ->join('produtos','items.produto_id','produtos.id')
                        ->join('cidades','clientes.cidade_id','cidades.id')
                        ->join('bairros','clientes.bairro_id','bairros.id')
                        ->join('ruas','clientes.rua_id','ruas.id')
                        ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                        ->whereIn('pedidos.pedidosituacao_id',$situacao)
                        ->whereIn('pedidos.pedidooperacao_id',$operacao)
                        ->where([
                            ['pedidos.empresa_id',$this->empresa_id],
                            ['items.precovendaunitario','>','0']
                        ]);
        if(!is_null($setor))
            $clientes = $clientes->where('clientes.setor_id',$setor);
        
        $clientes = $clientes->select('clientes.id','cidades.descricao as cidade','bairros.descricao as bairro','pedidos.valordesconto',
                                'ruas.descricao as rua','clientes.numero','clientes.nome','produtos.descricao as produto','pedidos.id as pedido_id',
                                'produtos.id as produto_id','items.quantidade','items.precovendatotal','items.precovendaunitario',
                                'clientes.setor_id','setor.descricao as setor','pedidos.datahoraprevisaoentrega as data','pedidos.valorvenda',
                                DB::raw("(select string_agg(tel.telefone, '' order by tel.telefone) as telefone 
                                from clientetelefones tel where cliente_id = clientes.id and rownum <= 2) as telefone"))
                                ->orderBy('setor')->orderBy('data')->get();
        $pedidos = collect([]);
        $anterior = null;
        $count = 0;
        $countset = 0;
        $nulo = true;
        foreach($clientes as $cliente){
            if(!is_null($anterior) && $anterior != $cliente->setor_id){
                $wset = $clientes->where('setor_id',$anterior);
                $objtotalset = (object) array();
                $objtotalset->quantidadetotal = $wset->sum('quantidade');
                $objtotalset->total = $wset->unique('pedido_id')->sum('valorvenda');
                $pedidos->push($objtotalset);
            }
            if((is_null($anterior) || $anterior != $cliente->setor_id) && $cliente->setor_id != null){
                $objsetor = (object) array();
                $objsetor->setor_id = $cliente->setor_id;
                $objsetor->setor = $cliente->setor;
                $pedidos->push($objsetor);
                $countset++;
            }
            if($cliente->setor_id == null && $nulo){
                $objnulo = (object) array();
                $objnulo->setor_id = $cliente->setor_id;
                $objnulo->setor = "Sem Setor";
                $pedidos->push($objnulo);
                $nulo = false;
            }
            $valortotal = $clientes->unique('pedido_id')->where('pedido_id',$cliente->pedido_id)->sum('valorvenda') + $cliente->valordesconto;
            $objnormal = (object) array();
            $objnormal->data = requestDataOracle($cliente->data,false);
            $objnormal->razao = $cliente->nome;
            $objnormal->endereco = "$cliente->bairro - $cliente->rua, $cliente->numero.";
            $telefone = $cliente->telefone;
            $telefone = str_replace('(','',$telefone);
            $telefone = str_replace(')','',$telefone);
            $objnormal->telefone = $telefone;
            $objnormal->produto = $cliente->produto;
            $objnormal->quantidade = $cliente->quantidade;
            $valor = $cliente->precovendaunitario * $objnormal->quantidade;
            $desconto = ($valor / $valortotal) * $cliente->valordesconto;
            $objnormal->valor = $valor - $desconto;
            $pedidos->push($objnormal);
            $anterior = $cliente->setor_id;
            $count++;
            if(count($clientes) == $count){
                if($countset > 1){
                    $wset = $clientes->where('setor_id',$anterior);
                    $objtotalset = (object) array();
                    $objtotalset->quantidadetotal = $wset->sum('quantidade');
                    $objtotalset->total = $wset->unique('pedido_id')->sum('valorvenda');
                    $pedidos->push($objtotalset);
                }
                $objtotal = (object) array();
                $objtotal->quantidadetotal = $pedidos->sum('quantidade');
                $objtotal->totalgeral = $pedidos->sum('valor');
                $pedidos->push($objtotal);
            }
        }
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $setor == null ? $filtro . ", Setor: todos." : $filtro . ", Setor: " . Setor::where('id',$setor)->pluck('descricao')->first().".";
        $retorno["filtro"] = $filtro;
        $retorno["pedidos"] = $pedidos;
        return $retorno;
    }

    private function definition(){
        //Busca id da empresa padrão
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //Busca id do grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //Set Empresa ID
        $this->repositorio->setEmpresa($this->empresa_id);
        //Set grupo id
        $this->repositorio->setGrupo($this->grupo_id);
    }
}

/*

select "CONDICAO"."DESCRICAO" as "CONDICAO", "PEDIDOS"."CONDICAOPAGAMENTO_ID",
"ITEMS"."PRODUTO_ID", "PRODUTOS"."DESCRICAO" as "PRODUTO", SUM("ITEMS"."QUANTIDADE") as quantidade,
sum(precovendatotal - (coalesce(valordesconto,0)/(coalesce(valordesconto,0)+valorvenda)*precovendatotal)) as valorliq,
sum(precovendatotal - (coalesce(valordesconto,0)/(coalesce(valordesconto,0)+valorvenda)*precovendatotal))/SUM("ITEMS"."QUANTIDADE") as preco_medio
from "PEDIDOS" 
inner join "PEDIDOITEMS" items on "ITEMS"."PEDIDO_ID" = "PEDIDOS"."ID" 
inner join "PRODUTOS" on "ITEMS"."PRODUTO_ID" = "PRODUTOS"."ID" 
inner join "CLIENTES" on "PEDIDOS"."CLIENTE_ID" = "CLIENTES"."ID" 
inner join "SEGMENTOS" on "CLIENTES"."SEGMENTO_ID" = "SEGMENTOS"."ID" 
inner join "CONDICAOPAGAMENTOS" condicao on "PEDIDOS"."CONDICAOPAGAMENTO_ID" = "CONDICAO"."ID" 
where "PEDIDOS"."DATAHORAPREVISAOENTREGA" between to_date('2017-08-15 00:00:00','yyyy-mm-dd hh24:mi:ss') and to_date('2017-08-17 23:59:59','yyyy-mm-dd hh24:mi:ss')
and "PEDIDOS"."PEDIDOSITUACAO_ID" in (21, 24) and ("PEDIDOS"."EMPRESA_ID" = 1
and "PEDIDOS"."PEDIDOOPERACAO_ID" = 21 and "ITEMS"."PRECOVENDAUNITARIO" > 0)
and "CLIENTES"."SEGMENTO_ID" = 22 
group by "CONDICAO"."DESCRICAO", "PEDIDOS"."CONDICAOPAGAMENTO_ID", "ITEMS"."PRODUTO_ID","PRODUTOS"."DESCRICAO"
order by "CONDICAO" asc,  "PRODUTO" asc

*/