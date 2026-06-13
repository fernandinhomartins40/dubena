<?php

namespace App\Http\Controllers;

use DB;
use Redirect;
use Exception;
use App\Services\CarbonCustom as Carbon;
use App\Checklist;
use App\Checklisttipo;
use App\Checklistform;
use App\Checklistpergunta;
use App\Checklistresposta;
use App\Checklistpesquisa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CadastrochecklistController extends Controller {

    protected $empresa_id;
    protected $grupo_id;
    protected $checklists;
    protected $tipochecklist;
    protected $error;

    function index(Checklistform $foo) {
        $this->authorize('view',$foo);
        $this->definitions();
        $checklists = $this->checklists;
        return view('checklist.checklistcadastro')->with(compact('checklists'));
    }

    public function create(Checklistform $foo) {
        $this->authorize('create',$foo);
        $this->definitions();
        $checklists = $this->checklists;
        $tipochecklist = $this->tipochecklist;
        $tiporesposta = $this->tipoResposta();
        return view('checklist.cadastrochecklist_form')->with(compact('checklists', 'tipochecklist', 'tiporesposta'));
    }

    function store(Request $request, Checklistform $foo) {
        $this->authorize('create',$foo);
        $this->definitions();
        $data = $request->all();
        $topicos = json_decode($data["topicos_hd"]);
        $perguntas = $data["perguntas"];
        $respostas = $data["respostas"];
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data, $dadosextras);
        DB::begintransaction();
        try {
            $chelistsform = Checklistform::create($data);
            $topicosid = $this->createTopicos($topicos, $chelistsform);
            if ($topicosid != false) {
                $perguntasid = $this->createPerguntas($perguntas, $topicosid);
            }
            if($perguntasid != false){
                $answer = $this->createRespostas($respostas, $perguntasid);
            }
        } catch (Exception $ex) {
            DB::rollback();
            return Redirect::to('cadastrochecklist.create')
                            ->withErrors($ex->getMessage())
                            ->withInput();
        }
        DB::commit();
        return Redirect::to('cadastrochecklist')->withMessageSuccess('Formulario de Checklist cadastrado com sucesso!');
    }

    function edit($id, Checklistform $foo) {
        $this->authorize('update',$foo);
        $checklist = Checklistform::find($id);
        $this->authorize('igualdade',$checklist);
        $this->definitions();
        $pesquisa = Checklistpesquisa::where('checklistform_id',$id)->get()->first();
        $checklisttopicos = Checklist::where('checklistform_id', $id)->get();
        $jperguntas = $this->trazerDados($checklisttopicos,"perguntas");
        $jrespostas = $this->trazerDados($jperguntas,"respostas");
        $tipochecklist = $this->tipochecklist;
        $checklist->datainicio = requestDataOracleSemHora($checklist->datainicio);
        $checklist->datafim = requestDataOracleSemHora($checklist->datafim);
        $tiporesposta = $this->tipoResposta();
        $perguntas = json_encode($jperguntas);
        $respostas = json_encode($jrespostas);
        $edit = 1;
        $inativar = 1;
        if(!is_null($pesquisa)){
            return view('checklist.cadastrochecklist_form')->with(compact('checklist', 'tipochecklist', 'tiporesposta',
                'checklisttopicos', 'perguntas', 'respostas', 'edit','inativar'));
        }else{
            return view('checklist.cadastrochecklist_form')->with(compact('checklist', 'tipochecklist', 'tiporesposta',
                'checklisttopicos', 'perguntas', 'respostas', 'edit'));
        }
    }

    function update(Request $request, $id, Checklistform $foo) {
        $this->authorize('update',$foo);
        $checklist = Checklistform::find($id);
        $this->authorize('igualdade',$checklist);
        $this->definitions();
        $data = $request->all();
        $inativar = $data["inativar"] == "1";
        if($inativar){
            $ativo = isset($data["ativo"]);
            $edicao = $this->inativarCadastro($checklist,$ativo);
        }else{
            $edicao = $this->editarCadastro($checklist,$data);
        }
        if($edicao){
            return Redirect::to('cadastrochecklist')->withMessageSuccess('Formulario de Checklist atualizado com sucesso!');
        }else{
            return Redirect::to('cadastrochecklist/'.$id.'/edit')
                                ->withErrors($this->error)
                                ->withInput();
        }
    }

    function show($id, Checklistform $foo) {
        $this->authorize('view', $foo);
        $checklist = Checklistform::find($id);
        $this->authorize('igualdade',$checklist);
        $this->definitions();
        $checklisttopicos = Checklist::where('checklistform_id', $id)->get();
        $jperguntas = $this->trazerDados($checklisttopicos,"perguntas");
        $jrespostas = $this->trazerDados($jperguntas,"respostas");
        $tipochecklist = $this->tipochecklist;
        $checklist->datainicio = requestDataOracleSemHora($checklist->datainicio);
        $checklist->datafim = requestDataOracleSemHora($checklist->datafim);
        $tiporesposta = $this->tipoResposta();
        $perguntas = json_encode($jperguntas);
        $respostas = json_encode($jrespostas);
        $show = 1;

        return view('checklist.cadastrochecklist_form')->with(compact('checklist', 'tipochecklist', 'tiporesposta',
                'checklisttopicos', 'perguntas', 'respostas', 'show'));
    }

    function destroy($id, Checklistform $foo) {
        $this->authorize('delete',$foo);
        $checklist = Checklistform::find($id);
        $this->authorize('igualdade',$checklist);
        DB::beginTransaction();
        try {
            $checklist->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    private function tipoResposta() {
        return [
            "" => "Selecione",
            "0" => "Checkbox",
            "1" => "Número",
            "2" => "Texto",
            "3" => "Data",
            "4" => "Radio Box"
        ];
    }

    private function dadosExtras($data) {
        $data["grupo_id"] = $this->grupo_id;
        $data["empresa_id"] = $this->empresa_id;
        $data["datainicio"] = insertDataOracle($data["datainicio"]);
        $data["datafim"] = insertDataOracle($data["datafim"]);
        $data["ativo"] = isset($data["ativo"]) ? $data["ativo"] : 0;
        return $data;
    }

    private function createTopicos($topicos, $checklistform) {
        DB::beginTransaction();
        try{
            $topicosgeral = [];
            foreach($topicos as $topico){
                $objtopico = new Checklist();
                $objtopico->empresa_id = $checklistform->empresa_id;
                $objtopico->grupo_id = $checklistform->grupo_id;
                $objtopico->checklistform_id = $checklistform->id;
                $objtopico->descricao = $topico->topico;
                $objtopico->ativo = $checklistform->ativo;
                $objtopico->save();
                $objtopico->idtopico = $topico->id;
                array_push($topicosgeral, $objtopico);
            }
        }catch(Exception $ex){
            DB::rollback();
            return false;
        }
        DB::commit();
        return $topicosgeral;
    }

    private function createPerguntas($perguntas, $topicosid) {
        $questions = [];
        foreach($perguntas as $pergunta){
            $perguntasesp = json_decode($pergunta);
            foreach($perguntasesp as $question){
                array_push($questions,$question);
            }
        }
        DB::beginTransaction();
        try{
            $questoes = [];
            foreach($topicosid as $topico){
                foreach($questions as $pergunta){
                    if($topico->idtopico == $pergunta->idtopico){
                        $objpergunta = new Checklistpergunta();
                        $objpergunta->checklist_id = $topico->id;
                        $objpergunta->descricao = $pergunta->pergunta;
                        $objpergunta->tipopergunta = 0;
                        $objpergunta->save();
                        $objpergunta->idpergunta = $pergunta->id;
                        array_push($questoes,$objpergunta);
                    }
                }
            }
        }catch(Exception $ex){
            DB::rollback();
            return false;
        }
        DB::commit();
        return $questoes;
    }

    private function createRespostas($respostas, $perguntas) {
        $answer = [];
        foreach($respostas as $resposta){
            $resp = json_decode($resposta);
            if(count($resp)>0){
                foreach($resp as $r){
                    array_push($answer,$r);
                }
            }
        }
        DB::beginTransaction();
        try{
            foreach($perguntas as $pergunta){
                foreach($answer as $resposta){
                    if($pergunta->idpergunta == $resposta->idpergunta){
                        $objresposta = new Checklistresposta();
                        $objresposta->checklistpergunta_id = $pergunta->id;
                        $objresposta->descricao = $resposta->resposta;
                        $objresposta->tipopergunta = $resposta->tipo;
                        $objresposta->resposta = $resposta->resposta;
                        $objresposta->alerta = $resposta->alerta;
                        $objresposta->save();
                    }
                }
            }
        }catch(Exception $ex){
            DB::rollback();
            return false;
        }
        DB::commit();
        return true;
    }

    private function trazerDados($array, $tipo){
        $dados = [];
        if($tipo == "perguntas"){
            foreach($array as $topico){
                $questions = $topico->checklistpergunta;
                foreach($questions as $pergunta){
                    array_push($dados,$pergunta);
                }
            }
        }else{
            foreach($array as $pergunta){
                $respostas = $pergunta->checklistresposta;
                foreach($respostas as $resposta){
                    $resposta->tiporesposta = $this->tipoResposta()[$resposta->tipopergunta];
                    array_push($dados,$resposta);
                }
            }
        }
        return $dados;
    }

    private function editarCadastro($checklist,$data)
    {
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data, $dadosextras);
        $topicos = json_decode($data["topicos_hd"]);
        $perguntas = $data["perguntas"];
        $respostas = $data["respostas"];
        DB::begintransaction();
        try{
            $checklist->checklist()->delete();
            $checklist->update($data);
            $topicosid = $this->createTopicos($topicos, $checklist);
            if ($topicosid != false) {
                $perguntasid = $this->createPerguntas($perguntas, $topicosid);
            }
            if($perguntasid != false){
                $answer = $this->createRespostas($respostas, $perguntasid);
            }
        } catch (Exception $ex) {
            DB::rollback();
            $this->error = $ex->getMessage();
            return false;
        }
        DB::commit();
        return true;
    }

    private function inativarCadastro($checklist,$ativo)
    {
        DB::beginTransaction();
        try{
            $checklist->update(['ativo'=>$ativo]);
        }catch(Exception $ex){
            DB::rollback();
            $this->error = $ex->getMessage();
            return false;
        }
        DB::commit();
        return true;
    }

    private function definitions(){
        //busca id empresa padrao
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca id do grupo
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //busca checklists
        $this->checklists = checklistform::where('empresa_id', $this->empresa_id)->get();
        //busca tipos de checklists
        $this->tipochecklist = Checklisttipo::where('ativo', 1)->pluck('descricao', 'id')->prepend('Selecione', '');
    }

    public function verificarPeriodo() {
        $get = $_GET;
        $datestart = Carbon::parse($get["datainicio"])->startOfDay();
        $dateend = Carbon::parse($get["datafim"])->endOfDay();
        $tipo = $get["tipo"];
        $id = $get["id"];
        $check = Checklistform::where(["checklisttipo_id"=>$tipo,"ativo"=>1,"empresa_id"=>Session::get("empresa_padrao")->id])->get();
        if(count($check) > 0){
            foreach ($check as $list) {
                if($list->datainicio <= $datestart && $datestart <= $list->datafim && $list->id != $id){
                    return "true";
                }else if ($list->datainicio <= $dateend && $dateend <= $list->datafim && $list->id != $id){
                    return "true";
                }else if ($dateend <= $list->datainicio && $list->datainicio <= $dateend && $list->id != $id){
                    return "true";
                }else if ($list->datainicio >= $datestart && $dateend >= $list->datafim && $list->id != $id) {
                    return "true";
                }else if ($list->datainicio <= $dateend && $dateend <= $list->datafim && $list->id != $id){
                    return "true";
                }
            }
        }else{
            return "false";
        }
    }
}
