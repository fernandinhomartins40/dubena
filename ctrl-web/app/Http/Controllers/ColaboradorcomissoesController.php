<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Setor;
use App\Produto;
use App\Segmento;
use App\Colaborador;
use App\Http\Requests;
use App\Comissaoexcecoes;
use App\Condicaopagamento;
use Illuminate\Support\Arr;
use App\Colaboradorcomissao;
use Illuminate\Http\Request;
use App\Services\CarbonCustom as Carbon;
use App\Http\Requests\ColaboradorComissaoRequest;

class ColaboradorcomissoesController extends Controller
{

    public function index(Colaboradorcomissao $com)
    {
        $this->authorize('view',$com);
        $setores = [];
        $setor = '';
        $colaboradores = [];
        $colaborador = '';
        if (isset($_GET['setor_id']) && $_GET['setor_id'] !== '') {
            $setor = $_GET['setor_id'];
            if (isset($_GET['colaborador_id']) && $_GET['colaborador_id'] !== '' && $_GET['colaborador_id'] !== 'null') {
                $colaboradores = new ColaboradorController();
                $colaborador = $_GET['colaborador_id'];
                $colaboradores->buscaPorSetor($_GET['setor_id']);
                if($setor != -1){
                    $colaboradorcomissoes = Colaboradorcomissao::where([
                        ['empresa_id', Session::get('empresa_padrao')->id],
                        ['setor_id', $setor],
                        ['colaborador_id', $colaborador]
                    ])->get();
                } else {
                    $colaboradorcomissoes = Colaboradorcomissao::where([
                        ['empresa_id', Session::get('empresa_padrao')->id],
                        ['colaborador_id', $colaborador]
                    ])->get();
                }
            } else {
                if($setor != -1){
                    $colaboradorcomissoes = Colaboradorcomissao::where([
                        ['empresa_id', Session::get('empresa_padrao')->id],
                        ['setor_id', $setor]
                    ])->get();
                } else {
                    $colaboradorcomissoes = Colaboradorcomissao::where([
                        ['empresa_id', Session::get('empresa_padrao')->id],
                    ])->get();
                }
            }
        } else {
            $colaboradorcomissoes = Colaboradorcomissao::where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('id', 'desc')->get();
        }
        $setores = Setor::where([
            ['ativo', 1],
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '-1');

        return View('colaboradors.comissoes.comissoes', compact('colaboradorcomissoes', 'setores', 'setor', 'colaboradores', 'colaborador'));
    }

    public function create(Colaboradorcomissao $com)
    {
        $this->authorize('create',$com);
        $setores = Setor::where([
            ['ativo', 1],
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->pluck('descricao', 'id')->prepend('-', -1);
        $setor = '';
        $colaboradores = [];
        $colaborador = '';
        $produtos = Produto::where([
            ['ativo', 1],
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->pluck('descricao', 'id');
        $produto = '';
        $condicaopagamentos = Condicaopagamento::where([
            ['ativo', 1],
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->pluck('descricao', 'id');
        $condicaopagamento = '';
        $segmentos = Segmento::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->pluck('descricao', 'id');
        return View('colaboradors.comissoes.comissoes_form', compact('setores', 'segmentos', 'setor', 'colaboradores', 'colaborador', 'produtos', 'produto', 'condicaopagamentos', 'condicaopagamento'));
    }

    public function store(ColaboradorComissaoRequest $request, Colaboradorcomissao $com)
    {
        $this->authorize('create',$com);

        $data = $request->all();
        if(isset($data['redirect']) && !empty($data['redirect'])) {
            $redirect = explode('public/', $data['redirect']);
        }
        $hasRedirect = isset($redirect[1]);
        DB::beginTransaction();
        try {
            $data = $this->dadosExtras($data);
            $valida = $this->validacoesExtras($data);

            if (empty($data['empresavalor'])) {
                $data['empresavalor'] = 0;
            }

            if (!$data['tonelagem'] && is_array($data["condicaopagamento_id"]) && ! isset($data["replicar"])) {
                $count = count($data["condicaopagamento_id"]);
                $multi = $this->insertMultiples($data);
            } else if (! isset($data["replicar"])) {
                $com = $this->makeComissao($data, Session::get('empresa_padrao')->id, $data["condicaopagamento_id"]);
                $verificarPeridoComissoes = $this->verificarExistencia($com);
                if ($verificarPeridoComissoes) {
                    throw new \Exception("Já existe uma comissão ativa cadastrada nesse período para esse colaborador neste setor.");
                }
                $colaboradorcomissao = Colaboradorcomissao::create($data);
                if ($data["excecoes"]) {
                    $this->insertUpdateExcessoes($data["excecoes"], $colaboradorcomissao);
                }
            } else {
                $multi = $this->replicarCadastro($data);
            }
        } catch (\Exception $e) {
            DB::rollback();
            if ($hasRedirect) {
                return \Redirect::to('colaboradorcomissoes/create?'.$redirect[0].$redirect[1])->withErrors("Erro: " . $e->getMessage())->withInput();
            }
            return \Redirect::to('colaboradorcomissoes/create')->withErrors("Erro: " . $e->getMessage())->withInput();
        }
        DB::commit();
        $msg = 'Comissão cadastrada com sucesso!';
        if (isset($multi) && ! is_bool($multi)) {
            $msg  = implode("<br />", $multi);
            $msg .= "<br />As demais foram cadastradas com sucesso.";
        }
        if ($hasRedirect) {
            return \Redirect::to($redirect[1])->withMessageSuccess($msg);
        }

        return \Redirect::to('colaboradorcomissoes')->withMessageSuccess($msg);
    }

    public function show($id, Colaboradorcomissao $com)
    {
        $this->authorize('view',$com);
        $colaboradorcomissao = Colaboradorcomissao::findOrFail($id);
        $this->authorize('igualdade',$colaboradorcomissao);
        $setores = Setor::where([
            ['ativo', 1],
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->pluck('descricao', 'id')->prepend('-', -1);
        $setor = $colaboradorcomissao->setor_id;
        $colaboradores = [];
        $colaborador = $colaboradorcomissao->colaborador_id;
        $produtos = Produto::where([
            ['ativo', 1],
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->pluck('descricao', 'id');
        $produto = $colaboradorcomissao->produto_id;
        $condicaopagamentos = Condicaopagamento::where([
            ['ativo', 1],
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->pluck('descricao', 'id');
        $condicaopagamento = $colaboradorcomissao->condicaopagamento_id;
        $colaboradorcomissao->datainicio = requestDataOracle($colaboradorcomissao->datainicio);
        $colaboradorcomissao->datafim = requestDataOracle($colaboradorcomissao->datafim);
        $show = true;
        $segmentos = Segmento::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->pluck('descricao', 'id');
        $excecoes = Comissaoexcecoes::where('colaboradorcomissao_id', $colaboradorcomissao->id)->get();
        return View('colaboradors.comissoes.comissoes_form', compact('colaboradorcomissao', 'segmentos', 'show', 'setores', 'setor', 'colaboradores', 'colaborador', 'produtos', 'produto', 'condicaopagamentos', 'condicaopagamento', 'excecoes'));
    }

    public function edit($id, Colaboradorcomissao $com)
    {
        $this->authorize('update',$com);
        $colaboradorcomissao = Colaboradorcomissao::findOrFail($id);
        $this->authorize('igualdade',$colaboradorcomissao);
        $setores = Setor::where([
            ['ativo', 1],
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->pluck('descricao', 'id')->prepend('-', -1);
        $setor = $colaboradorcomissao->setor_id;
        $colaboradores = [];
        $colaborador = $colaboradorcomissao->colaborador_id;
        $produtos = Produto::where([
            ['ativo', 1],
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->pluck('descricao', 'id');
        $produto = $colaboradorcomissao->produto_id;
        $condicaopagamentos = Condicaopagamento::where([
            ['ativo', 1],
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
            ])->pluck('descricao', 'id');
        $condicaopagamento = $colaboradorcomissao->condicaopagamento_id;
        $colaboradorcomissao->datainicio = requestDataOracle($colaboradorcomissao->datainicio);
        $colaboradorcomissao->datafim = requestDataOracle($colaboradorcomissao->datafim);
        $segmentos = Segmento::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->pluck('descricao', 'id');
        $excecoes = Comissaoexcecoes::where('colaboradorcomissao_id', $colaboradorcomissao->id)->get();
        return View('colaboradors.comissoes.comissoes_form', compact('colaboradorcomissao', 'segmentos', 'setores', 'setor', 'colaboradores', 'colaborador', 'produtos', 'produto', 'condicaopagamentos', 'condicaopagamento', 'excecoes'));
    }

    public function update(ColaboradorComissaoRequest $request, $id, Colaboradorcomissao $com)
    {
        $this->authorize('update',$com);
        $colaboradorcomissao = Colaboradorcomissao::findOrFail($id);
        $this->authorize('igualdade',$colaboradorcomissao);
        DB::beginTransaction();
        try {
            $data = $request->all();
            $data = $this->dadosExtras($data);
            if ($data['datainicio'] > $data['datafim']) {
                DB::rollback();
                throw new \Exception("A data de início não pode ser maior que a data do fim.");
            }

            if($data['ativo']) {
                $com = $this->makeComissao($data, Session::get('empresa_padrao')->id, $data["condicaopagamento_id"]);
                $verificarPeridoComissoes = $this->verificarExistencia($com, $id);
                if ($verificarPeridoComissoes) 
                    throw new \Exception("Já existe uma comissão ativa cadastrada nesse período para esse colaborador neste setor.");
            }
            $colaboradorcomissao->update($data);
            $colaboradorcomissao->comissaoexcecoes()->delete();
            if ($data['excecoes'])
                $this->insertUpdateExcessoes($data['excecoes'], $colaboradorcomissao);
        } catch (\Exception $e) {
            DB::rollback();
            $error = getErrorsException($e);
            return \Redirect::to('colaboradorcomissoes/' . $id . '/edit')
                ->withErrors($error)
                ->withInput();
        }
        DB::commit();
        return \Redirect::to('colaboradorcomissoes')->withMessageSuccess('Comissão atualizada com sucesso!');
    }

    /**
     * Process the data with the db standard
     * 
     * @param array  $data
     * @return array $data
     */
    private function dadosExtras($data)
    {
        $data['percentual'] = isset($data['percentual']) ? insertPercentualOracle($data['percentual']) : 0;
        $data['empresavalor'] = isset($data['empresavalor']) ? insertNumeroDecimalOracle($data['empresavalor']) : 0;
        $data['percentualapp'] = isset($data['percentualapp']) ? insertPercentualOracle($data['percentualapp']) : 0;
        $data['empresavalorapp'] = isset($data['empresavalorapp']) ? insertNumeroDecimalOracle($data['empresavalorapp']) : 0;
        $data['empresa_id'] = Session::get('empresa_padrao')->id;
        $data['datainicio'] = insertDataOracle($data['datainicio']);
        $data['datafim'] = insertDataOracle($data['datafim']);
        $data['ativo'] = isset($data['ativo']) && $data['ativo'];
        $data['tonelagem'] = isset($data['tonelagem']) && $data['tonelagem'];
        if ($data['percentual'] === '') {
            $data['percentual'] = 0;
        }
        if ($data['percentualapp'] === '') {
            $data['percentualapp'] = 0;
        }
        if(!$data['tonelagem']){
            if (is_array($data["condicaopagamento_id"]) && count($data["condicaopagamento_id"]) == 1) {
                $data["condicaopagamento_id"] = Arr::first($data["condicaopagamento_id"]);
            }
        } else {
            $data["condicaopagamento_id"] = null;
            $data["setor_id"] = null;
        }

        return $data;
    }

    private function insertUpdateExcessoes($excecoes, $colaboradorcomissao, $multiple = false)
    {
        $data = [];
        if ($multiple) {
            $data = $colaboradorcomissao;
        } else {
            $data[] = $colaboradorcomissao->id;
        }
        $excecoes = json_decode($excecoes);
        $excecao = new Comissaoexcecoes();
        $dados = collect([]);
        foreach ($data as $id) {
            $except = $this->makeExcecoes($excecoes, $id);
            $dados = $dados->merge($except);
        }
        $del = Comissaoexcecoes::whereIn('id', $data)->delete();
        $create = $excecao->insert($dados->toArray());

        return $create;
    }

    private function makeExcecoes($excecoes, $id)
    {
        $excep = collect([]);
        foreach ($excecoes as $ex) {
            $excecao = new Comissaoexcecoes();
            $excecao->segmento_id = $ex[0];
            $excecao->colaboradorcomissao_id = $id;
            $excecao->tipoexcecao = $ex[2] == 'Percentual' ? '1' : '2';
            $excecao->valorexcecao = $ex[2] == 'Percentual' ? insertPercentualOracle($ex[3]) : insertNumeroDecimalOracle($ex[3]);
            $excecao->valorexcecaoapp = $ex[2] == 'Percentual' ? insertPercentualOracle($ex[4]) : insertNumeroDecimalOracle($ex[4]);
            $excep->push($excecao);
        }
        return $excep;
    }

    /**
     * Insert multiple rows on the colaboradorcomissaos table
     * 
     * @return array $data
     * @return boolean
     */
    private function insertMultiples($data, $returnCol = false)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $condicoes = $data["condicaopagamento_id"];
        $comissao = new Colaboradorcomissao();
        $comissoes = collect([]);
        $hasExcecoes = !$data["excecoes"];
        $ids = [];
        $existence = [];
        foreach ($condicoes as $cond) {
            $com = $this->makeComissao($data, $empresa_id, $cond);
            $exists = $this->verificarExistencia($com);

            if (! $hasExcecoes && ! $exists) {
                $created = $com->save();
                array_push($ids, $com->id);
            } else if (! $exists) {
                $com->created_at = Carbon::now();
                $com->updated_at = Carbon::now();
                $comissoes->push($com);
            } else {
                $message = $this->getMsg($com);
                $existence[] = $message;
            }
        }

        if (! $hasExcecoes && ! $returnCol) {
            $this->insertUpdateExcessoes($data["excecoes"], $ids, true);
        } else if (! $returnCol && $comissoes->isNotEmpty()) {
            $comissao->insert($comissoes->toArray());
        }

        if (count($existence) > 0 && ! $returnCol) 
            return $existence;

        if (! $returnCol) {
            return true;
        } else {
            $obj = (object) array();
            $obj->existence = $existence;
            $obj->comissoes = $comissoes;
            $obj->excecoes = $ids;
            return $obj;
        }
    }

    /**
     * Make an instance of Colaboradorcomissao model
     * 
     * @param array $data
     * @param int $empresa_id
     * @param int $cond condicao de pagamento
     * @return \App\Colaboradorcomissao $comissao
     */
    private function makeComissao($data, $empresa_id, $cond)
    {
        $com = new Colaboradorcomissao();
        $com->empresa_id = $empresa_id;
        $com->tipocomissao = $data["tipocomissao"];
        $com->colaborador_id = $data["hiddencolaborador_id"];
        $com->condicaopagamento_id = $cond;
        $com->produto_id = $data["produto_id"];
        $com->setor_id = $data["setor_id"];
        $com->percentual = $data["percentual"];
        $com->empresavalor = $data["empresavalor"];
        $com->datainicio = $data["datainicio"];
        $com->datafim = $data["datafim"];
        $com->ativo = $data["ativo"];
        $com->tonelagem = $data["tonelagem"];
        $com->percentualapp = $data["percentualapp"];
        $com->empresavalorapp = $data["empresavalorapp"];

        return $com;
    }

    /**
     * Check if exists another row with the same colaborador_id, setor_id, produto_id and condicaopagamento_id
     * 
     * @param \App\Colaboradorcomissao $comissao
     * @param int $id
     * @return boolean
     */
    private function verificarExistencia($comissao, $id = null) : bool
    {
        $query = "select id ".
            "from colaboradorcomissaos ".
            "where empresa_id = $comissao->empresa_id and ativo = 1 and " . 
            ($comissao->tonelagem?"":" setor_id = $comissao->setor_id and ");
        if (! is_null($id)) {
            $query .= "id <> $id and ";
        }
        $query .= 
            ($comissao->tonelagem?"":"condicaopagamento_id = $comissao->condicaopagamento_id and ").
            " produto_id = $comissao->produto_id and ".
            "colaborador_id = $comissao->colaborador_id and ".
            "( ".
                "( datainicio <= to_date('$comissao->datainicio', 'yyyy-mm-dd hh24:mi:ss') and datafim >= to_date('$comissao->datainicio', 'yyyy-mm-dd hh24:mi:ss') ) or ".
                "( datainicio <= to_date('$comissao->datafim', 'yyyy-mm-dd hh24:mi:ss') and datafim >= to_date('$comissao->datafim', 'yyyy-mm-dd hh24:mi:ss') ) or ".
                "( datainicio >= to_date('$comissao->datafim', 'yyyy-mm-dd hh24:mi:ss') and datafim <= to_date('$comissao->datafim', 'yyyy-mm-dd hh24:mi:ss') ) ".
            ")";

        $exist = collect(DB::select($query))->isNotEmpty();
        if ($exist) {
            return true;
        }
        return false;
    }

    private function replicarCadastro($data)
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $setores = $this->getSetorColaboradores();
        $isArray = is_array($data["condicaopagamento_id"]);
        $comissao = new Colaboradorcomissao();
        $comissoes = collect([]);
        $hasExcecoes = !$data["excecoes"];
        $ids = [];
        $existence = [];
        foreach ($setores as $setor) {
            $data["setor_id"] = $setor->setor_id;
            $data["colaborador_id"] = $setor->colaborador_id;
            $data["hiddencolaborador_id"] = $setor->colaborador_id;
            if ($isArray) {
                $com = $this->insertMultiples($data, true);

                $existence = array_merge($existence, $com->existence);
                $ids = array_merge($ids, $com->excecoes);
                $comissoes = $comissoes->merge($com->comissoes);
            } else {
                $cond = $data["condicaopagamento_id"];
                $com = $this->makeComissao($data, $empresa_id, $cond);
                $exists = $this->verificarExistencia($com);
                if (! $hasExcecoes && ! $exists) {
                    $created = $com->save();
                    array_push($ids, $com->id);
                } else if(! $exists) {
                    $com->created_at = Carbon::now();
                    $com->updated_at = Carbon::now();
                    $comissoes->push($com);
                } else {
                    $message = $this->getMsg($com);
                    $existence[] = $message;
                }
            }
        }
        $comissoes = $comissoes->flatten();
        if (! $hasExcecoes) {
            $this->insertUpdateExcessoes($data["excecoes"], $ids, true);
        } else if ($comissoes->isNotEmpty()) {
            $comissao->insert($comissoes->toArray());
        }

        if (count($existence) > 0) {
            return $existence;
        }

        return true;
    }

    /**
     * Method to return a message to the user
     * 
     * @param \App\Colaboradorcomissao $comissao
     * @return string $message
     */
    private function getMsg($comissao) : string
    {
        $query = "select 'Foi encontrado uma comissão cadastrada: Setor: ' || setor ".
        "|| ', Colaborador: ' || nome || ', Produto: ' || produto ".
        "|| ', Condicao de Pagamento: ' || condicao || ' no mesmo período.' as msg ".
        "from ( ".
          "select max(setor) as setor, max(nome) as nome, max(produto) as produto, max(condicao) as condicao ".
          "from ( ".
            "select descricao as setor,  ".
            "cast('' as varchar(1)) as nome, ".
            "cast('' as varchar(1)) as produto, ".
            "cast('' as varchar(1)) as condicao ".
            "from setors ".
            "where id = $comissao->setor_id ".

            "union all ".

            "select cast('' as varchar(1)) as setor, ".
            "nome, ".
            "cast('' as varchar(1)) as produto, ".
            "cast('' as varchar(1)) as condicao ".
            "from colaboradors ".
            "where id = $comissao->colaborador_id ".

            "union all ".

            "select cast('' as varchar(1)) as setor, ".
            "cast('' as varchar(1)) as nome, ".
            "descricao as produto, ".
            "cast('' as varchar(1)) as condicao ".
            "from produtos ".
            "where id = $comissao->produto_id ".

            "union all ".

            "select cast('' as varchar(1)) as setor, ".
            "cast('' as varchar(1)) as nome, ".
            "cast('' as varchar(1)) as produto, ".
            "descricao as condicao ".
            "from condicaopagamentos ".
            "where id = $comissao->condicaopagamento_id ".
          ") condicoes ".
        ") msgs";

        $msg = collect(DB::select($query))->first()->msg;

        return $msg;
    }

    private function getSetorColaboradores()
    {
        return DB::table('setorcolaboradores as sect')
            ->join('setors', 'sect.setor_id', 'setors.id')
            ->where('setors.empresa_id', Session::get('empresa_padrao')->id)
            ->select('sect.setor_id', 'sect.colaborador_id')
            ->orderBy('sect.setor_id')
            ->get();
    }

    private function validacoesExtras($data)
    {
        $validado = true;
        if (empty($data['percentual']) && empty($data['empresavalor'])) {
            $validado = "Pelo menos um dos campos a seguir deve ser preenchido: Comissão ou Valor Repasse";
        } else if (!empty($data['percentual']) && !empty($data['empresavalor'])) {
            $validado = "Somente um dos campos a seguir deve ser preenchido: Comissão ou Valor Repasse.";
        }
        if ($data['datainicio'] > $data['datafim'] && is_bool($validado)) {
            $validado = "A data de início não pode ser maior que a data do fim.";
        }
        if (!is_bool($validado)) {
            throw new \Exception($validado);
        }
        return $validado;
    }

}
