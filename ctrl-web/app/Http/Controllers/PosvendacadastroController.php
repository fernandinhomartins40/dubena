<?php

namespace App\Http\Controllers;

use DB;
use Redirect;
use Exception;
use App\Posvenda;
use App\Services\CarbonCustom as Carbon;
use App\Posvendaresposta;
use App\Posvendapesquisa;
use App\Posvendapergunta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class PosvendacadastroController extends Controller{
    
    private $empresa_id;
    private $grupo_id;
    private $posvendas;

    function index(Posvenda $pos){
        $this->authorize('view',$pos);
        $this->definition();
        $posvendas = $this->posvendas;
        return view('posvenda.posvendacadastro')->with(compact('posvendas'));
    }

    function create(Posvenda $pos){
        $this->authorize('create',$pos);
        $this->definition();
        return view('posvenda.posvendacadastro_form');
    }

    function store(Request $request, Posvenda $pos){
        $this->authorize('create',$pos);
        $this->definition();
        $data = $request->all();
        $dadosextras = $this->dadosExtras($data);
        $data = array_merge($data,$dadosextras);
        $dataperguntas = json_decode($data["perguntas_hd"]);
        $datarespostas = $data["respostas"];
        DB::beginTransaction();
        try{
            $posvenda = Posvenda::create($data);
            $perguntas = $this->criarPerguntas($posvenda,$dataperguntas);
            $respostas = $this->criarRespostas($perguntas,$datarespostas);
        }catch(Exception $ex){
            DB::rollback();
            return Redirect::to('posvendacadastro/create')
                            ->withErrors($ex->getMessage())
                            ->withInput();                            
        }
        DB::commit();
        
        return Redirect::to('posvendacadastro')->withMessageSuccess('Pós-venda cadastrada com sucesso!');
    }

    function show($id, Posvenda $pos){
        $this->authorize('view',$pos);
        $posvenda = Posvenda::find($id);
        $this->authorize('igualdade',$posvenda);
        $posvenda->datahorainicio = requestDataOracle($posvenda->datahorainicio);
        $posvenda->datahorafim = requestDataOracle($posvenda->datahorafim);
        $perguntas = $posvenda->posvendaperguntas;
        $respostasgeral = [];
        foreach($perguntas as $pergunta){
            $respostas = $pergunta->posvendarespostas;
            foreach($respostas as $resposta){
                array_push($respostasgeral,$resposta);
            }
        }
        $respostas = json_encode($respostasgeral);
        $show = 1;

        return view('posvenda.posvendacadastro_form')->with(compact('posvenda','perguntas','respostas','show'));
    }

    function edit($id, Posvenda $pos){
        $this->authorize('update',$pos);
        $posvenda = Posvenda::find($id);
        $this->authorize('igualdade',$posvenda);
        $pesquisa = Posvendapesquisa::where('posvenda_id',$id)->get();
        $posvenda->datahorainicio = requestDataOracle($posvenda->datahorainicio);
        $posvenda->datahorafim = requestDataOracle($posvenda->datahorafim);
        $perguntas = $posvenda->posvendaperguntas;
        $respostasgeral = [];
        foreach($perguntas as $pergunta){
            $respostas = $pergunta->posvendarespostas;
            foreach($respostas as $resposta){
                array_push($respostasgeral,$resposta);
            }
        }
        $respostas = json_encode($respostasgeral);
        $edit = 1;
        $inativar = 1;
        if(count($pesquisa) <= 0){
            return view('posvenda.posvendacadastro_form')->with(compact('posvenda','perguntas','respostas','edit'));
        }else{
            return view('posvenda.posvendacadastro_form')->with(compact('posvenda','perguntas','respostas','edit','inativar'));
        }
    }

    function update(Request $request, $id, Posvenda $pos){
        $this->authorize('update',$pos);
        $posvenda = Posvenda::find($id);
        $this->authorize('igualdade',$posvenda);
        $this->definition();
        $inativar = false;
        $data = $request->all();
        if($data["inativar"] == 1){
            $inativar = true;
            $data["ativo"] = isset($data["ativo"]) ? 1 : 0;
        }else{
            $dadosextras = $this->dadosExtras($data);
            $data = array_merge($data,$dadosextras);
            $dataperguntas = json_decode($data["perguntas_hd"]);
            $datarespostas = $data["respostas"];
        }
        DB::beginTransaction();
        try{
            if(!$inativar){
                $posvenda->posvendaperguntas()->delete();
                $posvenda->update($data);
                $perguntas = $this->criarPerguntas($posvenda,$dataperguntas);
                $repostas = $this->criarRespostas($perguntas,$datarespostas);
            }else{
                $posvenda->update(['ativo'=>$data["ativo"]]);
            }
        }catch(Exception $ex){
            DB::rollback();
            return Redirect::to('posvendacadastro/create')
                            ->withErrors($ex->getMessage())
                            ->withInput();  
        }
        DB::commit();
        
        return Redirect::to('posvendacadastro')->withMessageSuccess('Pós-venda atualizada com sucesso!');
    }

    function destroy($id, Posvenda $pos){
        $this->authorize('delete',$pos);
        $posvenda = Posvenda::find($id);
        $this->authorize('igualdade',$posvenda);
        $pesquisa = Posvendapesquisa::where('posvenda_id',$id)->get();
        DB::beginTransaction();
        try {
            if(count($pesquisa) > 0){
                return '<br /><br />Existe uma ou mais pesquisas vinculadas a este formulário, ele não pode ser excluido.';
            }else{
                $posvenda->delete();
            }
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    private function dadosExtras($data){
        $data["empresa_id"] = $this->empresa_id;
        $data["grupo_id"] = $this->grupo_id;
        $data["datahorainicio"] = insertDataOracle($data["datahorainicio"]);
        $data["datahorafim"] = insertDataOracle($data["datahorafim"]);
        $data["ativo"] = isset($data["ativo"]) ? $data["ativo"] : 0;
        return $data;
    }

    private function criarPerguntas($posvenda,$perguntas){
        DB::beginTransaction();
        try{
            $posvendaperguntas = [];
            foreach($perguntas as $pergunta){
                $posvendapergunta = new Posvendapergunta();
                $posvendapergunta->posvenda_id = $posvenda->id;
                $posvendapergunta->descricao = $pergunta->pergunta;
                $pospergunta = $posvendapergunta->save();
                $posvendapergunta->idpergunta = $pergunta->id;
                array_push($posvendaperguntas,$posvendapergunta);
            }
        }catch(Exception $ex){
            DB::rollback();
            return false;
        }
        DB::commit();
        return $posvendaperguntas;
    }

    private function criarRespostas($perguntas,$datarespostas){
        $answer = [];
        foreach($datarespostas as $res){
            $respostas = json_decode($res);
            foreach($respostas as $resposta){
                $objresposta = (object) array();
                foreach($perguntas as $pergunta){
                    if($resposta->idpergunta == $pergunta->idpergunta){
                        $objresposta->pergunta_id = $pergunta->id;
                        $objresposta->resposta = $resposta->resposta;
                        array_push($answer,$objresposta);
                    }
                }
            }
        }
        DB::beginTransaction();
        try{
            foreach($answer as $resposta){
                $posresposta = new Posvendaresposta();
                $posresposta->posvendapergunta_id = $resposta->pergunta_id;
                $posresposta->descricao = $resposta->resposta;
                $posres = $posresposta->save();
            }
        }catch(Exception $ex){
            DB::rollback();
            return false;
        }
        DB::commit();
        return $posres;
    }

    private function definition(){
        //busca id da empresa logada
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca id do grupo da empresa logada
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //busca todos os cadastros de pós venda
        $this->posvendas = Posvenda::where(['empresa_id'=>$this->empresa_id])->get();
    }

    //ajax
    public function verificarPeriodo(){
        $get = $_GET;
        $datast = str_replace("_"," ",$get["datainicio"]);
        $dataen = str_replace("_"," ",$get["datafim"]);
        $datainicio = Carbon::parse($datast)->startOfDay();
        $datafim = Carbon::parse($dataen)->endOfDay();
        $empresa_id = Session::get('empresa_padrao')->id;
        $posvenda = Posvenda::where([
                            ['empresa_id',$empresa_id],
                            ['ativo',1]
                        ])
                    ->get();
        if(count($posvenda)>0){
            foreach($posvenda as $pos){
                if($pos->datahorainicio <= $datainicio && $datainicio <= $pos->datahorafim){
                    return "true";
                }else if($pos->datahorainicio <= $datafim && $datafim <= $pos->datahorafim){
                    return "true";
                }else if($datafim <= $pos->datahorainicio && $pos->datahorainicio <= $datafim){
                    return "true";
                }else if($pos->datahorainicio <= $datafim && $datafim <= $pos->datahorafim){
                    return "true";
                }else if($pos->datahorainicio >= $datainicio && $datafim >= $pos->datahorafim){
                    return "true";
                }
            }
        }else{
            return "false";
        }
        return "false";
    }

}
