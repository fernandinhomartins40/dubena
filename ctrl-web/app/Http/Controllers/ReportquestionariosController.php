<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\User;
use App\Setor;
use App\Empresa;
use App\Services\CarbonCustom as Carbon;
use App\Colaborador;
use App\Checklistform;
use App\Checklisttipo;
use App\Checklistpesquisa;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;

class ReportquestionariosController extends Controller
{
    private $grupo_id;
    private $empresa_id;
    private $repository;

    function __construct(SelectRepository $repository){
        $this->repository = $repository;
    }

    public function indexPosVenda(){
        $this->definition();
        $empresas = $this->repository->getEmpresasUser();
        $empresas_id = User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray();
		$emp = Empresa::whereIn('id', $empresas_id)->orderBy('nome_informal')->get();
        $setores = Setor::where([['ativo', 1]])->whereIn('empresa_id', $empresas_id)->orderBy('descricao')->get();
        $col = DB::table('setorcolaboradores as setcol')
                    ->join('colaboradors as col','setcol.colaborador_id','col.id')
                    ->where('col.ativo',1)->whereIn('col.empresa_id',$empresas_id)
                    ->select('setcol.setor_id','col.nome','col.id','col.empresa_id')
                    ->get();
        $setor = [];
        $colaborador = [];
		foreach ($emp as $empresa) {
			$set = $setores->where('empresa_id', $empresa->id);
            $setor[$empresa->nome_informal] = $set->pluck('descricao', 'id')->toArray();
            $cole = $col->where('empresa_id',$empresa->id);
            $colaborador[$empresa->nome_informal] = $cole->pluck('nome','id')->toArray();
        }
        return view('reports.questionarios.posvendas.posvenda_index')->with(compact('empresas','setor','colaborador'));
    }

    public function posvendaFiltro(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"] == "1";
        $titulo = "Relatório de Pesquisas de Pós-venda";
        $pos = $this->buscaPos($get);
        $filtro = $pos["filtro"];
        $posvenda = $pos["posvenda"];
        if($tipo){
            return view('reports.questionarios.posvendas.posvenda_pdf')->with(compact('titulo','filtro','posvenda'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.questionarios.posvendas.posvenda_pdf', compact('titulo','filtro','posvenda'));
            return $pdf->stream();
        }
    }

    private function buscaPos($filtro){
        $datainicio = Carbon::parse($filtro["datainicio"])->startOfDay();
        $datafim = Carbon::parse($filtro["datafim"])->endOfDay();
        $empresas = User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray();
        $empresas_id = $filtro["empresa"] == 0 ? $empresas : explode(',',$filtro["empresa"]);
        $setor_id = $filtro["setor"] == 0 ? null : explode(',',$filtro["setor"]);
        $colaborador_id = $filtro["colaborador"] == 0 ? null : explode(',',$filtro["colaborador"]);

        $pos = DB::table('posvendapesquisas as pesquisas')->join('posvendas','pesquisas.posvenda_id','posvendas.id')
                        ->join('empresas','posvendas.empresa_id','empresas.id')
                        ->join('posvendapesquisarespostas as pres','pres.posvendapesquisa_id','pesquisas.id')
                        ->join('posvendarespostas as respostas','pres.posvendaresposta_id','respostas.id')
                        ->join('posvendaperguntas as perguntas','respostas.posvendapergunta_id','perguntas.id')
                        ->leftJoin('pedidos','pesquisas.pedido_id','pedidos.id')
                        ->whereBetween('pesquisas.datahora',[$datainicio,$datafim])
                        ->whereIn('posvendas.empresa_id',$empresas_id);
        if(!is_null($setor_id))
            $pos = $pos->whereIn('pesquisas.setor_id',$setor_id);
        if(!is_null($colaborador_id))
            $pos = $pos->whereIn('pedidos.colaborador_id',$colaborador_id);
        $pos = $pos->select('pesquisas.posvenda_id','posvendas.descricao as posvenda','perguntas.id as pergunta_id',
                        'perguntas.descricao as pergunta','respostas.id as resposta_id','respostas.descricao as resposta',
                        'posvendas.empresa_id','empresas.nome_informal as empresa',
                        DB::raw('(count(pres.posvendaresposta_id)) as quantidade'))
                    ->groupBy('pesquisas.posvenda_id','posvendas.descricao','perguntas.id','perguntas.descricao','respostas.id',
                        'respostas.descricao','posvendas.empresa_id','empresas.nome_informal')
                    ->orderBy('empresa')->orderBy('pesquisas.posvenda_id')
                    ->orderBy('perguntas.descricao')->orderBy('respostas.descricao')->get();
        $posvendas_id = $pos->unique('posvenda_id')->pluck('posvenda_id')->toArray();
        if(count($pos) > 0){
            $pesquisa = DB::table('posvendapesquisas as pesquisas')->leftJoin('clientes','pesquisas.cliente_id','clientes.id')
                                ->leftJoin('pedidos','pesquisas.pedido_id','pedidos.id')
                                ->whereIn('pesquisas.posvenda_id',$posvendas_id)
                                ->whereBetween('pesquisas.datahora',[$datainicio,$datafim])
                                ->whereRaw('pesquisas.observacao is not null');
            if(!is_null($setor_id))
                $pesquisa = $pesquisa->whereIn('pesquisas.setor_id',$setor_id);
            if(!is_null($colaborador_id))
                $pesquisa = $pesquisa->whereIn('pedidos.colaborador_id',$colaborador_id);
            $pesquisa = $pesquisa->select('pesquisas.id as pesquisa_id','pesquisas.posvenda_id','clientes.id as cliente_id',
                                    'clientes.nome','pesquisas.observacao','pesquisas.pedido_id')
                                 ->orderBy('clientes.nome')->get();
        }
        $posvendas = collect([]);
        $empanterior = null;
        $posanterior = null;
        $peranterior = null;
        $count = 0;
        foreach($pos as $venda){
            if(is_null($empanterior) || $empanterior != $venda->empresa_id){
                $objempresa = (object) array();
                $objempresa->empresa_id = $venda->empresa_id;
                $objempresa->empresa = $venda->empresa;
                $posvendas->push($objempresa);
            }
            if(is_null($posanterior) || $posanterior != $venda->posvenda_id){
                $objpos = (object) array();
                $objpos->posvenda_id = $venda->posvenda_id;
                $objpos->posvenda = $venda->posvenda;
                $posvendas->push($objpos);
                $count = 0;
            }
            if(is_null($peranterior) || $peranterior != $venda->pergunta_id){
                $objpergunta = (object) array();
                $objpergunta->pergunta_id = $venda->pergunta_id;
                $objpergunta->pergunta = $venda->pergunta;
                $posvendas->push($objpergunta);
            }
            $totalpergunta = $pos->where('pergunta_id',$venda->pergunta_id)->sum('quantidade');
            $objnormal = (object) array();
            $objnormal->resposta_id = $venda->resposta_id;
            $objnormal->resposta = $venda->resposta;
            $objnormal->quantidade = $venda->quantidade;
            $porcentagem = (($objnormal->quantidade / $totalpergunta) * 100);
            $objnormal->percentual = number_format($porcentagem,2,',','');
            $posvendas->push($objnormal);
            $empanterior = $venda->empresa_id;
            $posanterior = $venda->posvenda_id;
            $peranterior = $venda->pergunta_id;
            $countpos = $pos->where('posvenda_id',$venda->posvenda_id)->count();
            $count++;
            if($count == $countpos){
                $totalobs = $pesquisa->where('posvenda_id',$posanterior)->count();
                if($totalobs > 0){
                    $objcabecalho = (object) array();
                    $objcabecalho->cabecalho = true;
                    $posvendas->push($objcabecalho);
                    foreach($pesquisa as $cliente){
                        if($cliente->posvenda_id == $posanterior){
                            $objcliente = (object) array();
                            $objcliente->pesquisa_id = $cliente->pesquisa_id;
                            $objcliente->cliente_id = $cliente->cliente_id;
                            $objcliente->cliente = $cliente->nome;
                            $objcliente->observacao = $cliente->observacao;
                            $posvendas->push($objcliente);
                        }
                    }
                }
            }
        }
        $filtro = "Filtro: Período " . requestDataOracle($datainicio,false) . " a " . requestDataOracle($datafim,false);
        $filtro = $empresas_id == null || count($empresas) == count($empresas_id) ? "$filtro, Empresa(s): todas" : 
                            "$filtro, Empresa(s): " . implode(', ',Empresa::whereIn('id',$empresas_id)->pluck('nome_informal')->toArray());
        $filtro = $setor_id == null ? "$filtro, Setor(es): todos" :
                                      "$filtro, Setor(es): " . implode(', ',Setor::whereIn('id',$setor_id)->pluck('descricao')->toArray());
        $filtro = $colaborador_id == null ? "$filtro, Colaborador(es): todos." :
                                      "$filtro, Colaborador(es): " . implode(', ',Colaborador::whereIn('id',$colaborador_id)->pluck('nome')->toArray())
                                      .".";
        $retorno["filtro"] = $filtro;
        $retorno["posvenda"] = $posvendas;
        return $retorno;
    }

    //Checklist 
    # Informar que foi definido que o tipo de checklist é obrigatorio.
    public function checkIndex(){
        $this->definition();
        $empresas = $this->repository->getEmpresasUser();
        $tipo = Checklisttipo::all()->pluck('descricao','id')->prepend('Selecione','');
        return view('reports.questionarios.checklists.checklist_index')->with(compact('empresas','tipo'));
    }

    public function checkAjax(){
        $get = $_GET;
        $tipo_id = $get["tipocheck"];
        $empresas_id = $get["empresa"] == "0" ? User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray() : 
                        explode(',',$get["empresa"]);
        $form = Checklistform::whereIn('empresa_id',$empresas_id)->where('checklisttipo_id',$tipo_id)->pluck('id')->toArray();
        $pesquisas = Checklistpesquisa::whereIn('checklistform_id',$form)->select('datahorapesquisa','id','empresa_id')->get();
        $ids = $pesquisas->pluck('empresa_id')->toArray();
        $emp = Empresa::whereIn('id', $ids)->orderBy('nome_informal')->get();
        $checklist = array();
        foreach($emp as $empresa){
            $ch = $pesquisas->where('empresa_id',$empresa->id);
            $checklist[$empresa->nome_informal] = $ch->pluck('datahorapesquisa','id')->toArray();
        }
        return $checklist;
    }

    public function filtroCheck(){
        $this->definition();
        $get = $_GET;
        $tipo = $get["tipo"] == "1";
        $titulo = "Relatório de Checklist";
        $check = $this->buscaChecklist($get);
        $filtro = $check["filtro"];
        $checklists = $check["checklists"];
        if($tipo){
            return view('reports.questionarios.checklists.checklist_pdf')->with(compact('titulo','filtro','checklists'));
        }else{
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('reports.questionarios.checklists.checklist_pdf', compact('titulo','filtro','checklists'));
            return $pdf->stream();
        }
    }

    private function buscaChecklist($filtro){
        $empresa_id = $filtro["empresa"] == "0" ? User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray() : 
                            explode(',',$filtro["empresa"]);
        $tipocheck = $filtro["tipocheck"] == "0" ? null : $filtro["tipocheck"];
        $check = $filtro["check"] == "0" ? null : explode(',',$filtro["check"]);
        
        $pesquisas = DB::table('checklistpesquisas as pesquisas')
                        ->join('checklistforms as formulario',function($join){
                                $join->on('pesquisas.checklistform_id','formulario.id');
                            })
                        ->join('empresas','formulario.empresa_id','empresas.id')
                        ->join('checklists',function($join){
                                $join->on('checklists.checklistform_id','formulario.id');
                            })
                        ->join('checklistperguntas as perguntas','perguntas.checklist_id','checklists.id')
                        ->join('checklistrespostas as respostas','respostas.checklistpergunta_id','perguntas.id')
                        ->leftJoin('checklistpesquisarespostas as pres',function($join){
                                $join->on('pres.checklistpesquisa_id','pesquisas.id');
                                $join->on('pres.checklistpergunta_id','perguntas.id');
                                $join->on('pres.checklistresposta_id','respostas.id');
                            })
                        ->whereIn('formulario.empresa_id',$empresa_id)
                        ->where('formulario.checklisttipo_id',$tipocheck);
                        
        if(!is_null($check))
            $pesquisas = $pesquisas->whereIn('pesquisas.id',$check);

        $pesquisas = $pesquisas->select('pesquisas.empresa_id','empresas.nome_informal as empresa','pesquisas.id','pesquisas.datahorapesquisa as data',
                                    'pesquisas.checklistform_id as formulario_id','formulario.descricao as formulario','checklists.id as topico_id',
                                    'checklists.descricao as topico','perguntas.id as pergunta_id','perguntas.descricao as pergunta',
                                    'respostas.id as resposta_id','respostas.descricao as respostas','respostas.tipopergunta',
                                    'pres.checklistresposta_id as respondida','pres.resposta as pesquisa_resposta','pesquisas.observacoes')
                               ->orderBy('empresa')->orderBy('pesquisas.id')->orderBy('formulario')->orderBy('topico_id')
                               ->orderBy('pergunta_id')->orderBy('resposta_id')->get();
        // dd($pesquisas);
        $checklists = collect([]);
        $empanterior = null;
        $pesqanterior = null;
        $formanterior = null;
        $topanterior = null;
        $perganterior = null;
        $count = 0;
        foreach($pesquisas as $pesquisa){
            if(is_null($empanterior) || $empanterior != $pesquisa->empresa_id){
                $objempresa = (object) array();
                $objempresa->empresa_id = $pesquisa->empresa_id;
                $objempresa->empresa = $pesquisa->empresa;
                $checklists->push($objempresa);
            }
            if(is_null($pesqanterior) || $pesqanterior != $pesquisa->id){
                $objpesquisa = (object) array();
                $objpesquisa->pesquisa_id = $pesquisa->id;
                $objpesquisa->data = $pesquisa->formulario . " dia " . requestDataOracle($pesquisa->data,false);
                $checklists->push($objpesquisa);
                $count = 0;
            }
            if(is_null($topanterior) || $topanterior != $pesquisa->topico_id){
                $objtopico = (object) array();
                $objtopico->topico_id = $pesquisa->topico_id;
                $objtopico->topico = $pesquisa->topico;
                $checklists->push($objtopico);
            }
            if(is_null($perganterior) || $perganterior != $pesquisa->pergunta_id){
                $objpergunta = (object) array();
                $objpergunta->pergunta_id = $pesquisa->pergunta_id;
                $objpergunta->pergunta = $pesquisa->pergunta;
                $checklists->push($objpergunta);
            }
            $objnormal = (object) array();
            $objnormal->resposta_id = $pesquisa->resposta_id;
            $objnormal->tipopergunta = $pesquisa->tipopergunta;
            $objnormal->respostas = $pesquisa->respostas;
            $objnormal->respondida = $pesquisa->respondida;
            $objnormal->check_resposta = $pesquisa->pesquisa_resposta;
            $checklists->push($objnormal);
            $empanterior = $pesquisa->empresa_id;
            $pesqanterior = $pesquisa->id;
            $formanterior = $pesquisa->formulario_id;
            $topanterior = $pesquisa->topico_id;
            $perganterior = $pesquisa->pergunta_id;
            $count++;
            $countpesquisa = $pesquisas->where('id',$pesquisa->id)->count();
            if($count == $countpesquisa){
                $objobs = (object) array();
                $objobs->observacao = $pesquisa->observacoes == null ? "" : $pesquisa->observacoes;
                $checklists->push($objobs);
            }
        }
        if(!is_null($check)){
            $checklista = Checklistpesquisa::whereIn('id',$check)->pluck('datahorapesquisa');
            $ch = collect([]);
            foreach ($checklista as $c) {
                // dd($c);
                $data = requestDataOracle($c,false);
                $ch->push($data);
            }
        }
        $empresas = User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray();
        $filtro = $empresa_id == null || count($empresas) == count($empresa_id) ? "Filtro Empresa(s): todas" : 
                    "Filtro Empresa(s): " . implode(', ',Empresa::whereIn('id',$empresa_id)->pluck('nome_informal')->toArray());
        $filtro = "$filtro, Tipo de Checklist: " . Checklisttipo::where('id',$tipocheck)->pluck('descricao')->first();
        $filtro = !isset($ch) ? "$filtro, Checklist: todos." : "$filtro, Checklist: " . implode(', ',$ch->toArray()).".";
        $retorno["checklists"] = $checklists;
        $retorno["filtro"] = $filtro;
        return $retorno;
    }

    private function definition(){
        #busca id da empresa
        $this->empresa_id = Session::get('empresa_padrao')->id;
        #busca grupo da empresa
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        #set empresa repository
        $this->repository->setEmpresa($this->empresa_id);
        #set grupo repository
        $this->repository->setGrupo($this->grupo_id);
    }
}
