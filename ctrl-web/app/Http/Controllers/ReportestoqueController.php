<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Setor;
use App\Produto;
use App\Estoquesetor;
use App\Produtoclasse;
use App\Estoquerequisicao;
use Illuminate\Http\Request;
use App\Estoquetransferencia;
use App\Estoquerequisicaoitem;
use App\Estoquetransferenciaitem;
use App\Repository\SelectRepository;
use App\Services\CarbonCustom as Carbon;

class ReportestoqueController extends Controller
{
    private $empresa_id;
    private $grupo_id;
    private $setores;
    private $produtos;
    private $classes;
    private $repositorio;

    public function __construct(SelectRepository $repositorio){
        $this->repositorio = $repositorio;
    }

    //Transferencia
    public function transferenciaIndex(){
        $this->definition();
        $setores = $this->repositorio->getSetores();
        $produtos = $this->repositorio->getProdutos()->prepend('Selecione','');

        return view('reports.estoque.transferencia.estoquetransferencia')->with(compact('setores','produtos'));
    }

    public function transferenciaPdf(){
        $this->definition();
        $get = $_GET;
        if($get["destino"] == "0"){
            $transferencia = $this->buscaTransferencia($get);
            $origem = $transferencia["origem"];
            $filtro = $transferencia["filtro"];
            $titulo = 'Relatório de Transferências de Estoque Origem';
            $tipo = $get["tipo"];
            if($tipo == 1){
                $pdf = \App::make('dompdf.wrapper');
                $pdf->loadView('reports.estoque.transferencia.estoquetransferencia_pdf', compact('titulo', 'origem','filtro'));
                return $pdf->stream();
            }
            return view('reports.estoque.transferencia.estoquetransferencia_pdf', compact('titulo', 'origem','filtro'));
        }else{
            $transferencia = $this->buscaTransferenciaDestino($get);
            $destino = $transferencia["destino"];
            $filtro = $transferencia["filtro"];
            $titulo = 'Relatório de Transferências de Estoque Destino';
            $tipo = $get["tipo"];
            if($tipo == 1){
                $pdf = \App::make('dompdf.wrapper');
                $pdf->loadView('reports.estoque.transferencia.estoquetransferencia_destino_pdf', compact('titulo', 'destino','filtro'));
                return $pdf->stream();
            }
            return view('reports.estoque.transferencia.estoquetransferencia_destino_pdf', compact('titulo', 'destino','filtro'));
        }
    }

    private function buscaTransferencia($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafinal"])->endOfDay();
        $setor_origem = $filtro["setororigem"] == "null" ? null : explode(',',$filtro["setororigem"]);
        $produto_id = $filtro["produto"] == "0" ? null : $filtro["produto"];
        $operadorprod = $produto_id == null ? "!=" : "=";
        $tr = $this->pesquisaTransferencia($datainicio,$datafim,$produto_id,$setor_origem,"origem");
        $transferencias = $tr->orderBy('setors.descricao')
                                         ->orderBy('setororigem')
                                         ->orderBy('setordestino')
                                         ->orderBy('produto')
                                         ->orderBy('datahora')->get();
        $origem = collect([]);
        $totalenviado = null;
        $totalsaida = null;
        $anterior = null;
        $destino = null;
        $count = 0;
        $countsec = 0;
        foreach($transferencias as $transferencia){
            if(!is_null($destino) && $destino != $transferencia->destinosetor_id || 
            $anterior != $transferencia->origemsetor_id && !is_null($destino)){
                $objtotalrec = (object) array();
                $objtotalrec->totalrecebido = $transferencias->where('origemsetor_id',$anterior)
                                                     ->where('destinosetor_id',$destino)
                                                     ->sum('quantidade');
                $origem->push($objtotalrec);
            }
            if(is_null($anterior) || $anterior != $transferencia->origemsetor_id){
                $objorigem = (object) array();
                $objorigem->origem_id = $transferencia->origemsetor_id;
                $objorigem->origem = $transferencia->setororigem;
                $origem->push($objorigem);
            }
            if($destino == 'none' || $destino != $transferencia->destinosetor_id ||
                                     $anterior != $transferencia->origemsetor_id){
                $objdestino = (object) array();
                $objdestino->destino_id = $transferencia->destinosetor_id;
                $objdestino->destino = $transferencia->setordestino;
                $origem->push($objdestino);
                $countsec++;
            }
            $objnormal = (object) array();
            $objnormal->transferencia_id = $transferencia->transferencia_id;
            $objnormal->datahora = requestDataOracle($transferencia->datahora);
            $objnormal->produto = $transferencia->produto;
            $objnormal->quantidade = $transferencia->quantidade;
            $objnormal->observacoes = $transferencia->observacoes;
            $origem->push($objnormal);
            $totalenviado += $transferencia->quantidade;
            $anterior = $transferencia->origemsetor_id;
            $destino = $transferencia->destinosetor_id;
            $count++;
            if($count == count($transferencias)){
                if($countsec > 1){
                    $objtotalrec = (object) array();
                    $objtotalrec->totalrecebido = $transferencias->where('origemsetor_id',$anterior)
                                                        ->where('destinosetor_id',$destino)
                                                        ->sum('quantidade');
                    $origem->push($objtotalrec);
                    $objtotalorigem = (object) array();
                    $objtotalorigem->totalenv = $transferencias->where('origemsetor_id',$anterior)
                                                    ->sum('quantidade');
                    $origem->push($objtotalorigem);
                }
                $obj = (object) array();
                $obj->total = $totalenviado;
                $origem->push($obj);
            }
        }
        $filtro = "Filtro: Período: " . requestDataOracle($datainicio,false,true) . " a " . requestDataOracle($datafim,false,true);
        $filtro = $produto_id == null ? $filtro . ", Produto: todos" : $filtro . ", Produto: " . Produto::where('id',$produto_id)->pluck('descricao')->first();
        $filtro = $setor_origem == null ? $filtro . ", Setor(es): todos" : $filtro . ", Setor(es): " . implode(', ',Setor::whereIn('id',$setor_origem)->pluck('descricao')->toArray());
        $retorno["origem"] = $origem;
        $retorno["filtro"] = $filtro;
        return $retorno;  
    }

    private function buscaTransferenciaDestino($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafinal"])->endOfDay();
        $setor_destino = $filtro["setordestino"] == "null" ? null : explode(',',$filtro["setordestino"]);
        $produto_id = $filtro["produto"] == "0" ? null : $filtro["produto"];
        $operadorprod = $produto_id == null ? "!=" : "=";
        $tr = $this->pesquisaTransferencia($datainicio,$datafim,$produto_id,$setor_destino,"destino");
        $transferencias = $tr->orderBy('setordestino')
                             ->orderBy('setororigem')
                             ->orderBy('produto')
                             ->orderBy('datahora')->get();
        $destino = collect([]);
        $totalrecebico = null;
        $totalsaida = null;
        $anterior = null;
        $origem = null;
        $count = 0;
        $countdest = 0;
        $first = true;
        foreach($transferencias as $tra){
            if(!is_null($origem) && $origem != $tra->origemsetor_id || 
                        $anterior != $tra->destinosetor_id && !is_null($origem)){
                $objtotalrec = (object) array();
                $objtotalrec->totalrecebido = $transferencias->where('destinosetor_id',$anterior)
                                                     ->where('origemsetor_id',$origem)
                                                     ->sum('quantidade');
                $destino->push($objtotalrec);
            }
            if(is_null($anterior) || $anterior != $tra->destinosetor_id){
                $objdestino = (object) array();
                $objdestino->destino_id = $tra->destinosetor_id;
                $objdestino->destino = $tra->setordestino;
                $destino->push($objdestino);
            }
            if(is_null($origem) || $origem != $tra->origemsetor_id || $anterior != $tra->destinosetor_id){
                $objorigem = (object) array();
                $objorigem->origem_id = $tra->origemsetor_id;
                $objorigem->origem = $tra->setororigem;
                $destino->push($objorigem);
                $countdest++;
            }
            $objnormal = (object) array();
            $objnormal->transferencia_id = $tra->transferencia_id;
            $objnormal->datahora = requestDataOracle($tra->datahora);
            $objnormal->produto = $tra->produto;
            $objnormal->quantidade = $tra->quantidade;
            $objnormal->observacoes = $tra->observacoes;
            $destino->push($objnormal);
            $totalrecebico += $tra->quantidade;
            $anterior = $tra->destinosetor_id;
            $origem = $tra->origemsetor_id;
            $count++;
            if($count == count($transferencias)){
                if($countdest > 1){
                    $objtotalrec = (object) array();
                    $objtotalrec->totalrecebido = $transferencias->where('destinosetor_id',$anterior)
                                                        ->where('origemsetor_id',$origem)
                                                        ->sum('quantidade');
                    $destino->push($objtotalrec);
                    $objtotaldestino = (object) array();
                    $objtotaldestino->totalenv = $transferencias->where('destinosetor_id',$anterior)
                                                    ->sum('quantidade');
                    $destino->push($objtotaldestino);
                }
                $obj = (object) array();
                $obj->total = $totalrecebico;
                $destino->push($obj);
            }
        }
        $filtro = "Filtro: Período: " . requestDataOracle($datainicio,false,true) . " a " . requestDataOracle($datafim,false,true);
        $filtro = $produto_id == null ? $filtro . ", Produto: todos" : $filtro . ", Produto: " . Produto::where('id',$produto_id)->pluck('descricao')->first();
        $filtro = $setor_destino == null ? $filtro . ", Setor(es): todos." : $filtro . ", Setor(es): " . implode(', ',Setor::whereIn('id',$setor_destino)->pluck('descricao')->toArray());
        $retorno["destino"] = $destino;
        $retorno["filtro"] = $filtro;
        return $retorno;  
    }

    private function pesquisaTransferencia($datainicio,$datafim,$produto_id,$setor,$coluna){
        $operadorprod = $produto_id == null ? "!=" : "=";
        $transferencias = DB::table('estoquetransferencias as transferencia')
                              ->join('estoquetransferenciaitems as tritems','tritems.estoquetransferencia_id','transferencia.id')
                              ->join('produtos','tritems.produto_id','produtos.id')
                              ->join('setors','transferencia.origemsetor_id','setors.id')
                              ->join('setors as sec','transferencia.destinosetor_id','sec.id')
                              ->whereBetween('transferencia.datahora',[$datainicio,$datafim])
                              ->where([
                                  ['transferencia.empresa_id',$this->empresa_id],
                                  ['tritems.produto_id',$operadorprod,$produto_id],
                                  ['setors.ativo',1],
                              ]);

        if($setor != null && $coluna == "destino"){
            $transferencias = $transferencias->whereIn('transferencia.destinosetor_id',$setor); 
        }else if($setor != null && $coluna == "origem"){
            $transferencias = $transferencias->whereIn('transferencia.origemsetor_id',$setor); 
        }

        $transferencias = $transferencias->select('transferencia.id as transferencia_id','tritems.quantidade','tritems.produto_id',
                    'produtos.descricao as produto','setors.id','transferencia.origemsetor_id',
                    'transferencia.destinosetor_id','setors.descricao as setororigem',
                    'sec.descricao as setordestino','transferencia.datahora','transferencia.observacoes');
        return $transferencias;
    }

    //Requisicao
    public function requisicaoIndex(){
        $this->definition();
        $setores = $this->repositorio->getSetores()->prepend('Selecione','');
        $produtos = $this->repositorio->getProdutos()->prepend('Selecione','');

        return view('reports.estoque.requisicao.estoque_requisicao')->with(compact('setores','produtos'));
    }

    public function requisicaoPdf(){
        $this->definition();
        $get = $_GET;
        $requisicao = $this->buscaRequisicao($get);
        $requisicoes = $requisicao["requisicoes"];
        $filtro = $requisicao["filtro"];
        $tipo = $get["tipo"];
        $titulo = 'Relatório de Requisições de Estoque';
        if($tipo == "1"){
            return view('reports.estoque.requisicao.estoque_requisicao_pdf')->with(compact('titulo', 'requisicoes','filtro'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.estoque.requisicao.estoque_requisicao_pdf', compact('titulo', 'requisicoes','filtro'))->setPaper('a4','landscape');
            return $pdf->stream();
        }
    }

    private function buscaRequisicao($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafinal"])->endOfDay();
        $setor_id = $filtro["setor"] == 0 ? null : $filtro["setor"];
        $produto_id = $filtro["produto"] == 0 ? null : $filtro["produto"];
        $cancelado = $filtro["cancelado"] == 1;
        $operadorprod = $produto_id == null ? '!=' : '=';
        $operadorsetor = $setor_id == null ? '!=' : '=';
        $estrequisicao = DB::table('estoquerequisicaoitems as items')
                                    ->join('estoquerequisicaos as requisicao','requisicao.id','items.estoquerequisicao_id')
                                    ->join('produtos','items.produto_id','produtos.id')
                                    ->join('setors as setor','items.setor_id','setor.id')
                                    ->leftJoin('planocontas as plano','requisicao.planoconta_id','plano.id')
                                    ->leftJoin('centrocustos as centro','requisicao.centrocusto_id','centro.id')
                                    ->join('users','requisicao.user_id','users.id')
                                    ->whereBetween('requisicao.datahora',[$datainicio,$datafim])
                                    ->where([
                                        ['requisicao.empresa_id',$this->empresa_id],
                                        ['items.setor_id',$operadorsetor,$setor_id],
                                        ['items.produto_id',$operadorprod,$produto_id],
                                        ['requisicao.cancelado',$cancelado]
                                    ])->select('requisicao.id as requisicao_id','items.produto_id','produtos.descricao as produto',
                                               'setor.descricao as setor','plano.descricao as planocontas',
                                               'requisicao.planoconta_id','requisicao.centrocusto_id','items.quantidade',
                                               'centro.descricao as centrocusto','requisicao.cancelado','items.customedio',
                                               'requisicao.datahora','users.name as nome','items.setor_id as setor_id')
                                    ->orderBy('setor')->orderBy('produto')->orderBy('requisicao_id')->get();
        $requisicoes = collect([]);
        $totalgeral = 0;
        $anterior = null;
        $prodant = null;
        $custototal = 0;
        $count = 0;
        $countsec = 0;
        foreach($estrequisicao as $requisicao){
            if(!is_null($anterior) && $anterior != $requisicao->setor_id){
                $wsec = $estrequisicao->where('setor_id',$anterior);
                $objsetortotal = (object) array();
                $objsetortotal->totalsetor = $wsec->sum('quantidade');
                $objsetortotal->custototal = requestNumeroDecimalOracle($wsec->sum('customedio'));
                $requisicoes->push($objsetortotal);
            }
            if(is_null($anterior) || $requisicao->setor_id != $anterior){
                $objsetor = (object) array();
                $objsetor->setor = $requisicao->setor;
                $objsetor->setor_id = $requisicao->setor_id;
                $requisicoes->push($objsetor);
                $countsec++;
            }
            if(is_null($prodant) || $requisicao->produto_id != $prodant ||
                                     $anterior != $requisicao->setor_id){
                $objproduto = (object) array();
                $objproduto->produto = $requisicao->produto;
                $objproduto->produto_id = $requisicao->produto_id;
                $requisicoes->push($objproduto);
            }
            $objnormal = (object) array();
            $objnormal->requisicao_id = $requisicao->requisicao_id;
            $objnormal->datahora = requestDataOracle($requisicao->datahora);
            $objnormal->colaborador = $requisicao->nome;
            $objnormal->quantidade = $requisicao->quantidade;
            $objnormal->planoconta = $requisicao->planocontas;
            $objnormal->centrocusto = $requisicao->centrocusto;
            $objnormal->situacao = $requisicao->cancelado == 0 ? "Ativo" : "Cancelado";
            $objnormal->custo = requestNumeroDecimalOracle($requisicao->customedio);
            $requisicoes->push($objnormal);
            $anterior = $requisicao->setor_id;
            $prodant = $requisicao->produto_id;
            $totalgeral += $requisicao->quantidade;
            $custototal += $requisicao->customedio;
            $count++;
            if($count == count($estrequisicao)){
                // dd($countsec);
                if($countsec > 1){
                    $wsec = $estrequisicao->where('setor_id',$anterior);
                    $objsetortotal = (object) array();
                    $objsetortotal->totalsetor = $wsec->sum('quantidade');
                    $objsetortotal->custototal = requestNumeroDecimalOracle($wsec->sum('customedio'));
                    $requisicoes->push($objsetortotal);
                }
                $objtotalgeral = (object) array();
                $objtotalgeral->total = $totalgeral;
                $objtotalgeral->custototal = requestNumeroDecimalOracle($custototal);
                $requisicoes->push($objtotalgeral);
            }
        }
        $filtro = "FIltros: Período " . requestDataOracle($datainicio,false,true) . " a " . requestDataOracle($datafim,false,true);
        $filtro = $setor_id == null ? $filtro .  ", Setor: todos" : $filtro .  ", Setor: " . Setor::where('id',$setor_id)->pluck('descricao')->first();
        $filtro = $produto_id == null ? $filtro . ", Produto: todos" : $filtro . ", Produto: " . Produto::where('id',$produto_id)->pluck('descricao')->first();
        $filtro = $cancelado == 0 ? $filtro . ", sem requisições canceladas." : $filtro . ", com requisições canceladas.";
        $retorno["requisicoes"] = $requisicoes;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    //GLP
    public function glpIndex(){
        $this->definition();
        $setores = $this->repositorio->getSetores()->prepend('Selecione','');
        $produtos = $this->repositorio->getProdutos(true)->prepend('Selecione','');

        return view('reports.estoque.glp.estoque_glp')->with(compact('setores','produtos'));
    }

    public function pdfGlp(){
        $this->definition();
        $get = $_GET;
        $glp = $this->buscasGLP($get);
        $glps = $glp["setores"];
        $vazios = $glp["vazios"];
        $cheios = $glp["cheios"];
        $total = $glp["total"];
        $sector = $glp["sector"];
        $filtro = $glp["filtro"];
        $titulo = 'Relatório de Estoque de GLP';
        $tipo = $get["tipo"];
        if($tipo == 1){
            return view('reports.estoque.glp.estoque_glp_pdf')->with(compact('titulo', 'glps','cheios','vazios','sector','total','filtro'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.estoque.glp.estoque_glp_pdf', compact('titulo', 'glps','cheios','vazios','sector','total','filtro'));
            return $pdf->stream();
        }
    }

    private function buscasGLP($filtro){
        $setor_id = $filtro["setor"] == 0 ? null : $filtro["setor"];
        $produto_id = $filtro["produto"] == 0 ? null : $filtro["produto"];
        $zerado = $filtro["zerado"];
        $operadorsetor = $setor_id == null ? '!=' : '=';
        $operadorprod = $produto_id == null ? '!=' : '=';
        $class = Produtoclasse::where([['tipo','G'],['grupo_id',$this->grupo_id]])->pluck('id')->toArray();
        $vasilhame_ids = Produtoclasse::where(['grupo_id'=>$this->grupo_id,'tipo'=>'V'])->pluck('id')->toArray();
        $classes = array_merge($class,$vasilhame_ids);
        $estoques = DB::table('estoquesetors')->join('produtos','estoquesetors.produto_id','produtos.id')
                                ->join('setors as setor','estoquesetors.setor_id','setor.id')
                                ->whereIn('produtos.produtoclasse_id',$classes)
                                ->where([
                                    ['estoquesetors.empresa_id',$this->empresa_id],
                                    ['estoquesetors.setor_id',$operadorsetor,$setor_id],
                                    $zerado == 0 ? ['estoquesetors.quantidade','!=',0] : ['estoquesetors.quantidade','!=',null],
                                    ['setor.ativo',1],
                                    ['produtos.ativo',1]
                                ])->select('estoquesetors.produto_id','estoquesetors.setor_id',
                                           'produtos.id','produtos.produtoretornavel_id as vasilhame',
                                           'estoquesetors.quantidade','setor.descricao as setor',
                                           'produtos.descricao as produto')
                                ->orderBy('setor')
                                ->orderBy('produto')->get();
        $glps = $estoques->where('vasilhame','!=',null);
        $vasilhames = $estoques->where('vasilhame',null);
        $colecaosetor = collect([]);
        $setores = collect([]);
        $totalvazios = 0;
        $totalcheios = 0;
        $totalvasilhames = 0;
        foreach($estoques as $estoque){
            $vasilhame_id = $estoque->vasilhame;
            $validaprod = $produto_id == null ? true : false;
            if($vasilhame_id != null && $validaprod ? true : $estoque->produto_id == $produto_id){
                $objestoque = (object) array();
                $objsetor = (object) array();
                $objestoque->setor_id = $estoque->setor_id;
                $objestoque->setor = $estoque->setor;
                $objsetor->setor_id = $objestoque->setor_id;
                $objsetor->setor = $objestoque->setor;
                $objestoque->produto_id = $estoque->produto_id;
                $objestoque->produto = $estoque->produto;
                $cheio = $glps->where('produto_id',$estoque->produto_id)->where('setor_id',$estoque->setor_id)->sum('quantidade');
                $vasilhame = $estoques->where('produto_id',$vasilhame_id)->where('setor_id',$estoque->setor_id)->sum('quantidade');
                $objestoque->cheios = $cheio;
                $objestoque->total = $vasilhame;
                $objestoque->vazios = $objestoque->total - $objestoque->cheios;
                $totalvazios += $objestoque->vazios;
                $totalcheios += $objestoque->cheios;
                $totalvasilhames += $objestoque->total;
                $objestoque->produto_id = $estoque->produto_id;
                $objestoque->vasilhame_id = $vasilhame_id;
                $setores->push($objestoque);
                $colecaosetor->push($objsetor);
            }
        }
        $sector = collect([]);
        foreach($colecaosetor->unique("setor_id") as $setor){
            $objsector = (object) array();
            $objsector->setor = $setor->setor;
            $objsector->setor_id = $setor->setor_id;
            $objsector->cheios = $setores->where("setor_id",$setor->setor_id)->sum("cheios");
            $objsector->vazios = $setores->where("setor_id",$setor->setor_id)->sum("vazios");
            $objsector->total = $setores->where("setor_id",$setor->setor_id)->sum("total");
            $sector->push($objsector);
        }
        // dd($setores);
        $filtro = $setor_id == null ? "Setor: todos" : "Setor: " . Setor::where('id',$setor_id)->pluck('descricao')->first();
        $filtro = $produto_id == null ? $filtro . ", Produto: todos" : $filtro . ", Produto: " . Produto::where('id',$produto_id)->pluck('descricao')->first();
        $filtro = $zerado == 1 ? $filtro . ", com estoque zerado." : $filtro . ", sem estoque zerado.";
        $retorno["setores"] = $setores;
        $retorno["sector"] = $sector;
        $retorno["vazios"] = $totalvazios;
        $retorno["cheios"] = $totalcheios;
        $retorno["total"] = $totalvasilhames;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    //Geral
    public function geralIndex(){
        $this->definition();
        $setores = $this->repositorio->getSetores()->prepend('Selecione','');
        $produtos = $this->repositorio->getProdutos()->prepend('Selecione','');
        $classes = $this->repositorio->getProdutoClasse()->prepend('Selecione','');

        return view('reports.estoque.geral.estoque_geral')->with(compact('setores','produtos','classes'));
    }

    public function geralPdf(){
        $this->definition();
        $get = $_GET;
        $estoquegeral = $this->buscaGeral($get);
        $geral = $estoquegeral["geral"];
        $sector = $estoquegeral["sector"];
        $total = $estoquegeral["total"];
        $filtro = $estoquegeral["filtro"];
        $titulo = 'Relatório de Estoque Geral';
        $tipo = $get["tipo"];
        if($tipo == 1){
            return view('reports.estoque.geral.estoque_geral_pdf')->with(compact('titulo', 'geral','sector','total','filtro'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.estoque.geral.estoque_geral_pdf', compact('titulo', 'geral','sector','total','filtro'));
            return $pdf->stream();
        }
    }
    private function buscaGeral($filtro){
        $setor_id = $filtro["setor"] == 0 ? null : $filtro["setor"];
        $produto_id = $filtro["produto"] == 0 ? null : $filtro["produto"];
        $classe_id = $filtro["classe"] == 0 ? null : $filtro["classe"];
        $zero= $filtro["zerado"] == 0 ? null : $filtro["zerado"];

        $operadorsetor = $setor_id == null ? '!=' : '=';
        $operadorprod = $produto_id == null ? '!=' : '=';
        $operadorclass = $classe_id == null  ? '!=' : '=';
        
        $estoques = Estoquesetor::join('produtos','estoquesetors.produto_id','produtos.id')
                                  ->join('produtoclasses as classes','produtos.produtoclasse_id','classes.id')
                                  ->join('setors as setor','estoquesetors.setor_id','setor.id')
                                  ->where([
                                    ['estoquesetors.empresa_id',$this->empresa_id],
                                    ['estoquesetors.produto_id',$operadorprod,$produto_id],
                                    ['estoquesetors.setor_id',$operadorsetor,$setor_id],
                                    ['produtos.produtoclasse_id',$operadorclass,$classe_id],
                                    $zero == null ? ['estoquesetors.quantidade','!=',0] : ['estoquesetors.quantidade','!=',null],
                                    ['setor.ativo',1],
                                    ['produtos.ativo',1]
                                  ])->select('estoquesetors.id','estoquesetors.produto_id','estoquesetors.setor_id','produtos.id',
                                             'produtos.produtoretornavel_id as vasilhame','estoquesetors.quantidade','classes.id as classe_id',
                                             'produtos.descricao as produto','setor.descricao as setor','classes.descricao as classe')
                                  ->orderBy('setor.descricao')->orderBy('produtos.descricao')->get();
        $geral = collect([]);
        $setores = collect([]);
        $total = 0;
        foreach($estoques as $estoque){
            $objestoque = (object) array();
            $objsetor = (object) array();
            $objestoque->setor_id = $estoque->setor_id;
            $objsetor->setor_id = $objestoque->setor_id;
            $objsetor->setor = $estoque->setor;
            $objestoque->produto_id = $estoque->produto_id;
            $objestoque->produto = $estoque->produto;
            $objestoque->classe_id = $estoque->classe_id;
            $objestoque->classe = $estoque->classe;
            $objestoque->quantidade = $estoque->quantidade;
            $total += $estoque->quantidade;
            $geral->push($objestoque);
            $setores->push($objsetor);
        }
        $sector = collect([]);
        foreach($setores->unique('setor_id') as $setor){
            $objsector = (object) array();
            $objsector->setor = $setor->setor;
            $objsector->setor_id = $setor->setor_id;
            $objsector->total = $geral->where('setor_id',$setor->setor_id)->sum('quantidade');
            $sector->push($objsector);
        }
        $filtro = $setor_id == null ? "Setores: Todos" : " Setor: " . Setor::where('id',$setor_id)->pluck('descricao')->first();
        $filtro = $classe_id == null ? $filtro . " Classes: Todas" : $filtro . " Classe: " . Produtoclasse::where('id',$classe_id)->pluck('descricao')->first();
        $filtro = $produto_id == null ? $filtro . " Produtos: Todos" : $filtro .  " Produto: " . Produto::where('id',$produto_id)->pluck('descricao')->first();
        $filtro = $zero == null ? $filtro . " sem estoque zerado." : $filtro . " com estoque zerado.";
        $retorno["sector"] = $sector;
        $retorno["geral"] = $geral;
        $retorno["total"] = $total;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    private function definition(){
       //id empresa padrao
       $this->empresa_id = Session::get('empresa_padrao')->id;
       //id grupo empresa padrao
       $this->grupo_id =Session::get('empresa_padrao')->grupo_id; 
       //Set Empresa
       $this->repositorio->setEmpresa($this->empresa_id);
       //Set Grupo
       $this->repositorio->setGrupo($this->grupo_id);
    }

}