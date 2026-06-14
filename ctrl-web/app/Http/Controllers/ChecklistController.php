<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\User;
use Exception;
use App\Services\CarbonCustom as Carbon;
use App\Checklist;
use App\Checklistform;
use App\Http\Requests;
use App\Checklisttipo;
use App\Checklistresposta;
use App\Checklistpergunta;
use App\Checklistpesquisa;
use Illuminate\Http\Request;
use App\Checklistpesquisaresposta as Pesquisaresposta;

class ChecklistController extends Controller {
    
    private $grupo_id;
    private $empresa_id;
    private $empresas;
    private $checklists;
    private $tipochecklist;
    
    function index(Checklistpesquisa $ch) {
        $this->authorize('view',$ch);
        $this->definition();
        $tipochecklist = $this->tipochecklist;
        $empresas = $this->empresas;
        return view('checklist.pesquisachecklist')->with(compact('tipochecklist','empresas'));
    }
    
    public function checarFiltro(Checklistpesquisa $ch) {
        $this->authorize('view',$ch);
        $this->definition();
        $empresas = $this->empresas;
        $tipochecklist = $this->tipochecklist;
        $get = $_GET;
        $datainicio = Carbon::parse($get["datainicio"])->startOfDay();
        $datafim = Carbon::parse($get["datafim"])->endOfDay();
        $tipo = $get["tipo"] != "" ? $get["tipo"] : null;

        if($get["respondido"] == "0"){
            $checklists = DB::table('checklistforms as form')->join('empresas','form.empresa_id','empresas.id')
                        ->join('checklisttipos as tipo','form.checklisttipo_id','tipo.id')
                        ->where([
                            ['form.empresa_id',$get["empresa"]],
                            ['form.ativo',1]
                        ]);
            
            if(!is_null($tipo))
                $checklists = $checklists->where('form.checklisttipo_id',$tipo);
            
            $checklists = $checklists->select('form.id','form.empresa_id','empresas.nome_informal','tipo.descricao as tipo',
                                            DB::raw('0 as tipoform'))
                                     ->get();
        }else{
            $emp = User::find(\Auth::user()->id)->empresas()->pluck('id')->toArray();
            $checklists = DB::table('checklistpesquisas as pesquisa')->join('checklistforms as form','pesquisa.checklistform_id','form.id')
                        ->join('empresas','pesquisa.empresa_id','empresas.id')
                        ->join('empresas as emp2','form.empresa_id','emp2.id')
                        ->join('checklisttipos as tipo','form.checklisttipo_id','tipo.id')
                        ->whereBetween('datahorapesquisa',[$datainicio,$datafim]);

            if(empty($get["empresa"]))
                $checklists = $checklists->whereIn('pesquisa.empresa_id',$emp);
            else
                $checklists = $checklists->where('pesquisa.empresa_id',$get["empresa"]);
            if(!is_null($tipo))
                $checklists = $checklists->where('form.checklisttipo_id',$tipo);    

            $checklists = $checklists->select('pesquisa.id','form.empresa_id','tipo.descricao as tipo','emp2.nome_informal','empresas.nome_informal as empresacadastro',
                                            DB::raw('1 as tipoform'))
                                     ->get();
        }
        return view('checklist.pesquisachecklist')->with(compact('checklists','tipochecklist','empresas'));        
    }

    public function create(Request $request, Checklistpesquisa $ch) {
        $this->authorize('create',$ch);
        $get = $_GET;
        $id = $get["id"];
        $checklisttopicos = $this->checklistForm($id);
        $empresanome = $checklisttopicos->first()->empresa->nome_informal;
        $empresacnpj = $checklisttopicos->first()->empresa->cnpj;
        $empresatelefone = $checklisttopicos->first()->empresa->telefone1;
        $descricao = $checklisttopicos->first()->checklistform->descricao;

        return view('checklist.pesquisachecklist_form')->with(compact('checklisttopicos','id','empresanome','empresacnpj','empresatelefone','descricao'));
    }
    
    public function store(Request $request, Checklistpesquisa $ch) {
        $this->authorize('create',$ch);
        $this->definition();
        $data = $request->all();
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data, $dadosextras);
        DB::beginTransaction();
        try{
            $pesquisa = Checklistpesquisa::create($data);
            $resposta = $this->createRespostas($pesquisa,$data);
            if($resposta != true){
                throw new exception($resposta);
            }
        }catch(Exception $ex){
            DB::rollback();
            return Redirect::to('checklist/create?id='.$data["idchecklist"])
                        ->withErrors($ex->getMessage())
                        ->withInput();
        }
        DB::commit();
        return Redirect::to('checklist')->withMessageSuccess('Pesquisa salva com sucesso!');
    }

    public function show($id, Checklistpesquisa $ch) {
        $this->authorize('view',$ch);
        $checklist = Checklistpesquisa::find($id);
        if(!empty($checklist)){
            $this->authorize('acesso',$checklist);
            $pesquisaresposta = json_encode($checklist->checkListPesquisaResposta);
            $checklisttopicos = $this->checklistForm($checklist->checklistform_id);
            $empresanome = $checklisttopicos->first()->empresa->nome_informal;
            $empresacnpj = $checklisttopicos->first()->empresa->cnpj;
            $empresatelefone = $checklisttopicos->first()->empresa->telefone1;
            $descricao = $checklisttopicos->first()->checklistform->descricao;
            $show = 1;
            return view('checklist.pesquisachecklist_form')->with(compact('checklist','checklisttopicos','id','pesquisaresposta',
                    'show','empresanome','empresacnpj','empresatelefone','descricao'));
        }else{
            return Redirect::to('checklist/create?id='.$id);
        }
    }

    public function gerarPdf($id, Checklistpesquisa $ch) {
        $this->authorize('view',$ch);
        $checklist = Checklistpesquisa::find($id);
        if(!empty($checklist)){
            $this->authorize('acesso',$checklist);
            $empresa_id = $checklist->empresa->id;
            $empresanome = $checklist->empresa->nome_informal;
            $empresacnpj = $checklist->empresa->cnpj;
            $data = requestDataOracle($checklist->datahorapesquisa);
            $descricao = $checklist->checklistform->descricao;
            $titulo = "Pesquisa de Checklist N.:" . $checklist->id . " EMPRESA:" . $empresanome;
            $checkrespostas = $checklist->checkListPesquisaResposta;
            $topicos = $this->checklistForm($checklist->checklistform_id);
            $checklisttopicos = $this->respostasChecklist($checkrespostas,$topicos);
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('checklist.pesquisachecklist_pdf',compact('empresa_id','data','empresanome','empresacnpj','titulo',
                        'pesquisaresposta','checklisttopicos','descricao'));
            return $pdf->stream();
        }else{
            $checklist = Checklistform::find($id);
            $this->authorize('acesso',$checklist);
            $empresa_id = $checklist->empresa->id;
            $empresanome = $checklist->empresa->nome_informal;
            $empresacnpj = $checklist->empresa->cnpj;
            $descricao = $checklist->descricao;
            $data = Carbon::now()->format('d-m-Y h:m');
            $titulo = "Pesquisa de Checklist N.:" . $checklist->id . " EMPRESA:" . $empresanome;
            $checklisttopicos = $this->checklistForm($id);
            $pdf = \App::make('dompdf.wrapper');
            $pdf->loadView('checklist.pesquisachecklist_pdf',compact('checklist','empresa_id','data','empresanome',
                    'empresacnpj','titulo','pesquisaresposta','checklisttopicos','descricao'));
            return $pdf->stream();
        }
    }

    //Funções Privadas
    private function dadosExtras($data){
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $data["checklistform_id"] = $data["idchecklist"];
        $data["datahorapesquisa"] = Carbon::now();
        return $data;
    }

    private function createRespostas($pesquisa, $data){
        $respostas = false;
        $respostasradio = isset($data["respostasradio"]) ? json_decode($data["respostasradio"]) : null;
        $respostastext = isset($data["respostastext"]) ? json_decode($data["respostastext"]) : null;
        $respostascheckbox = isset($data["respostascheckbox"]) ? json_decode($data["respostascheckbox"]) : null;
        if(!empty($respostastext)){
            $respostas = $this->salvar($pesquisa,$respostastext);
        }
        if(!empty($respostasradio) && ($respostas || isset($respostas))){
            $respostas = $this->salvar($pesquisa,$respostasradio);
        }
        if(!empty($respostascheckbox) && ($respostas || isset($respostas))){
            $respostas = $this->salvar($pesquisa,$respostascheckbox);
        }
        if($respostas != true){
            return $respostas;
        }
        
        return true;
    }

    private function salvar($pesquisa,$respostas){
        DB::beginTransaction();
        try{
            foreach($respostas as $resposta){
                if($resposta->idresposta != "observacoes"){
                    $pesquisaresposta = new Pesquisaresposta();
                    $pesquisaresposta->checklistpesquisa_id = $pesquisa->id;
                    $pesquisaresposta->checklistresposta_id = $resposta->idresposta;
                    if($resposta->tipo == "radio"){
                        $pesquisaresposta->checklistpergunta_id = $resposta->idpergunta;
                        $descricao = Checklistresposta::where('id',$resposta->idresposta)->get()->pluck('descricao');
                        $pesquisaresposta->resposta = $descricao[0];
                    }else if($resposta->tipo == "texto"){
                        $pesquisaresposta->checklistpergunta_id = $resposta->idpergunta;
                        $pesquisaresposta->resposta = $resposta->resposta;
                    }else if($resposta->tipo == "data"){
                        $pesquisaresposta->checklistpergunta_id = $resposta->idpergunta;
                        $pesquisaresposta->resposta = insertDataOracle($resposta->resposta);
                    }else if($resposta->tipo == "checkbox"){
                        $pesquisaresposta->checklistpergunta_id = $resposta->idpergunta;
                        $descricao = Checklistresposta::where('id',$resposta->idresposta)->get()->pluck('descricao')->first();
                        $pesquisaresposta->resposta = $descricao;
                    }
                    $respostasalva = $pesquisaresposta->save();
                }
            }
        }catch(Exception $ex){
            DB::rollback();
            return $ex;
        }
        DB::commit();
        return true;
    }

    private function checklistForm($id){
        $checklistform = Checklistform::find($id);
        $checklisttopicos = $checklistform->checklist;
        $checklistperguntas = [];
        for($i=0;$i<count($checklisttopicos);$i++){
            $perguntas = $checklisttopicos[$i]->checklistpergunta;
            foreach($perguntas as $pergunta){
                $respostas = $pergunta->checklistresposta;
                $pergunta->respostas = $respostas;
            }
            $checklisttopicos[$i]->perguntas = $perguntas;
        }
        return $checklisttopicos;
    }

    private function respostasChecklist($checkrespostas,$checktopicos){
        foreach($checkrespostas as $checkresposta){
            foreach($checktopicos as $topicos){
                foreach($topicos->perguntas as $pergunta){
                    foreach($pergunta->respostas as $resposta){
                        switch($resposta->tipopergunta){
                            case "0":
                                if($resposta->id == $checkresposta->checklistresposta_id){
                                    $resposta->respondido = 1;
                                }
                                break;
                            case "1":
                                if($resposta->id == $checkresposta->checklistresposta_id){
                                    $resposta->respondido = $checkresposta->resposta;
                                }
                                break;
                            case "2":
                                if($resposta->id == $checkresposta->checklistresposta_id){
                                    $resposta->respondido = $checkresposta->resposta;
                                }
                                break;
                            case "3":
                                if($resposta->id == $checkresposta->checklistresposta_id){
                                    $resposta->respondido = requestDataOracle($checkresposta->resposta);
                                }
                                break;
                            case "4":
                                if($resposta->id == $checkresposta->checklistresposta_id){
                                    $resposta->respondido = 1;
                                }
                                break;
                        }
                    }
                }
            }
        }
        return $checktopicos;
    }

    private function definition(){
        //busca id do grupo
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //busca id da empresa padrao
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca checklists por empresa
        $this->checklists = Checklist::where('grupo_id',$this->grupo_id)->get();
        //busca tipos de checklists
        $this->tipochecklist = Checklisttipo::all()->pluck('descricao','id')->prepend('Selecione','');
        //busca empresas permitidas ao usuario
        $this->empresas = User::find(\Auth::user()->id)->empresas()->pluck('nome_informal','id')->prepend('Selecione','');
    }

}