<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Rua;
use Redirect;
use App\Cargo;
use App\Bairro;
use App\Estado;
use App\Cidade;
use App\Tipoexame;
use App\Parentesco;
use App\Tipopessoa;
use App\Estadocivil;
use App\Colaborador;
use App\Telefonetipo;
use App\Colaboradorexame;
use App\Colaboradorferias;
use App\Colaboradorfamilia;
use App\Colaboradortelefone;
use Illuminate\Http\Request;

class ColaboradorController extends Controller {

    public function __construct() {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Colaborador $col) {
        $this->authorize('view',$col);
        $colaboradors = Colaborador::where([
            ['empresa_id', Session::get('empresa_padrao')->id]
        ])
        ->select('id', 'nome as descricao', 'ativo')
        ->get();
        return view('colaboradors.colaboradors', compact('colaboradors'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Colaborador $col) {
        $this->authorize('create',$col);
        $cidades = [];
        $cidadesN = [];
        $estados = Estado::all()->pluck('descricao', 'uf');
        $estados1 = Estado::all()->pluck('uf', 'uf');
        $bairros = [];
        $ruas = [];

        $tipopessoasdados = Tipopessoa::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->get();
        $tipopessoas = ['' => 'Selecione'];
        foreach ($tipopessoasdados as $tipopessoa) {
            $tipopessoas[$tipopessoa->id . $tipopessoa->tipopessoacadastro] = $tipopessoa->descricao;
        }
        $telefonetipos = Telefonetipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $estadocivils = Estadocivil::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $parentescos = Parentesco::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $tiposexames = Tipoexame::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $cargos = Cargo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');

        $cep = Session::get('empresa_padrao')->cep;
        $tipopessoa = '';
        return view('colaboradors.colaborador_form', compact('cidades', 'cidadesN', 'estados', 'estados1', 'bairros', 'tipopessoas', 'telefonetipos', 'estadocivils', 'parentescos', 'tiposexames', 'ruas', 'cargos', 'cep', 'tipopessoa'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Colaborador $col) {
        $this->authorize('create',$col);
        $this->validate($request, [
            'tipopessoa_id' => 'required',
            'nome' => 'required',
            'numero' => 'required',
            'cidade_id' => 'required',
            'bairro_id' => 'required'
            ]);
        $data = $request->only('grupo_id', 'empresa_id', 'tipopessoa_id', 'ativo', 'nome', 'cpf', 'rg', 'sexo', 'ctps', 'estadocivil_id', 'datanascimento', 'dataadmissao', 'datadesligamento', 'cnpj', 'inscricao_estadual', 'cargo_id', 'observacoes', 'cep', 'uf', 'cidade_id', 'bairro_id', 'rua_id', 'numero', 'complemento', 'ponto_referencia', 'email', 'rua_descricao');
        if ($data['rg'] != '') {
            $this->validate($request, [
                'rg' => 'unique:colaboradors,rg,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
                ]);
        }
        if ($data['cpf'] != '') {
            $this->validate($request, [
                'cpf' => 'cpf|unique:colaboradors,cpf,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id
                ]);
        }
        $data['tipopessoa_id'] = str_replace('F', '', $data['tipopessoa_id']);
        $data['tipopessoa_id'] = str_replace('J', '', $data['tipopessoa_id']);
        $data["user_id"] = \Auth::user()->id;
        $data["datanascimento"] = insertDataOracle($data["datanascimento"]);
        $data["dataadmissao"] = insertDataOracle($data["dataadmissao"]);
        $data["datadesligamento"] = insertDataOracle($data["datadesligamento"]);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["rgorgao"] = "X";
        DB::beginTransaction();
        try {

            if (starts_with($data["rua_id"], "N")) $this->createRua($data);

            $colaborador = Colaborador::create($data);

            $fones = [];
            if ($request->only('telefones')["telefones"] != null) {
                $telefones = json_decode($request->only('telefones')["telefones"]);
                foreach ($telefones as $telefone) {
                    $fone = new Colaboradortelefone();
                    $fone["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $fone["empresa_id"] = Session::get('empresa_padrao')->id;
                    $fone["telefonetipo_id"] = $telefone[0];
                    $fone["telefone"] = $telefone[2];
                    array_push($fones, $fone);
                }
            }
            $colaborador->telefones()->delete();
            $colaborador->telefones()->saveMany($fones);

            $colaboradorfamilias = [];
            if ($request->only('colaboradorfamilias')["colaboradorfamilias"] != null) {
                $familias = json_decode($request->only('colaboradorfamilias')["colaboradorfamilias"]);
                foreach ($familias as $familia) {
                    $colaboradorfamilia = new Colaboradorfamilia();
                    $colaboradorfamilia["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $colaboradorfamilia["empresa_id"] = Session::get('empresa_padrao')->id;
                    $colaboradorfamilia["parentesco_id"] = $familia[0];
                    $colaboradorfamilia["nome"] = $familia[2];
                    $colaboradorfamilia["datanascimento"] = insertDataOracle($familia[3]);
                    array_push($colaboradorfamilias, $colaboradorfamilia);
                }
            }
            $colaborador->colaboradorfamilias()->delete();
            $colaborador->colaboradorfamilias()->saveMany($colaboradorfamilias);

            $feriascolaborador = [];
            if ($request->only('colaboradorferias')["colaboradorferias"] != null) {
                $colaboradorferiasrequest = json_decode($request->only('colaboradorferias')["colaboradorferias"]);
                foreach ($colaboradorferiasrequest as $ferias) {
                    $colaboradorferias = new Colaboradorferias();
                    $colaboradorferias["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $colaboradorferias["empresa_id"] = Session::get('empresa_padrao')->id;
                    $colaboradorferias["datainicio"] = insertDataOracle($ferias[0]);
                    $colaboradorferias["dias"] = $ferias[1];
                    $colaboradorferias["gozada"] = $ferias[2] === 'Sim' ? true : false;
                    array_push($feriascolaborador, $colaboradorferias);
                }
            }
            $colaborador->colaboradorferias()->delete();
            $colaborador->colaboradorferias()->saveMany($feriascolaborador);

            $colaboradorexames = [];
            if ($request->only('colaboradorexames')["colaboradorexames"] != null) {
                $colaboradorexamesrequest = json_decode($request->only('colaboradorexames')["colaboradorexames"]);
                foreach ($colaboradorexamesrequest as $exame) {
                    $colaboradorexame = new Colaboradorexame();
                    $colaboradorexame["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $colaboradorexame["empresa_id"] = Session::get('empresa_padrao')->id;
                    $colaboradorexame["tipoexame_id"] = $exame[0];
                    $colaboradorexame["data"] = insertDataOracle($exame[2]);
                    $colaboradorexame["datavencimento"] = insertDataOracle($exame[3]);
                    $colaboradorexame["alerta"] = $exame[4] === 'Sim' ? true : false;
                    array_push($colaboradorexames, $colaboradorexame);
                }
            }
            $colaborador->colaboradorexames()->delete();
            $colaborador->colaboradorexames()->saveMany($colaboradorexames);
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/colaborador/create')
                ->withErrors($e->getMessage())
                ->withInput();
        }
        DB::commit();
        return \Redirect::route('colaborador.index')->withMessageSuccess('Colaborador cadastrado com sucesso!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Colaborador $col) {
        $this->authorize('view',$col);
        $colaborador = Colaborador::find($id);
        $this->authorize('igualdade',$colaborador);
        $estados = Estado::all()->pluck('descricao', 'uf');
        $cidade = Cidade::find($colaborador->{'cidade_id'});
        $cidades = Cidade::where([['grupo_id', Session::get('empresa_padrao')->grupo_id],['uf', $cidade->{'uf'}]])
        ->orWhere([['grupo_id', null],['uf', $cidade->{'uf'}]])
        ->orderby('descricao')->pluck('descricao', 'id');

        $cidades1 = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orWhere('grupo_id', null)->orderby('descricao')->pluck('descricao', 'id');
        $bairros = Bairro::where([['cidade_id', $cidade->id], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao', 'id');
        $ruas = Rua::where([['cidade_id', $colaborador->{'cidade_id'}], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao', 'id');
        $cargos = Cargo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');

        $colaborador["datanascimento"] = requestDataOracle($colaborador->datanascimento);
        $colaborador["dataadmissao"] = requestDataOracle($colaborador->dataadmissao);
        $colaborador["datadesligamento"] = requestDataOracle($colaborador->datadesligamento);
        $cidadesN = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('uf', $colaborador->cidadenatal_uf)->orderby('descricao')->pluck('descricao', 'id');
        $tipopessoasdados = Tipopessoa::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->get();
        $tipopessoas = ['' => 'Selecione'];
        foreach ($tipopessoasdados as $tipopessoa) {
            $tipopessoas[$tipopessoa->id . $tipopessoa->tipopessoacadastro] = $tipopessoa->descricao;
        }
        $telefonetipos = Telefonetipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $estadocivils = Estadocivil::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $parentescos = Parentesco::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $tiposexames = Tipoexame::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');

        foreach ($colaborador->colaboradorfamilias as $colaboradorfamilia) {
            $colaboradorfamilia["datanascimento"] = requestDataOracle($colaboradorfamilia->datanascimento);
        }

        foreach ($colaborador->colaboradorferias as $colaboradorferias) {
            $colaboradorferias["datainicio"] = requestDataOracle($colaboradorferias->datainicio);
        }

        foreach ($colaborador->colaboradorexames as $colaboradorexame) {
            $colaboradorexame["data"] = requestDataOracle($colaboradorexame->data);
            $colaboradorexame["datavencimento"] = requestDataOracle($colaboradorexame->datavencimento);
        }

        $tipopessoaColaborador = Tipopessoa::find($colaborador->tipopessoa_id);
        $tipopessoa = $tipopessoaColaborador->id . $tipopessoaColaborador->tipopessoacadastro;
        $show = true;
        return view('colaboradors.colaborador_form', compact('colaborador', 'estados', 'cidade', 'cidades', 'cidades1', 'bairros', 'cidadesN', 'tipopessoas', 'telefonetipos', 'estadocivils', 'parentescos', 'tiposexames', 'ruas', 'cargos', 'tipopessoa', 'show'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Colaborador $col) {
        $this->authorize('update',$col);
        $colaborador = Colaborador::find($id);
        $this->authorize('igualdadeEdicao',$colaborador);
        $estados = Estado::all()->pluck('descricao', 'uf');
        $cidade = Cidade::find($colaborador->{'cidade_id'});
        $cidades = Cidade::where([['grupo_id', Session::get('empresa_padrao')->grupo_id],['uf', $cidade->{'uf'}]])
        ->orWhere([['grupo_id', null],['uf', $cidade->{'uf'}]])
        ->orderby('descricao')->pluck('descricao', 'id');

        $cidades1 = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orWhere('grupo_id', null)->orderby('descricao')->pluck('descricao', 'id');

        $bairros = Bairro::where([['cidade_id', $cidade->id], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao', 'id');
        $ruas = Rua::where([['cidade_id', $colaborador->{'cidade_id'}], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao', 'id');
        $cargos = Cargo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');

        $colaborador["datanascimento"] = requestDataOracle($colaborador->datanascimento);
        $colaborador["dataadmissao"] = requestDataOracle($colaborador->dataadmissao);
        $colaborador["datadesligamento"] = requestDataOracle($colaborador->datadesligamento);
        $cidadesN = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('uf', $colaborador->cidadenatal_uf)->orderby('descricao')->pluck('descricao', 'id');
        $tipopessoasdados = Tipopessoa::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->get();
        $tipopessoas = ['' => 'Selecione'];
        foreach ($tipopessoasdados as $tipopessoa) {
            $tipopessoas[$tipopessoa->id . $tipopessoa->tipopessoacadastro] = $tipopessoa->descricao;
        }
        $telefonetipos = Telefonetipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $estadocivils = Estadocivil::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $parentescos = Parentesco::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $tiposexames = Tipoexame::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');

        foreach ($colaborador->colaboradorfamilias as $colaboradorfamilia) {
            $colaboradorfamilia["datanascimento"] = requestDataOracleSemHora($colaboradorfamilia->datanascimento);
        }

        foreach ($colaborador->colaboradorferias as $colaboradorferias) {
            $colaboradorferias["datainicio"] = requestDataOracleSemHora($colaboradorferias->datainicio);
        }

        foreach ($colaborador->colaboradorexames as $colaboradorexame) {
            $colaboradorexame["data"] = requestDataOracle($colaboradorexame->data);
            $colaboradorexame["datavencimento"] = requestDataOracle($colaboradorexame->datavencimento);
        }
        $cep = $colaborador->cep;
        $tipopessoaColaborador = Tipopessoa::find($colaborador->tipopessoa_id);
        $tipopessoa = $tipopessoaColaborador->id . $tipopessoaColaborador->tipopessoacadastro;
        return view('colaboradors.colaborador_form', compact('colaborador', 'estados', 'cidade', 'cidades', 'cidades1', 'bairros', 'cidadesN', 'tipopessoas', 'telefonetipos', 'estadocivils', 'parentescos', 'tiposexames', 'ruas', 'cargos', 'cep', 'tipopessoa'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Colaborador $col) {
        $this->authorize('update',$col);
        $colaborador = Colaborador::findOrFail($id);
        $this->authorize('igualdadeEdicao',$colaborador);
        $this->validate($request, [
            'tipopessoa_id' => 'required',
            'nome' => 'required',
            'numero' => 'required',
            'cidade_id' => 'required',
            'bairro_id' => 'required'
            ]);
        $data = $request->only('id', 'grupo_id', 'empresa_id', 'tipopessoa_id', 'ativo', 'nome', 'cpf', 'rg', 'sexo', 'ctps', 'estadocivil_id', 'datanascimento', 'dataadmissao', 'datadesligamento', 'cnpj', 'inscricao_estadual', 'cargo_id', 'observacoes', 'cep', 'uf', 'cidade_id', 'bairro_id', 'rua_id', 'numero', 'complemento', 'ponto_referencia', 'email', 'rua_descricao');
        if ($data['rg'] != '') {
            $this->validate($request, [
                'rg' => 'unique:colaboradors,rg,' . $id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
                ]);
        }
        if ($data['cpf'] != '') {
            $this->validate($request, [
                'cpf' => 'cpf|unique:colaboradors,cpf,' . $id . ',id,empresa_id,' . Session::get('empresa_padrao')->id
                ]);
        }
        $data["user_id"] = \Auth::user()->id;
        $data["datanascimento"] = insertDataOracle($request->only('datanascimento')['datanascimento']);
        $data["dataadmissao"] = insertDataOracle($request->only('dataadmissao')['dataadmissao']);
        $data["datadesligamento"] = insertDataOracle($request->only('datadesligamento')['datadesligamento']);
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        $data["rgorgao"] = "X";
        $data['tipopessoa_id'] = str_replace("F", '', $data['tipopessoa_id']);
        $data['tipopessoa_id'] = str_replace("J", '', $data['tipopessoa_id']);

        DB::beginTransaction();
        try {

            if (starts_with($data["rua_id"], "N")) $this->createRua($data);

            $colaborador->update($data);

            $fones = [];
            if ($request->only('telefones')["telefones"] != null) {
                $telefones = json_decode($request->only('telefones')["telefones"]);
                foreach ($telefones as $telefone) {
                    $fone = new Colaboradortelefone();
                    $fone["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $fone["empresa_id"] = Session::get('empresa_padrao')->id;
                    $fone["telefonetipo_id"] = $telefone[0];
                    $fone["telefone"] = $telefone[2];
                    array_push($fones, $fone);
                }
            }
            $colaborador->telefones()->delete();
            $colaborador->telefones()->saveMany($fones);


            $colaboradorfamilias = [];
            if ($request->only('colaboradorfamilias')["colaboradorfamilias"] != null) {
                $familias = json_decode($request->only('colaboradorfamilias')["colaboradorfamilias"]);

                foreach ($familias as $familia) {
                    $colaboradorfamilia = new Colaboradorfamilia();
                    $colaboradorfamilia["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $colaboradorfamilia["empresa_id"] = Session::get('empresa_padrao')->id;
                    $colaboradorfamilia["colaborador_id"] = $id;
                    $colaboradorfamilia["parentesco_id"] = $familia[0];
                    $colaboradorfamilia["nome"] = $familia[2];
                    $colaboradorfamilia["datanascimento"] = insertDataOracle($familia[3]);
                    array_push($colaboradorfamilias, $colaboradorfamilia);
                }
            }
            $colaborador->colaboradorfamilias()->delete();
            $colaborador->colaboradorfamilias()->saveMany($colaboradorfamilias);

            $feriascolaborador = [];
            if ($request->only('colaboradorferias')["colaboradorferias"] != null) {
                $colaboradorferiasrequest = json_decode($request->only('colaboradorferias')["colaboradorferias"]);
                foreach ($colaboradorferiasrequest as $ferias) {
                    $colaboradorferias = new Colaboradorferias();
                    $colaboradorferias["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $colaboradorferias["empresa_id"] = Session::get('empresa_padrao')->id;
                    $colaboradorferias["colaborador_id"] = $id;
                    $colaboradorferias["datainicio"] = insertDataOracle($ferias[0]);
                    $colaboradorferias["dias"] = $ferias[1];
                    $colaboradorferias["gozada"] = $ferias[2] == 'Sim' ? true : false;
                    array_push($feriascolaborador, $colaboradorferias);
                }
            }
            $colaborador->colaboradorferias()->delete();
            $colaborador->colaboradorferias()->saveMany($feriascolaborador);

            $colaboradorexames = [];
            if ($request->only('colaboradorexames')["colaboradorexames"] != null) {
                $colaboradorexamesrequest = json_decode($request->only('colaboradorexames')["colaboradorexames"]);

                foreach ($colaboradorexamesrequest as $exame) {
                    $colaboradorexame = new Colaboradorexame();
                    $colaboradorexame["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $colaboradorexame["empresa_id"] = Session::get('empresa_padrao')->id;
                    $colaboradorexame["colaborador_id"] = $id;
                    $colaboradorexame["tipoexame_id"] = $exame[0];
                    $colaboradorexame["data"] = insertDataOracle($exame[2]);
                    $colaboradorexame["datavencimento"] = insertDataOracle($exame[3]);
                    $colaboradorexame["alerta"] = $exame[4] == 'Sim' ? true : false;
                    array_push($colaboradorexames, $colaboradorexame);
                }
            }
            $colaborador->colaboradorexames()->delete();
            $colaborador->colaboradorexames()->saveMany($colaboradorexames);
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to("/colaborador/$id/edit")
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        return \Redirect::route('colaborador.index')->withMessageSuccess('Colaborador atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Colaborador $col) {
        $this->authorize('delete',$col);
        DB::beginTransaction();
        try {
            Colaborador::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    function buscaPorSetor($setor_id) {
        if($setor_id == -1 || $setor_id=="null" || !$setor_id){
            return Colaborador::where([
                ['colaboradors.ativo', 1],
                ])
            //->join('setorcolaboradores', 'setorcolaboradores.colaborador_id', '=', 'colaboradors.id')
            ->orderBy('colaboradors.nome')
            ->select(DB::Raw('id as colaborador_id'), 'nome')
            ->get();
        }
        return Colaborador::where([
            ['colaboradors.ativo', 1],
            ['setorcolaboradores.setor_id', $setor_id]
            ])
        ->join('setorcolaboradores', 'setorcolaboradores.colaborador_id', '=', 'colaboradors.id')
        ->orderBy('colaboradors.nome')->get();
    }

    private function createRua(&$data)
    {
        $ruaCon = new RuaController();
        $rua = $ruaCon->createRuaFromOther($data);

        $data["rua_id"] = $rua->id;
    }

}
