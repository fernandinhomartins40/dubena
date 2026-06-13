<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Services\CarbonCustom as Carbon;
use App\Colaborador;
use App\Pedidosituacao;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;

class ReportcomissoesController extends Controller
{
    private $repository;
    private $empresa_id;
    private $grupo_id;

    public function __construct(SelectRepository $repository){
        $this->repository = $repository;
    }

    public function comissoesIndex(){
        $this->definition();
        $colaboradores = $this->repository->getSetorColaborador()->prepend("Selecione","");
        $colaboradoreston = $this->repository->getColaboradorComissaoTon()->prepend("Selecione","");
        return view('reports.vendas.entregador.comissoes.entregador_comissao')->with(compact('colaboradores', 'colaboradoreston'));
    }

    public function filtroComissao(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"];
        $resumo = $get["resumo"];
        $tonelagem = $get["ton"] == 1;
        if($tonelagem){
            return $this->filtroComissaoTonelagem($get);
        }
        if($resumo=="1"){
            $com = $this->buscaComissoesResumo($get);
        } else {
            $com = $this->buscaComissoes($get);
        }
        $filtro = $com["filtro"];
        $comissoes = $com["comissoes"];
        $titulo = "Relatório de Comissões";
        if($tipo == 1){
            return view('reports.vendas.entregador.comissoes.entregador_comissao_pdf')->with(compact('titulo','filtro','comissoes', 'resumo'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('a4','landscape')->loadView('reports.vendas.entregador.comissoes.entregador_comissao_pdf', compact('titulo','filtro','comissoes', 'resumo'));
            return $pdf->stream();
        }
    }

    private function buscaComissoes($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $colaborador_id = $filtro["colaborador"] == 0 ? null : $filtro["colaborador"];

        $comissionados = DB::table('pedidos')
                            ->join('pedidoitems as items','items.pedido_id','pedidos.id')
                            ->join('produtos','items.produto_id','produtos.id')
                            ->join('colaboradors as colaborador','pedidos.colaborador_id','colaborador.id')
                            ->join('condicaopagamentos as condicao','pedidos.condicaopagamento_id','condicao.id')
                            ->join('clientes','pedidos.cliente_id','clientes.id')
                            ->leftJoin('clienteconvenios as con', 'clientes.convenio_id', 'con.cliente_id')
                            ->leftJoin('colaboradorcomissaos as comissionados',function($join){
                                $join->on('comissionados.colaborador_id','pedidos.colaborador_id');
                                $join->on('comissionados.produto_id','items.produto_id');
                                $join->on('comissionados.condicaopagamento_id','pedidos.condicaopagamento_id');
                                $join->on('comissionados.setor_id','pedidos.entregasetor_id');
                                $join->on('comissionados.datainicio','<=',DB::raw('pedidos.datahoraprevisaoentrega'));
                                $join->on('comissionados.datafim','>=',DB::raw('pedidos.datahoraprevisaoentrega'));
                            })
                            ->leftJoin('comissaoexcecoes as excessoes',function($join){
                                $join->on('excessoes.colaboradorcomissao_id','comissionados.id');
                                $join->on('excessoes.segmento_id','clientes.segmento_id');
                            })
                            ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                            ->where([
                                    ['pedidos.empresa_id',$this->empresa_id],
                                    ['items.precovendaunitario','>','0']
                                ])
                            ->whereRaw("pedidos.pedidosituacao_id in (SELECT id FROM pedidosituacaos WHERE (fechadoconcluido = 1 or entregafinalizada = 1) and grupo_id = $this->grupo_id)");
        if(!is_null($colaborador_id))
            $comissionados = $comissionados->where('colaborador.id',$colaborador_id);
        $comissionados = $comissionados->select('pedidos.id as pedido_id','colaborador.id as colaborador_id','colaborador.nome',
                                            DB::raw('coalesce(case when condicao.tipo = 4 and con.comissaodestino = 2 then con.comissao else 0 end, 0) as comissao_convenio'),
                                            'pedidos.condicaopagamento_id','condicao.descricao as condicao','items.produto_id',
                                            'produtos.descricao as produto','pedidos.datahoraprevisaoentrega','items.quantidade',
                                            'items.precovendaunitario','pedidos.valorvenda','comissionados.tipocomissao','pedidos.valordesconto',
                                            DB::raw('case when pedidos.apipedido_id is null then comissionados.percentual else comissionados.percentualapp end as percentual'),
                                            DB::raw('case when pedidos.apipedido_id is null then comissionados.empresavalor else comissionados.empresavalorapp end as empresavalor'),
                                            'excessoes.tipoexcecao',
                                            DB::raw('case when pedidos.apipedido_id is null then excessoes.valorexcecao else excessoes.valorexcecaoapp end as valorexcecao'),
                                            'excessoes.segmento_id as excessoessegmento','clientes.segmento_id',
                                            'excessoes.colaboradorcomissao_id','comissionados.id as comissionados_id', 'pedidos.apipedido_id')
                                        ->orderBy('colaborador.nome')->orderBy('pedido_id')->orderBy('produto')->get();
        $comissoes = collect([]);
        $anterior = null;
        $count = 0;
        $countcol = 0;
        $quantidade = 0;
        $valor = 0;
        $percentual = 0;
        $repasse = 0;
        $valorvenda = 0;
        foreach($comissionados as $comissao){
            if(!is_null($anterior) && $anterior != $comissao->colaborador_id){
                $objtotal = (object) array();
                $objtotal->colquantidade = $quantidade;
                $objtotal->colvalor = $valor;
                $objtotal->colpercentual = $percentual;
                $objtotal->colrepasse = $repasse;
                $objtotal->coltotal = $objtotal->colrepasse + $objtotal->colpercentual;
                $comissoes->push($objtotal);
                $quantidade = 0;
                $valor = 0;
                $percentual = 0;
                $repasse = 0;
                $valorvenda = 0;
            }

            if(is_null($anterior) || $anterior != $comissao->colaborador_id){
                $objentregador = (object) array();
                $objentregador->colaborador_id = $comissao->colaborador_id;
                $objentregador->colaborador = $comissao->nome;
                $comissoes->push($objentregador);
                $countcol++;
            }

            $valorbruto = $comissao->valorvenda + $comissao->valordesconto;
            $excessao = $comissao->excessoessegmento != null;
            $objnormal = (object) array();
            $objnormal->colaborador_id = $comissao->colaborador_id;
            $objnormal->pedido_id = $comissao->pedido_id.($comissao->apipedido_id?" *":"");
            $objnormal->condicao = $comissao->condicao;
            $objnormal->data = requestDataOracle($comissao->datahoraprevisaoentrega,false);
            $objnormal->produto = $comissao->produto;
            $objnormal->quantidade = $comissao->quantidade;
            $objnormal->valor = $comissao->precovendaunitario * $objnormal->quantidade;
            if($comissao->comissao_convenio > 0){
                $objnormal->valor = $objnormal->valor -  ($objnormal->valor * $comissao->comissao_convenio/100);
            }
            $objnormal->percentual_comissao = $comissao->percentual;
            $proporcional = ($objnormal->valor - ($objnormal->valor / $valorbruto) * $comissao->valordesconto);
            $objnormal->valor = $proporcional;
            if($excessao && $comissao->tipoexcecao == 1){
                $objnormal->percentual = ($objnormal->valor * $comissao->valorexcecao / 100);
                $objnormal->repasse = 0;
                $objnormal->percentual_comissao = $comissao->valorexcecao;
            }else if($excessao && $comissao->tipoexcecao = 2){
                $objnormal->percentual = 0;
                $objnormal->repasse = ($proporcional - ($comissao->valorexcecao * $objnormal->quantidade));
                $objnormal->percentual_comissao = $comissao->valorexcecao;
            }else if($comissao->tipocomissao == 1){
                $objnormal->percentual = ($objnormal->valor * $comissao->percentual / 100);
                $objnormal->repasse = 0;
                $objnormal->percentual_comissao = $comissao->percentual;
            }else if ($comissao->tipocomissao == 2){
                $objnormal->percentual = 0;
                $objnormal->repasse = ($proporcional - ($comissao->empresavalor * $objnormal->quantidade));
                $objnormal->percentual_comissao = $comissao->empresavalor;
            }else{
                $objnormal->percentual = 0;
                $objnormal->repasse = 0;
                $objnormal->percentual_comissao = 0;
            }
            $comissoes->push($objnormal);
            $anterior = $comissao->colaborador_id;

            $quantidade += $objnormal->quantidade;
            $valor += $objnormal->valor;
            $percentual += $objnormal->percentual;
            $repasse += $objnormal->repasse;

            $count++;
            if($count == count($comissionados)){
                if($countcol > 1){
                    $objtotal = (object) array();
                    $objtotal->colquantidade = $quantidade;
                    $objtotal->colvalor = $valor;
                    $objtotal->colpercentual = $percentual;
                    $objtotal->colrepasse = $repasse;
                    $objtotal->coltotal = $objtotal->colrepasse + $objtotal->colpercentual;
                    $comissoes->push($objtotal);
                }
                $objtotalgeral = (object) array();
                $objtotalgeral->quantidadetotal = $comissoes->sum('quantidade');
                $objtotalgeral->valortotal = $comissoes->sum('valor');
                $objtotalgeral->percentualtotal = $comissoes->sum('percentual');
                $objtotalgeral->repassetotal = $comissoes->sum('repasse');
                $objtotalgeral->totalgeral = $objtotalgeral->percentualtotal + $objtotalgeral->repassetotal;
                $comissoes->push($objtotalgeral);
            }
        }
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $colaborador_id == null ? $filtro . ", Colaborador: todos." : $filtro . ", Colaborador: " . Colaborador::where('id',$colaborador_id)->pluck('nome')->first().".";
        $retorno["filtro"] = $filtro;
        $retorno["comissoes"] = $comissoes;
        return $retorno;
    }

    private function buscaComissoesResumo($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $colaborador_id = $filtro["colaborador"] == 0 ? null : $filtro["colaborador"];

        $comissionados = DB::table('pedidos')
                            ->join('pedidoitems as items','items.pedido_id','pedidos.id')
                            ->join('produtos','items.produto_id','produtos.id')
                            ->join('colaboradors as colaborador','pedidos.colaborador_id','colaborador.id')
                            ->join('condicaopagamentos as condicao','pedidos.condicaopagamento_id','condicao.id')
                            ->join('clientes','pedidos.cliente_id','clientes.id')
                            ->leftJoin('clienteconvenios as con', 'clientes.convenio_id', 'con.cliente_id')
                            ->leftJoin('colaboradorcomissaos as comissionados',function($join){
                                $join->on('comissionados.colaborador_id','pedidos.colaborador_id');
                                $join->on('comissionados.produto_id','items.produto_id');
                                $join->on('comissionados.condicaopagamento_id','pedidos.condicaopagamento_id');
                                $join->on('comissionados.setor_id','pedidos.entregasetor_id');
                                $join->on('comissionados.datainicio','<=',DB::raw('pedidos.datahoraprevisaoentrega'));
                                $join->on('comissionados.datafim','>=',DB::raw('pedidos.datahoraprevisaoentrega'));
                            })
                            ->leftJoin('comissaoexcecoes as excessoes',function($join){
                                $join->on('excessoes.colaboradorcomissao_id','comissionados.id');
                                $join->on('excessoes.segmento_id','clientes.segmento_id');
                            })
                            ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                            ->where([
                                    ['pedidos.empresa_id',$this->empresa_id],
                                    ['items.precovendaunitario','>','0'],
                                    //['pedidos.id', 49175]
                                ])
                            ->whereRaw("pedidos.pedidosituacao_id in (SELECT id FROM pedidosituacaos WHERE (fechadoconcluido = 1 or entregafinalizada = 1) and grupo_id = $this->grupo_id)");
        if(!is_null($colaborador_id))
            $comissionados = $comissionados->where('colaborador.id',$colaborador_id);
        $comissionados = $comissionados->select('pedidos.id as pedido_id','colaborador.id as colaborador_id','colaborador.nome',
                                            DB::raw('coalesce(case when condicao.tipo = 4 and con.comissaodestino = 2 then con.comissao else 0 end, 0) as comissao_convenio'),
                                            'pedidos.condicaopagamento_id','condicao.descricao as condicao','items.produto_id',
                                            'produtos.descricao as produto','pedidos.datahoraprevisaoentrega','items.quantidade',
                                            'items.precovendaunitario','pedidos.valorvenda','comissionados.tipocomissao','pedidos.valordesconto',
                                            DB::raw('case when pedidos.apipedido_id is null then comissionados.percentual else comissionados.percentualapp end as percentual'),
                                            DB::raw('case when pedidos.apipedido_id is null then comissionados.empresavalor else comissionados.empresavalorapp end as empresavalor'),
                                            'excessoes.tipoexcecao',
                                            DB::raw('case when pedidos.apipedido_id is null then excessoes.valorexcecao else excessoes.valorexcecaoapp end as valorexcecao'),
                                            'excessoes.segmento_id as excessoessegmento','clientes.segmento_id',
                                            'excessoes.colaboradorcomissao_id','comissionados.id as comissionados_id', 'apipedido_id')
                                        ->orderBy('colaborador.nome')->orderBy('pedidos.condicaopagamento_id')->orderBy('items.produto_id')
                                        ->orderBy('comissionados.percentual')->orderBy('excessoes.valorexcecao')->get();
        $comissoes = collect([]);
        $anterior = null;
        $condicaoProdAnterior = null;
        $condicaoAnterior = null;
        $produtoAnterior = null;
        //$percentualComissaoAnterior = null;
        $count = 0;
        $countcol = 0;
        $quantidade = 0;
        $valor = 0;
        $percentual = 0;
        $repasse = 0;
        $quantidadeTotal = 0;
        $valorTotal = 0;
        $percentualTotal = 0;
        $repasseTotal = 0;
        $quantidadeCondicao = 0;
        $valorCondicao = 0;
        $percentualCondicao = 0;
        $repasseCondicao = 0;
        $valorvenda = 0;
        foreach($comissionados as $comissao){
            $condicaoProdAtual = $comissao->condicaopagamento_id.'|'.$comissao->produto_id; // .($comissao->excessoessegmento != null?$comissao->valorexcecao:$comissao->percentual);

            if(!is_null($anterior) && $anterior != $comissao->colaborador_id){
                $objCondicao = (object) array();
                $objCondicao->colaborador_id = $comissao->colaborador_id;
                $objCondicao->pedido_id = "";
                $objCondicao->condicao = $condicaoAnterior;
                $objCondicao->data = null;
                $objCondicao->produto = $produtoAnterior;
                $objCondicao->quantidade =$quantidadeCondicao;
                $objCondicao->valor = $valorCondicao;
                $objCondicao->percentual =$percentualCondicao;
                $objCondicao->repasse = $repasseCondicao;
                $objCondicao->percentual_comissao = null; // $percentualComissaoAnterior;
                $comissoes->push($objCondicao);
                $objtotal = (object) array();
                $objtotal->colquantidade = $quantidade;
                $objtotal->colvalor = $valor;
                $objtotal->colpercentual = $percentual;
                $objtotal->colrepasse = $repasse;
                $objtotal->coltotal = $objtotal->colrepasse + $objtotal->colpercentual;
                $comissoes->push($objtotal);
                $quantidade = 0;
                $valor = 0;
                $percentual = 0;
                $repasse = 0;
                $valorvenda = 0;
                $quantidadeCondicao = 0;
                $valorCondicao = 0;
                $percentualCondicao = 0;
                $repasseCondicao = 0;
                $condicaoAnterior = $comissao->condicao;
                $produtoAnterior = $comissao->produto;
                $condicaoProdAnterior = $condicaoProdAtual;
                //$percentualComissaoAnterior = $comissao->excessoessegmento != null?$comissao->valorexcecao:$comissao->percentual;
            }

            if(is_null($anterior) || $anterior != $comissao->colaborador_id){
                $objentregador = (object) array();
                $objentregador->colaborador_id = $comissao->colaborador_id;
                $objentregador->colaborador = $comissao->nome;
                $comissoes->push($objentregador);
                $countcol++;
                
            }

            if(!is_null($condicaoProdAnterior) && $condicaoProdAnterior != $condicaoProdAtual){
                $objCondicao = (object) array();
                $objCondicao->colaborador_id = $comissao->colaborador_id;
                $objCondicao->pedido_id = "";
                $objCondicao->condicao = $condicaoAnterior;
                $objCondicao->data = null;
                $objCondicao->produto = $produtoAnterior;
                $objCondicao->percentual_comissao = null; //$percentualComissaoAnterior;
                $objCondicao->quantidade =$quantidadeCondicao;
                $objCondicao->valor = $valorCondicao;
                $objCondicao->percentual =$percentualCondicao;
                $objCondicao->repasse = $repasseCondicao;
                $comissoes->push($objCondicao);
                $condicaoAnterior = $comissao->condicao;
                $produtoAnterior = $comissao->produto;
                $condicaoProdAnterior = $condicaoProdAtual;
                //$percentualComissaoAnterior = $comissao->excessoessegmento != null?$comissao->valorexcecao:$comissao->percentual;
                $quantidadeCondicao = 0;
                $valorCondicao = 0;
                $percentualCondicao = 0;
                $repasseCondicao = 0;
            } elseif(is_null($condicaoProdAnterior)){
                $condicaoAnterior = $comissao->condicao;
                $produtoAnterior = $comissao->produto;
                $condicaoProdAnterior = $condicaoProdAtual;
                //$percentualComissaoAnterior = $comissao->excessoessegmento != null?$comissao->valorexcecao:$comissao->percentual;
                $quantidadeCondicao = 0;
                $valorCondicao = 0;
                $percentualCondicao = 0;
                $repasseCondicao = 0;
            }

            $valorbruto = $comissao->valorvenda + $comissao->valordesconto;
            $excessao = $comissao->excessoessegmento != null;
            $objnormal = (object) array();
            $objnormal->colaborador_id = $comissao->colaborador_id;
            $objnormal->pedido_id = $comissao->pedido_id;
            $objnormal->condicao = $comissao->condicao;
            $objnormal->data = requestDataOracle($comissao->datahoraprevisaoentrega,false);
            $objnormal->produto = $comissao->produto;
            $objnormal->quantidade = $comissao->quantidade;
            $objnormal->valor = $comissao->precovendaunitario * $objnormal->quantidade;
            if($comissao->comissao_convenio > 0){
                $objnormal->valor = $objnormal->valor -  ($objnormal->valor * $comissao->comissao_convenio/100);
            }


            $proporcional = ($objnormal->valor - ($objnormal->valor / $valorbruto) * $comissao->valordesconto);
            $objnormal->valor = $proporcional;
            if($excessao && $comissao->tipoexcecao == 1){
                $objnormal->percentual = ($objnormal->valor * $comissao->valorexcecao / 100);
                $objnormal->repasse = 0;
            }else if($excessao && $comissao->tipoexcecao = 2){
                $objnormal->percentual = 0;
                $objnormal->repasse = ($proporcional - ($comissao->valorexcecao * $objnormal->quantidade));
            }else if($comissao->tipocomissao == 1){
                $objnormal->percentual = ($objnormal->valor * $comissao->percentual / 100);
                $objnormal->repasse = 0;
            }else if ($comissao->tipocomissao == 2){
                $objnormal->percentual = 0;
                $objnormal->repasse = ($proporcional - ($comissao->empresavalor * $objnormal->quantidade));
            }else{
                $objnormal->percentual = 0;
                $objnormal->repasse = 0;
            }
            //$comissoes->push($objnormal);
            $anterior = $comissao->colaborador_id;

            $quantidade += $objnormal->quantidade;
            $valor += $objnormal->valor;
            $percentual += $objnormal->percentual;
            $repasse += $objnormal->repasse;

            $quantidadeTotal += $objnormal->quantidade;
            $valorTotal += $objnormal->valor;
            $percentualTotal += $objnormal->percentual;
            $repasseTotal += $objnormal->repasse;

            $quantidadeCondicao += $objnormal->quantidade;
            $valorCondicao += $objnormal->valor;
            $percentualCondicao += $objnormal->percentual;
            $repasseCondicao += $objnormal->repasse;


            $count++;
        }
        $objCondicao = (object) array();
        $objCondicao->colaborador_id = $comissao->colaborador_id;
        $objCondicao->pedido_id = "";
        $objCondicao->condicao = $condicaoAnterior;
        $objCondicao->data = null;
        $objCondicao->produto = $produtoAnterior;
        $objCondicao->quantidade =$quantidadeCondicao;
        $objCondicao->valor = $valorCondicao;
        $objCondicao->percentual =$percentualCondicao;
        $objCondicao->repasse = $repasseCondicao;
        $objCondicao->percentual_comissao = null; //$percentualComissaoAnterior;
        $comissoes->push($objCondicao);
        $objtotal = (object) array();
        $objtotal->colquantidade = $quantidade;
        $objtotal->colvalor = $valor;
        $objtotal->colpercentual = $percentual;
        $objtotal->colrepasse = $repasse;
        $objtotal->coltotal = $objtotal->colrepasse + $objtotal->colpercentual;
        $comissoes->push($objtotal);
        $objtotalgeral = (object) array();
        $objtotalgeral->quantidadetotal = $quantidadeTotal;
        $objtotalgeral->valortotal = $valorTotal;
        $objtotalgeral->percentualtotal = $percentualTotal;
        $objtotalgeral->repassetotal = $repasseTotal;
        $objtotalgeral->totalgeral = $objtotalgeral->percentualtotal + $objtotalgeral->repassetotal;
        $comissoes->push($objtotalgeral);
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $colaborador_id == null ? $filtro . ", Colaborador: todos." : $filtro . ", Colaborador: " . Colaborador::where('id',$colaborador_id)->pluck('nome')->first().".";
        $retorno["filtro"] = $filtro;
        $retorno["comissoes"] = $comissoes;
        return $retorno;
    }

    public function filtroComissaoTonelagem($get){
        $this->definition();
        $tipo = $get["tipo"];
        $resumo = $get["resumo"];
        if($resumo=="1"){
            $com = $this->buscaComissoesTonelagemResumo($get);
        } else {
            $com = $this->buscaComissoesTonelagem($get);
        }
        $filtro = $com["filtro"];
        $comissoes = $com["comissoes"];
        $titulo = "Relatório de Comissões";
        if($tipo == 1){
            return view('reports.vendas.entregador.comissoes.entregador_comissao_tonelagem_pdf')->with(compact('titulo','filtro','comissoes', 'resumo'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('a4','landscape')->loadView('reports.vendas.entregador.comissoes.entregador_comissao_tonelagem_pdf', compact('titulo','filtro','comissoes', 'resumo'));
            return $pdf->stream();
        }
    }

    private function buscaComissoesTonelagem($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $colaborador_id = $filtro["colaborador"] == 0 ? null : $filtro["colaborador"];

        $comissionados = DB::table('pedidos')
                            ->join('pedidoitems as items','items.pedido_id','pedidos.id')
                            ->join('produtos','items.produto_id','produtos.id')
                            ->join('clientes','pedidos.cliente_id','clientes.id')
                            ->leftJoin('colaboradorcomissaos as comissionados',function($join){
                                $join->on('comissionados.produto_id','items.produto_id');
                                $join->on('comissionados.datainicio','<=',DB::raw('pedidos.datahoraprevisaoentrega'));
                                $join->on('comissionados.datafim','>=',DB::raw('pedidos.datahoraprevisaoentrega'));
                                $join->on('comissionados.tonelagem', DB::raw('1'));
                                $join->on('comissionados.ativo', DB::raw('1'));
                            })
                            ->join('colaboradors as colaborador','comissionados.colaborador_id','colaborador.id')
                            ->leftJoin('comissaoexcecoes as excessoes',function($join){
                                $join->on('excessoes.colaboradorcomissao_id','comissionados.id');
                                $join->on('excessoes.segmento_id','clientes.segmento_id');
                            })
                            ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                            ->where([
                                    ['pedidos.empresa_id',$this->empresa_id],
                                    ['items.precovendaunitario','>','0']
                                ])
                            ->whereRaw("pedidos.pedidosituacao_id in (SELECT id FROM pedidosituacaos WHERE (fechadoconcluido = 1 or entregafinalizada = 1) and grupo_id = $this->grupo_id)");
        if(!is_null($colaborador_id))
            $comissionados = $comissionados->where('colaborador.id',$colaborador_id);
        $comissionados = $comissionados->select('pedidos.id as pedido_id','colaborador.id as colaborador_id','colaborador.nome',
                                            'items.produto_id', 'produtos.descricao as produto','pedidos.datahoraprevisaoentrega','items.quantidade',
                                            'items.precovendaunitario','pedidos.valorvenda','comissionados.tipocomissao','pedidos.valordesconto',
                                            DB::raw('case when pedidos.apipedido_id is null then comissionados.percentual else comissionados.percentualapp end as percentual'),
                                            DB::raw('case when pedidos.apipedido_id is null then comissionados.empresavalor else comissionados.empresavalorapp end as empresavalor'),
                                            'excessoes.tipoexcecao',
                                            DB::raw('case when pedidos.apipedido_id is null then excessoes.valorexcecao else excessoes.valorexcecaoapp end as valorexcecao'),
                                            'excessoes.segmento_id as excessoessegmento','clientes.segmento_id',
                                            'excessoes.colaboradorcomissao_id','comissionados.id as comissionados_id', 'pedidos.apipedido_id', 
                                            DB::raw("ITEMS.QUANTIDADE*PRODUTOS.PESOLIQUIDO AS KG"))
                                        ->orderBy('colaborador.nome')->orderBy('pedido_id')->orderBy('produto')->get();
        $comissoes = collect([]);
        $anterior = null;
        $count = 0;
        $countcol = 0;
        $quantidade = 0;
        $kg = 0;
        $percentual = 0;
        foreach($comissionados as $comissao){
            if(!is_null($anterior) && $anterior != $comissao->colaborador_id){
                $objtotal = (object) array();
                $objtotal->colquantidade = $quantidade;
                $objtotal->colkg = $kg;
                $objtotal->colpercentual = $percentual;
                $objtotal->coltotal = $objtotal->colpercentual;
                $comissoes->push($objtotal);
                $quantidade = 0;
                $kg = 0;
                $percentual = 0;
            }

            if(is_null($anterior) || $anterior != $comissao->colaborador_id){
                $objentregador = (object) array();
                $objentregador->colaborador_id = $comissao->colaborador_id;
                $objentregador->colaborador = $comissao->nome;
                $comissoes->push($objentregador);
                $countcol++;
            }

            $excessao = $comissao->excessoessegmento != null;
            $objnormal = (object) array();
            $objnormal->colaborador_id = $comissao->colaborador_id;
            $objnormal->pedido_id = $comissao->pedido_id.($comissao->apipedido_id?" *":"");
            $objnormal->data = requestDataOracle($comissao->datahoraprevisaoentrega,false);
            $objnormal->produto = $comissao->produto;
            $objnormal->quantidade = $comissao->quantidade;
            $objnormal->kg = $comissao->kg;
            $objnormal->percentual_comissao = $comissao->percentual;
            if($excessao && $comissao->tipoexcecao == 1){
                $objnormal->percentual = ($objnormal->kg * $comissao->valorexcecao / 100);
                $objnormal->percentual_comissao = $comissao->valorexcecao;
            }else{
                $objnormal->percentual = ($objnormal->kg * $comissao->percentual / 100);
                $objnormal->percentual_comissao = $comissao->percentual;
            }
            $comissoes->push($objnormal);
            $anterior = $comissao->colaborador_id;

            $quantidade += $objnormal->quantidade;
            $kg += $objnormal->kg;
            $percentual += $objnormal->percentual;

            $count++;
            if($count == count($comissionados)){
                if($countcol > 1){
                    $objtotal = (object) array();
                    $objtotal->colquantidade = $quantidade;
                    $objtotal->colkg = $kg;
                    $objtotal->colpercentual = $percentual;
                    $objtotal->coltotal = $objtotal->colpercentual;
                    $comissoes->push($objtotal);
                }
                $objtotalgeral = (object) array();
                $objtotalgeral->quantidadetotal = $comissoes->sum('quantidade');
                $objtotalgeral->kgtotal = $comissoes->sum('kg');
                $objtotalgeral->percentualtotal = $comissoes->sum('percentual');
                $objtotalgeral->totalgeral = $objtotalgeral->percentualtotal;
                $comissoes->push($objtotalgeral);
            }
        }
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $colaborador_id == null ? $filtro . ", Colaborador: todos." : $filtro . ", Colaborador: " . Colaborador::where('id',$colaborador_id)->pluck('nome')->first().".";
        $retorno["filtro"] = $filtro;
        $retorno["comissoes"] = $comissoes;
        return $retorno;
    }

    private function buscaComissoesTonelagemResumo($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $colaborador_id = $filtro["colaborador"] == 0 ? null : $filtro["colaborador"];


        $comissionados = DB::table('pedidos')
                            ->join('pedidoitems as items','items.pedido_id','pedidos.id')
                            ->join('produtos','items.produto_id','produtos.id')
                            ->join('clientes','pedidos.cliente_id','clientes.id')
                            ->leftJoin('colaboradorcomissaos as comissionados',function($join){
                                $join->on('comissionados.produto_id','items.produto_id');
                                $join->on('comissionados.datainicio','<=',DB::raw('pedidos.datahoraprevisaoentrega'));
                                $join->on('comissionados.datafim','>=',DB::raw('pedidos.datahoraprevisaoentrega'));
                                $join->on('comissionados.tonelagem', DB::raw('1'));
                                $join->on('comissionados.ativo', DB::raw('1'));
                            })
                            ->join('colaboradors as colaborador','comissionados.colaborador_id','colaborador.id')
                            ->leftJoin('comissaoexcecoes as excessoes',function($join){
                                $join->on('excessoes.colaboradorcomissao_id','comissionados.id');
                                $join->on('excessoes.segmento_id','clientes.segmento_id');
                            })
                            ->whereBetween('pedidos.datahoraprevisaoentrega',[$datainicio,$datafim])
                            ->where([
                                    ['pedidos.empresa_id',$this->empresa_id],
                                    ['items.precovendaunitario','>','0']
                                ])
                            ->whereRaw("pedidos.pedidosituacao_id in (SELECT id FROM pedidosituacaos WHERE (fechadoconcluido = 1 or entregafinalizada = 1) and grupo_id = $this->grupo_id)");
        if(!is_null($colaborador_id))
            $comissionados = $comissionados->where('colaborador.id',$colaborador_id);
        $comissionados = $comissionados->select('pedidos.id as pedido_id','colaborador.id as colaborador_id','colaborador.nome',
                                            'items.produto_id', 'produtos.descricao as produto','pedidos.datahoraprevisaoentrega','items.quantidade',
                                            'items.precovendaunitario','pedidos.valorvenda','comissionados.tipocomissao','pedidos.valordesconto',
                                            DB::raw('case when pedidos.apipedido_id is null then comissionados.percentual else comissionados.percentualapp end as percentual'),
                                            DB::raw('case when pedidos.apipedido_id is null then comissionados.empresavalor else comissionados.empresavalorapp end as empresavalor'),
                                            'excessoes.tipoexcecao',
                                            DB::raw('case when pedidos.apipedido_id is null then excessoes.valorexcecao else excessoes.valorexcecaoapp end as valorexcecao'),
                                            'excessoes.segmento_id as excessoessegmento','clientes.segmento_id',
                                            'excessoes.colaboradorcomissao_id','comissionados.id as comissionados_id', 'pedidos.apipedido_id', 
                                            DB::raw("ITEMS.QUANTIDADE*PRODUTOS.PESOLIQUIDO AS KG"))
                                            ->orderBy('colaborador.nome')->orderBy('items.produto_id')
                                            ->orderBy('comissionados.percentual')->orderBy('excessoes.valorexcecao')->get();
        
        $comissoes = collect([]);
        $anterior = null;
        $condicaoProdAnterior = null;
        $produtoAnterior = null;
        $count = 0;
        $countcol = 0;
        $quantidade = 0;
        $kg = 0;
        $percentual = 0;
        $quantidadeTotal = 0;
        $kgTotal = 0;
        $percentualTotal = 0;
        $quantidadeProduto = 0;
        $kgProduto = 0;
        $percentualProduto = 0;
        foreach($comissionados as $comissao){
            $condicaoProdAtual = $comissao->produto_id; 

            if(!is_null($anterior) && $anterior != $comissao->colaborador_id){
                $objProduto = (object) array();
                $objProduto->colaborador_id = $comissao->colaborador_id;
                $objProduto->pedido_id = "";
                $objProduto->data = null;
                $objProduto->produto = $produtoAnterior;
                $objProduto->quantidade =$quantidadeProduto;
                $objProduto->kg = $kgProduto;
                $objProduto->percentual =$percentualProduto;
                $objProduto->percentual_comissao = null; 
                $comissoes->push($objProduto);
                $objtotal = (object) array();
                $objtotal->colquantidade = $quantidade;
                $objtotal->colkg = $kg;
                $objtotal->colpercentual = $percentual;
                $objtotal->coltotal = $objtotal->colpercentual;
                $comissoes->push($objtotal);
                $quantidade = 0;
                $kg = 0;
                $percentual = 0;
                $quantidadeProduto = 0;
                $kgProduto = 0;
                $percentualProduto = 0;
                $produtoAnterior = $comissao->produto;
                $condicaoProdAnterior = $condicaoProdAtual;
            }

            if(is_null($anterior) || $anterior != $comissao->colaborador_id){
                $objentregador = (object) array();
                $objentregador->colaborador_id = $comissao->colaborador_id;
                $objentregador->colaborador = $comissao->nome;
                $comissoes->push($objentregador);
                $countcol++;
                
            }

            if(!is_null($condicaoProdAnterior) && $condicaoProdAnterior != $condicaoProdAtual){
                $objProduto = (object) array();
                $objProduto->colaborador_id = $comissao->colaborador_id;
                $objProduto->pedido_id = "";
                $objProduto->data = null;
                $objProduto->produto = $produtoAnterior;
                $objProduto->percentual_comissao = null;
                $objProduto->quantidade =$quantidadeProduto;
                $objProduto->kg = $kgProduto;
                $objProduto->percentual =$percentualProduto;
                $comissoes->push($objProduto);
                $produtoAnterior = $comissao->produto;
                $condicaoProdAnterior = $condicaoProdAtual;
                $quantidadeProduto = 0;
                $kgProduto = 0;
                $percentualProduto = 0;
            } elseif(is_null($condicaoProdAnterior)){
                $produtoAnterior = $comissao->produto;
                $condicaoProdAnterior = $condicaoProdAtual;
                $quantidadeProduto = 0;
                $kgProduto = 0;
                $percentualProduto = 0;
            }

            $excessao = $comissao->excessoessegmento != null;
            $objnormal = (object) array();
            $objnormal->colaborador_id = $comissao->colaborador_id;
            $objnormal->pedido_id = $comissao->pedido_id;
            $objnormal->data = requestDataOracle($comissao->datahoraprevisaoentrega,false);
            $objnormal->produto = $comissao->produto;
            $objnormal->quantidade = $comissao->quantidade;
            $objnormal->kg = $comissao->kg;
            if($excessao && $comissao->tipoexcecao == 1){
                $objnormal->percentual = ($objnormal->kg * $comissao->valorexcecao / 100);
            }else{
                $objnormal->percentual = ($objnormal->kg * $comissao->percentual / 100);
            }
            $anterior = $comissao->colaborador_id;

            $quantidade += $objnormal->quantidade;
            $kg += $objnormal->kg;
            $percentual += $objnormal->percentual;

            $quantidadeTotal += $objnormal->quantidade;
            $kgTotal += $objnormal->kg;
            $percentualTotal += $objnormal->percentual;

            $quantidadeProduto += $objnormal->quantidade;
            $kgProduto += $objnormal->kg;
            $percentualProduto += $objnormal->percentual;


            $count++;
        }
        $objProduto = (object) array();
        $objProduto->colaborador_id = $comissao->colaborador_id;
        $objProduto->pedido_id = "";
        $objProduto->data = null;
        $objProduto->produto = $produtoAnterior;
        $objProduto->quantidade =$quantidadeProduto;
        $objProduto->kg = $kgProduto;
        $objProduto->percentual =$percentualProduto;
        $objProduto->percentual_comissao = null; 
        $comissoes->push($objProduto);
        $objtotal = (object) array();
        $objtotal->colquantidade = $quantidade;
        $objtotal->colkg = $kg;
        $objtotal->colpercentual = $percentual;
        $objtotal->coltotal = $objtotal->colpercentual;
        $comissoes->push($objtotal);
        $objtotalgeral = (object) array();
        $objtotalgeral->quantidadetotal = $quantidadeTotal;
        $objtotalgeral->kgtotal = $kgTotal;
        $objtotalgeral->percentualtotal = $percentualTotal;
        $objtotalgeral->totalgeral = $objtotalgeral->percentualtotal;
        $comissoes->push($objtotalgeral);
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $colaborador_id == null ? $filtro . ", Colaborador: todos." : $filtro . ", Colaborador: " . Colaborador::where('id',$colaborador_id)->pluck('nome')->first().".";
        $retorno["filtro"] = $filtro;
        $retorno["comissoes"] = $comissoes;
        return $retorno;
    }

    private function definition(){
        //busca id da empresa logada
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca id do grupo
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //set empresa repository
        $this->repository->setEmpresa($this->empresa_id);
        //set grupo repository
        $this->repository->setGrupo($this->grupo_id);
    }
}

/*
 SELECT pe.id,col.id as colaborador_id, col.nome, pe.condicaopagamento_id, con.descricao as condicao, it.produto_id, prod.descricao as produto, pe.datahoraprevisaoentrega,
it.quantidade, it.precovendaunitario, pe.valorvenda, com.tipocomissao, com.percentual, com.empresavalor, ex.tipoexcecao, ex.valorexcecao, ex.segmento_id as excessoessegmento,
cli.segmento_id, ex.colaboradorcomissao_id, com.id as comissionados_id  
FROM PEDIDOS pe
INNER JOIN pedidoitems it ON it.pedido_id = pe.id
INNER JOIN produtos prod ON it.produto_id = prod.id
INNER JOIN colaboradors col ON col.id = pe.colaborador_id
INNER JOIN condicaopagamentos con ON pe.condicaopagamento_id = con.id
INNER JOIN clientes cli ON cli.id = pe.cliente_id
LEFT OUTER JOIN colaboradorcomissaos com ON 
com.colaborador_id = pe.colaborador_id 
and com.produto_id = it.produto_id and com.condicaopagamento_id = pe.condicaopagamento_id 
and com.setor_id = pe.entregasetor_id
and com.datainicio <= pe.datahoraprevisaoentrega and com.datafim >= pe.datahoraprevisaoentrega
LEFT OUTER JOIN comissaoexcecoes ex ON ex.colaboradorcomissao_id = com.id AND ex.segmento_id = cli.segmento_id
WHERE pe.empresa_id = 1 
  AND pe.datahoraprevisaoentrega BETWEEN to_date('2017-07-01','YYYY-MM-DD') and to_date('2017-07-31','YYYY-MM-DD') 
  AND pe.pedidosituacao_id IN (SELECT id FROM pedidosituacaos WHERE fechadoconcluido = 1 or entregafinalizada = 1)


  */