<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Veiculo;
use App\Services\CarbonCustom as Carbon;
use App\Veiculotipo;
use App\Veiculotrocaoleo;
use Illuminate\Http\Request;
use App\Veiculoabastecimento;
use App\Repository\SelectRepository;
use App\Veiculoentradasaida as Entradasaida;
use App\Veiculoentradasaidapedidos as Entradasaidapedidos;

class ReportveiculosController extends Controller
{
    private $empresa_id;
    private $grupo_id;
    private $repositorio;

    public function __construct(SelectRepository $repositorio){
        $this->repositorio = $repositorio;
    }
    
    //////////////////// Abastecimento de Veiculo ///////////////////
    public function abastecimentoIndex(){
        $this->definition();
        $veiculos = $this->repositorio->getVeiculos()->prepend("Selecione","");
        return view('reports.veiculos.abastecimento')->with(compact('veiculos'));
    }

    public function abastecimentoPdf(){
        $this->definition();
        $get = $_GET;
        $abastecimento = $this->buscaAbastecimento($get);
        $tipo = $get["tipo"];
        $veiculos = $abastecimento["veiculos"];
        $filtro = $abastecimento["filtro"];
        $titulo = 'Relatório de Abastecimento';
        if($tipo == "1"){
            return view('reports.veiculos.abastecimento_pdf')->with(compact('titulo','veiculos','filtro'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.veiculos.abastecimento_pdf', compact('titulo','veiculos','filtro'));
            return $pdf->stream();
        }
    }

    private function buscaAbastecimento($filtro){
        $dataincio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $veiculo_id = $filtro["veiculo"] == 0 ? null : $filtro["veiculo"];
        $operadorveiculo = $veiculo_id == null ? '!=' : '=';
        $abastecimentos = Veiculoabastecimento::join('veiculos','veiculoabastecimentos.veiculo_id','veiculos.id')
                            ->join('colaboradors as colaborador','veiculoabastecimentos.colaborador_id','colaborador.id')
                            ->whereBetween('data',[$dataincio,$datafim])
                            ->where([
                                ['veiculoabastecimentos.empresa_id',$this->empresa_id],
                                ['veiculoabastecimentos.veiculo_id',$operadorveiculo,$veiculo_id]
                            ])->select('veiculos.placa','veiculoabastecimentos.id','veiculoabastecimentos.veiculo_id',
                                    'veiculoabastecimentos.colaborador_id','colaborador.nome','veiculoabastecimentos.data',
                                    'veiculoabastecimentos.kmanterior','veiculoabastecimentos.kmatual',
                                    'veiculoabastecimentos.kmatual','veiculoabastecimentos.kmrodado','veiculos.descricao',
                                    'veiculoabastecimentos.totallitros','veiculoabastecimentos.mediaconsumo')
                            ->orderBy('veiculos.placa')->orderBy('veiculoabastecimentos.data')->get();
        $veiculos = collect([]);
        $anterior = null;
        $count = 0;
        $countreg = 0;
        foreach($abastecimentos as $abastecimento){
            if(!is_null($anterior) && $anterior != $abastecimento->veiculo_id){
                $objtotal = (object) array();
                $wvei = $abastecimentos->where('veiculo_id',$anterior);
                $objtotal->totalrodado = $wvei->sum('kmrodado');
                $litros = $wvei->sum('totallitros');
                $objtotal->totallitros = number_format($litros,3,',','.');
                $objtotal->media = number_format($objtotal->totalrodado / $litros,3,',','.');
                $veiculos->push($objtotal);
            }
            if(is_null($anterior) || $anterior != $abastecimento->veiculo_id){
                $objveiculo = (object) array();
                $objveiculo->veiculo_id = $abastecimento->veiculo_id;
                $objveiculo->placa = $abastecimento->placa;
                $objveiculo->veiculo = $abastecimento->descricao;
                $veiculos->push($objveiculo);
            }
            $objnormal = (object) array();
            $objnormal->data = requestDataOracle($abastecimento->data);
            $objnormal->colaborador = $abastecimento->nome;
            $objnormal->kmanterior = $abastecimento->kmanterior;
            $objnormal->kmatual = $abastecimento->kmatual;
            $objnormal->kmrodado = $abastecimento->kmrodado;
            $objnormal->litros = number_format($abastecimento->totallitros,3,',','.');
            $objnormal->mediaconsumo = number_format($abastecimento->mediaconsumo,3,',','.');
            $veiculos->push($objnormal);
            $anterior = $abastecimento->veiculo_id;
            $count++;
            $countreg++;
            if($count == count($abastecimentos)){
                $objtotal = (object) array();
                $wvei = $abastecimentos->where('veiculo_id',$abastecimento->veiculo_id);
                $objtotal->totalrodado = $wvei->sum('kmrodado');
                $litros = $wvei->sum('totallitros');
                $objtotal->totallitros = number_format($litros,3,',','.');
                $objtotal->media = number_format($objtotal->totalrodado / $litros,3,',','.');
                $veiculos->push($objtotal);
            }
        }
        $filtro = "Filtro Período: " . RequestDataOracle($dataincio,false,true) . " a " . requestDataOracle($datafim,false,true);
        $filtro = $veiculo_id == null ? $filtro . ", Veiculo: todos" : $filtro . ", Veiculo: " . implode(' - ',Veiculo::where('id',$veiculo_id)->select('placa','descricao')->get()->first()->toArray());
        $retorno["veiculos"] = $veiculos;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }
    //////////////// Gestão de Frota ////////////////
    public function frotaIndex(){
        $this->definition();
        $veiculos = $this->repositorio->getVeiculos()->prepend("Selecione","");
        return view('reports.veiculos.gestaofrota')->with(compact('veiculos'));
    }

    public function frotaPdf(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"];
        $frota = $this->buscaFrota($get);
        $geral = $frota["geral"];
        $filtro = $frota["filtro"];
        $titulo = 'Movimentação Veicular';
        if($tipo == 1){
            return view('reports.veiculos.gestaofrota_pdf')->with(compact('titulo','geral','filtro'));
        }
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView('reports.veiculos.gestaofrota_pdf', compact('titulo','geral','filtro'))->setPaper('a4','landscape');
        return $pdf->stream();
    }

    private function buscaFrota($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $veiculo_id = $filtro["veiculo"] == 0 ? null : $filtro["veiculo"];

        $veiculos = DB::table('veiculoentradasaidas saida')->join('veiculos','saida.veiculo_id','veiculos.id')
                            ->join('veiculoentradasaidapedidos ped','ped.entradasaida_id','saida.id')
                            ->join('pedidos','ped.pedido_id','pedidos.id')
                            ->whereBetween('saida.datahora',[$datainicio,$datafim])
                            ->where([
                                ['saida.empresa_id',$this->empresa_id]
                            ]);
        if(!is_null($veiculo_id))
            $veiculos = $veiculos->where('veiculos.id',$veiculo_id);
        $veiculos = $veiculos->select(DB::raw("to_char(saida.datahora,'yyyy-mm-dd') data"),'veiculos.descricao as veiculo',
                                'saida.kmrodado','veiculos.placa',
                                'saida.temporodado','saida.ultimokm',DB::raw("count(ped.pedido_id) quantidade"),
                                DB::raw("sum((select sum(quantidade) from pedidoitems where pedido_id = pedidos.id)) qtd_prod"), 
                                DB::raw("(saida.kmrodado / count(ped.id)) as km_entrega"))
                             ->groupBy('veiculos.placa')->groupBy('saida.datahora')->groupBy('veiculos.descricao')
                             ->groupBy('saida.kmrodado')->groupBy('saida.temporodado')->groupBy('saida.ultimokm')
                             ->orderBy('data')->orderBy('placa')->get();
// dd($veiculos); 
        $colecaoveiculo = collect([]);
        $t = collect([]);
        $anterior = null;
        $dataanterior = null;
        $count = 0;
        $time = 0;
        $tempodata = 0;
        $countdata = 0;
        foreach($veiculos as $veiculo){
            if(!is_null($dataanterior) && $dataanterior != $veiculo->data){
                $wdata = $veiculos->where('data',$dataanterior);
                $objtotaldata = (object) array();
                $objtotaldata->totalkm = $wdata->sum('kmrodado');
                $objtotaldata->totalrodado = $this->secondsToTime($tempodata);
                $objtotaldata->totalped = $wdata->sum('quantidade');
                $objtotaldata->totalprod = $wdata->sum('qtd_prod');
                $objtotaldata->totkmentrega = $objtotaldata->totalkm / $objtotaldata->totalped;
                $objtotaldata->tempoentrega = $this->secondsToTime($tempodata / $objtotaldata->totalped);
                $colecaoveiculo->push($objtotaldata);
                $tempodata = 0;
                $countdata++;
            }
            if(is_null($dataanterior) || $dataanterior != $veiculo->data){
                $objdata = (object) array();
                $objdata->data = requestDataOracle($veiculo->data,false);
                $colecaoveiculo->push($objdata);
            }
            $objnormal = (object) array();
            $objnormal->veiculo = "$veiculo->placa - $veiculo->veiculo";
            $objnormal->kmrodado = $veiculo->kmrodado;
            $objnormal->temporodado = $veiculo->temporodado;
            $objnormal->qtdaped = $veiculo->quantidade;
            $objnormal->qtdaprod = $veiculo->qtd_prod;
            $objnormal->kmentrega = $veiculo->km_entrega;
            $texp = explode(' ',$veiculo->temporodado);
            $teexp = explode(':',$texp[1]);
            $dias = $texp[0];
            $horas = $teexp[0];
            $minutos = $teexp[1];
            $segundos = $teexp[2];
            $segundostotais = (($dias * 86400) + ($horas * 3600) + ($minutos * 60) + $segundos);
            $objnormal->tempopedido = $this->secondsToTime($segundostotais / $objnormal->qtdaped);
            $colecaoveiculo->push($objnormal);
            $count++;
            $tempodata += (int) $segundostotais;
            $time += (int) $segundostotais;
            $dataanterior = $veiculo->data;
            if($count == count($veiculos)){
                if($countdata > 1){
                    $wdata = $veiculos->where('data',$dataanterior);
                    $objtotaldata = (object) array();
                    $objtotaldata->totalkm = $wdata->sum('kmrodado');
                    $objtotaldata->totalrodado = $this->secondsToTime($tempodata);
                    $objtotaldata->totalped = $wdata->sum('quantidade');
                    $objtotaldata->totalprod = $wdata->sum('qtd_prod');
                    $objtotaldata->totkmentrega = $objtotaldata->totalkm / $objtotaldata->totalped;
                    $objtotaldata->tempoentrega = $this->secondsToTime($tempodata / $objtotaldata->totalped);
                    $colecaoveiculo->push($objtotaldata);
                }
                $objfinal = (object) array();
                $objfinal->geralkm = $colecaoveiculo->sum('kmrodado');
                $objfinal->geralrodado = $this->secondsToTime($time);
                $objfinal->geralped = $colecaoveiculo->sum('qtdaped');
                $objfinal->geralprod = $colecaoveiculo->sum('qtdaprod');
                $objfinal->geralkmentrega = $objfinal->geralkm / $objfinal->geralped;
                $objfinal->geraltempoentrega = $this->secondsToTime($time / $objfinal->geralped);
                $colecaoveiculo->push($objfinal);

            }
        }
        // dd($colecaoveiculo);
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false,true) . " a " . requestDataOracle($datafim,false,true);
        $filtro = $veiculo_id == null ? $filtro . ", Veiculo: todos." : $filtro . ", Veiculo: " . implode(' - ',Veiculo::where('id',$veiculo_id)->select('placa','descricao')->get()->first()->toArray());
        $retorno["geral"] = $colecaoveiculo;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    private function ensure2Digit($number) {
        if($number < 10) {
            $number = '0' . $number;
        }
        return $number;
    }

    private function secondsToTime($ss) {
        $s = $this->ensure2Digit($ss%60);
        $m = $this->ensure2Digit(floor(($ss%3600)/60));
        $h = $this->ensure2Digit(floor(($ss%86400)/3600));
        $d = $this->ensure2Digit(floor(($ss/86400)));
        return "$d $h:$m:$s";
    }

    //////////////Report Troca de Oleo
    public function oleoIndex(){
        $this->definition();
        $veiculos = $this->repositorio->getVeiculos()->prepend("Selecione","");
        $tipoveiculo = $this->repositorio->getTipoVeiculos()->prepend('Selecione','');
        return view('reports.veiculos.trocaoleo')->with(compact('veiculos','tipoveiculo'));
    }

    public function pdfTroca(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"];
        $oleo = $this->buscaTroca($get);
        $trocaoleo = $oleo["oleo"];
        $oleoveiculo = $oleo["veiculo"];
        $filtro = $oleo["filtro"];
        $titulo = 'Previsão de Troca de Óleo';
        if($tipo == 1){
            return view('reports.veiculos.trocaoleo_pdf')->with(compact('titulo','trocaoleo','oleoveiculo','filtro'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.veiculos.trocaoleo_pdf', compact('titulo','trocaoleo','oleoveiculo','filtro'));
            return $pdf->stream();
        }
    }

    private function buscaTroca($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $veiculo_id = $filtro["veiculo"] == 0 ? null : $filtro["veiculo"];

        $operadorveiculo = $veiculo_id == null ? '!=' : $veiculo_id;
        $trocaoleo = Veiculotrocaoleo::join('veiculos','veiculotrocaoleos.veiculo_id','veiculos.id')
                                    ->join('colaboradors as colaborador','veiculotrocaoleos.colaborador_id','colaborador.id')
                                    ->whereBetween('data',[$datainicio,$datafim])
                                    ->where([
                                        ['veiculotrocaoleos.empresa_id',$this->empresa_id],
                                        ['veiculotrocaoleos.veiculo_id',$operadorveiculo,$veiculo_id]
                                    ])->select('veiculotrocaoleos.veiculo_id','colaborador.nome','veiculos.placa',
                                               'veiculos.descricao as veiculo','veiculotrocaoleos.data',
                                               'veiculotrocaoleos.kmtrocaoleo','veiculotrocaoleos.oleoproximatroca')
                                    ->orderBy('veiculos.placa')->orderBy('veiculotrocaoleos.data')->get();
        $colecaotroca = collect([]);
        $colecaoveiculo = collect([]);
        foreach($trocaoleo as $oleo){
            $obj = (object) array();
            $objveiculo = (object) array();
            $obj->veiculo_id = $oleo->veiculo_id;
            $objveiculo->veiculo_id = $obj->veiculo_id;
            $objveiculo->veiculo = $oleo->placa . " - " . $oleo->veiculo;
            $obj->datatroca = requestDataOracle($oleo->data,false);
            $obj->condutor = $oleo->nome;
            $obj->kmtroca = $oleo->kmtrocaoleo;
            $obj->proximatroca = $oleo->oleoproximatroca;
            $colecaotroca->push($obj);
            $colecaoveiculo->push($objveiculo);
        }
        $filtro = "FIltro: Período " . requestDataOracle($datainicio,false,true) . " a " . requestDataOracle($datafim,false,true);
        $filtro = $veiculo_id == null ? $filtro . ", Veículo(s): todos." : $filtro . ", Veículo: " . Veiculo::where('id',$veiculo_id)->pluck('descricao')->first();
        $retorno["filtro"] = $filtro;
        $retorno["oleo"] = $colecaotroca;
        $retorno["veiculo"] = $colecaoveiculo->unique('veiculo_id');
        return $retorno;
    }

    public function pdfTrocaVencida(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"];
        $vencido = $this->buscaVencidos($get);
        $vencidos = $vencido["veiculos"];
        $classes = $vencido["classe"];
        $filtro = $vencido["filtro"];
        $titulo = 'Troca de Óleo Vencida';
        if($tipo == "1"){
            return view('reports.veiculos.trocaoleovencido_pdf')->with(compact('titulo','classes','vencidos','filtro'));
        }
        $pdf = \App::make('dompdf.wrapper');
        $pdf->loadView('reports.veiculos.trocaoleovencido_pdf', compact('titulo','classes','vencidos','filtro'));
        return $pdf->stream();
    }

    private function buscaVencidos($filtro){
        $tipo_id = $filtro["tipoveiculo"] == 0 ? null : $filtro["tipoveiculo"];
        $operadortipo = $tipo_id == null ? '!=' : '=';
        $oleos = Veiculotrocaoleo::with('veiculo.colaborador')->rightJoin('veiculos','veiculos.id','veiculotrocaoleos.veiculo_id')
                                ->join('veiculotipos as classe','classe.id','veiculos.veiculotipo_id')
                                ->where([
                                    ['veiculotrocaoleos.empresa_id',$this->empresa_id],
                                    ['veiculos.veiculotipo_id',$operadortipo,$tipo_id]
                                ])->select('veiculotrocaoleos.id','veiculotrocaoleos.oleoproximatroca','veiculos.kmatual',
                                    'veiculotrocaoleos.veiculo_id','veiculotrocaoleos.colaborador_id','veiculos.veiculotipo_id',
                                    'veiculotrocaoleos.kmultimatrocaoleo','veiculos.placa','veiculos.descricao',
                                    'classe.descricao as classe')
                                ->orderBy('veiculotrocaoleos.id','DESC')->orderBy('classe')->orderBy('veiculos.placa')
                                ->get()->unique('veiculo_id');
        // dd($oleos);
        $colecaoveiculos = collect([]);
        $colecaoclasse = collect([]);
        foreach($oleos as $oleo){
            if($oleo->kmatual > $oleo->oleoproximatroca){
                $obj = (object) array();
                $objclasse = (object) array();
                $obj->veiculo_id = $oleo->veiculo_id;
                $obj->placa = $oleo->placa;
                $obj->descricao = $oleo->descricao;
                $obj->motorista = $oleo->veiculo->colaborador->nome;
                $obj->classe_id = $oleo->veiculotipo_id;
                $objclasse->classe_id = $obj->classe_id;
                $objclasse->classe = $oleo->classe;
                $obj->ultimatroca = $oleo->kmultimatrocaoleo;
                $obj->kmatual = $oleo->kmatual;
                $obj->proximatroca = $oleo->oleoproximatroca;
                $colecaoveiculos->push($obj);
                $colecaoclasse->push($objclasse);
            }
        }
        $filtro = $tipo_id == null ? "Filtro: Tipo de Veículo: todos." : "Filtro: Tipo de Veículo: " . Veiculotipo::where('id',$tipo_id)->pluck('descricao')->first();
        $retorno["veiculos"] = $colecaoveiculos;
        $retorno["classe"] = $colecaoclasse->unique('classe_id')->reverse();
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    private function definition(){
        //define id da empresa logada
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->id;
        //set empresa no repositorio
        $this->repositorio->setEmpresa($this->empresa_id);
        //set grupo da empresa no repositorio
        $this->repositorio->setGrupo($this->grupo_id);
    }
}