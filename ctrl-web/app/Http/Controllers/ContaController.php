<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\User;
use App\Conta;
use App\Banco;
use App\Centrocusto;
use App\Condicaopagamento;
use App\Contaextratoconfig;
use App\Contamovimentotipo;
use App\Empresa;
use App\Contatipo;
use App\Contauser;
use App\Contatalao;
use App\Layoutbanco;
use App\Http\Requests;
use App\EmpresasGrupo;
use App\Enums\ContaextratoAcao;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Planoconta;
use Illuminate\Support\Facades\Input;
use App\Services\CarbonCustom as Carbon;

class ContaController extends Controller
{
    protected $msgsValidacaoExtratoconfig = array(
        'descricao.required' => 'O campo Texto no extrato é obrigatório.',
        'condicaopagamento_id.required' => 'O campo Condição de Pagamento é obrigatório.',
        'contamovimentotipo_id.required' => 'O campo Tipo de Movimento é obrigatório.',
        'pc_id.required' => 'O campo Plano de Contas é obrigatório.',
        'cc_id.required' => 'O campo Centro de Custos é obrigatório.',
        'cliente_id.required' => 'O campo Cliente é obrigatório.',
        'contaorigem_id.required' => 'O campo Conta Origem é obrigatório.',
    );


    public function __construct()
    {
        $this->middleware('auth');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Conta $co)
    {
        $this->authorize('view', $co);
        $contas = Conta::where([
            ['empresa_id', Session::get('empresa_padrao')->id]
        ])->get();

        return view('conta.contas', compact('contas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Conta $co)
    {
        $this->authorize('create', $co);
        $tipos = Contatipo::orderBy('perfil')->pluck('descricao', 'id');
        $bancos = Banco::orderBy('descricao')->pluck('descricao', 'id');
        $layouts = Layoutbanco::where('ativo', true)->get()->pluck('descricao', 'id')->prepend('Selecione', '');

        $usersdb = Empresa::where('id', Session::get('empresa_padrao')->id)->first()->users()
            ->where('users.support', 0)
            ->orderBy('name')
            ->get(['id', 'name']);

        $users = array();
        foreach ($usersdb as $user) {
            $users[$user->id] = $user->name;
        }
        $contamovimentotipos = Contamovimentotipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $condicaopagamentos = Condicaopagamento::where([
            ['ativo', 1],
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
        ])->pluck('descricao', 'id')->prepend('Selecione', '');
        $centrocustos = Centrocusto::menuspermissoesAll();
        $planocontas = Planoconta::menuspermissoesAll();
        $extratoacoes = collect(ContaextratoAcao::getCasesForSelect())->prepend("Selecione", "");
        $extratoacaolancar = ContaextratoAcao::from(ContaextratoAcao::Lancar);
        $extratoacaotransferir = ContaextratoAcao::from(ContaextratoAcao::Transferir);
        $extratoacaolancarbaixar = ContaextratoAcao::from(ContaextratoAcao::LancarBaixar);
        $contas = Conta::where('empresa_id', Session::get('empresa_padrao')->id)->where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');


        return view('conta.conta_form', compact('tipos', 'bancos', 'users', 'layouts', 'contamovimentotipos', 'condicaopagamentos', 'centrocustos', 'planocontas', 'extratoacoes', 'extratoacaolancar', 'extratoacaotransferir', 'contas', 'extratoacaolancarbaixar'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Conta $co)
    {
        $this->authorize('create', $co);
        $this->validate($request, [
            'descricao' => 'required|unique:contas,descricao,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
            'conta' => 'required',
            'contatipo_id' => 'required'
        ]);
        $data = $request->only(
            'contatipo_id',
            'banco_id',
            'descricao',
            'ativo',
            'conta',
            'agencia',
            'saldoinicial',
            'saldoatual',
            'boletoemite',
            'users',
            'talaos',
            'codigoofx'
        );
        $data['codigoofx'] = trim($data['codigoofx']);
        $bank = ['1', '5'];
        if (! $data['banco_id']) {
            $data['banco_id'] = null;
            $data['agencia'] = null;
        }
        if (! in_array($data["contatipo_id"], $bank)) {
            $data['banco_id'] = null;
            $data['agencia'] = null;
        }
        if ($data["contatipo_id"] != 1 || !$data["boletoemite"]) {
            $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
            $data["empresa_id"] = Session::get('empresa_padrao')->id;
            $data["boletoemite"] = false;
            $data["boletosequencia"] = null;
            $data["boletocarteira"] = null;
            $data["boletobyte"] = null;
            $data["boletomulta"] = null;
            $data["boletojuros"] = null;
            $data["boletoaceite"] = null;
            $data["boletoespecie"] = null;
            $data["boletoremessasequencia"] = null;
            $data["boletocedente"] = null;
            $data["boletocedentedigito"] = null;
            $data["boletocomprovanteentrega"] = false;
            $data["boletoinstrucoes"] = null;
            $data["boletovencimentominimodias"] = null;
            $data["boletovidesacadoravalista"] = false;
            $data["boletodiasprotesto"] = null;
            $data["boletocorrespondente"] = false;
            $data["boletocorrespondentebanco_id"] = null;
        } else if ($data["boletoemite"]) {
            $data = $request->only(
                'contatipo_id',
                'banco_id',
                'descricao',
                'ativo',
                'conta',
                'agencia',
                'saldoinicial',
                'saldoatual',
                'boletoemite',
                'users',
                'talaos',
                'boletosequencia',
                'boletocarteira',
                'boletobyte',
                'boletomulta',
                'boletojuros',
                'boletoaceite',
                'boletoespecie',
                'boletoremessasequencia',
                'boletocedente',
                'boletocedentedigito',
                'boletocomprovanteentrega',
                'boletoinstrucoes',
                'boletovencimentominimodias',
                'boletovidesacadoravalista',
                'boletodiasprotesto',
                'layoutbanco_id',
                'boletocorrespondente',
                'boletocorrespondentebanco_id',
                'boletoprotesto_baixadevolucao',
                'codigoofx'
            );

            $data['codigoofx'] = trim($data['codigoofx']);
            $data['boletocomprovanteentrega'] = !is_null($data['boletocomprovanteentrega']);
            $data['boletocorrespondente'] = !is_null($data['boletocorrespondente']);
            $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
            $data["empresa_id"] = Session::get('empresa_padrao')->id;
            $data['boletomulta'] = insertPercentualOracle($data['boletomulta']);
            $data['boletojuros'] = insertPercentualOracle($data['boletojuros']);
        }
        $data['saldoinicial'] = insertNumeroDecimalOracle($data['saldoinicial']);
        $data['saldoatual'] = insertNumeroDecimalOracle($data['saldoatual']);

        $data = emptyToNull($data);
        DB::beginTransaction();
        try {
            $conta = Conta::create($data);
            if ($data["boletoemite"]) {
                if (!strlen($data['boletoprotesto_baixadevolucao'])) {
                    throw new \Exception("O campo Instrução é obrigatório quando Emite Boleto estiver marcado.");
                }
                $instrucoesExp = explode(';', $data['boletoinstrucoes']);
                $count = 0;
                foreach ($instrucoesExp as $inst) {
                    if (!empty($inst))
                        $count++;
                }
                if ($count > 2)
                    throw new \Exception("O sistema só permite um limite de 2 instruções para gerar o boleto. Agrupe-as na mesma linha para inserir mais.");

                $layoutbanco = Layoutbanco::find($data['layoutbanco_id']);
                if (is_null($layoutbanco))
                    throw new \Exception("Não foi possível encontrar o layout de cobrança: " . $data['layoutbanco_id']);

                $data['boletoposicoesnossonumero'] = $layoutbanco->boletoposicoesnossonumero;

                if ($layoutbanco->codigo_banco != $conta->banco->codigo)
                    throw new \Exception("O código do banco escolhido para a conta não é o mesmo do selecionado para o layout.");
            }

            if (in_array($conta->contatipo_id, $bank) && is_null($conta->banco_id))
                throw new \Exception("O campo Banco precisa ser preenchido");

            $conta->fechado = true;
            $conta->save();
            $users = $this->givePermissoes($conta, $data["users"]);
            $talaos = $this->createCheques($conta, $data["talaos"]);
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::back()
                ->withErrors($e->getMessage())
                ->withInput();
        }
        DB::commit();
        return \Redirect::route('conta.index')->withMessageSuccess('Conta cadastrada com sucesso!');;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Conta $co)
    {
        $this->authorize('view', $co);
        $Conta = Conta::find($id);
        $this->authorize('igualdade', $Conta);
        $show = true;
        $tipos = Contatipo::orderBy('perfil')->pluck('descricao', 'id');
        $bancos = Banco::orderBy('descricao')->pluck('descricao', 'id');
        $layouts = Layoutbanco::where('ativo', true)->get()->pluck('descricao', 'id')->prepend('Selecione', '');
        $empresa = Session::get('empresa_padrao')->id;
        $usersdb = Empresa::find($empresa)->users()
            ->where('users.support', 0)
            ->orderBy('name')
            ->get(['id', 'name']);

        $users = array();
        foreach ($usersdb as $user) {
            $users[$user->id] = $user->name;
        }
        $Conta->saldoinicial = requestNumeroDecimalOracle($Conta->saldoinicial);
        $Conta->saldoatual = requestNumeroDecimalOracle($Conta->saldoatual);
        $Conta->boletomulta = requestPercentualOracle($Conta->boletomulta);

        $contamovimentotipos = Contamovimentotipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $condicaopagamentos = Condicaopagamento::where([
            ['ativo', 1],
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
        ])->pluck('descricao', 'id')->prepend('Selecione', '');
        $centrocustos = Centrocusto::menuspermissoesAll();
        $planocontas = Planoconta::menuspermissoesAll();
        $extratoacoes = collect(ContaextratoAcao::getCasesForSelect())->prepend("Selecione", "");
        $extratoacaolancar = ContaextratoAcao::from(ContaextratoAcao::Lancar);
        $extratoacaotransferir = ContaextratoAcao::from(ContaextratoAcao::Transferir);
        $extratoacaolancarbaixar = ContaextratoAcao::from(ContaextratoAcao::LancarBaixar);
        $contas = Conta::where('empresa_id', Session::get('empresa_padrao')->id)->where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');


        return view('conta.conta_form', compact('Conta', 'tipos', 'bancos', 'users', 'show', 'layouts', 'contamovimentotipos', 'condicaopagamentos', 'centrocustos', 'planocontas', 'extratoacoes', 'extratoacaolancar', 'extratoacaotransferir', 'contas', 'extratoacaolancarbaixar'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Conta $co)
    {
        $this->authorize('update', $co);
        $Conta = Conta::find($id);
        $this->authorize('igualdade', $Conta);
        $tipos = Contatipo::orderBy('perfil')->pluck('descricao', 'id');
        $bancos = Banco::orderBy('descricao')->pluck('descricao', 'id');
        $layouts = Layoutbanco::where('ativo', true)->get()->pluck('descricao', 'id')->prepend('Selecione', '');
        $empresa = Session::get('empresa_padrao')->id;
        $usersdb = Empresa::find($empresa)->users()
            ->where('users.support', 0)
            ->orderBy('name')
            ->get(['id', 'name']);

        $users = array();
        foreach ($usersdb as $user) {
            $users[$user->id] = $user->name;
        }
        $Conta->saldoinicial = requestNumeroDecimalOracle($Conta->saldoinicial);
        $Conta->saldoatual = requestNumeroDecimalOracle($Conta->saldoatual);
        $Conta->boletomulta = requestPercentualOracle($Conta->boletomulta);

        $contamovimentotipos = Contamovimentotipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        $condicaopagamentos = Condicaopagamento::where([
            ['ativo', 1],
            ['grupo_id', Session::get('empresa_padrao')->grupo_id]
        ])->pluck('descricao', 'id')->prepend('Selecione', '');
        $centrocustos = Centrocusto::menuspermissoesAll();
        $planocontas = Planoconta::menuspermissoesAll();
        $extratoacoes = collect(ContaextratoAcao::getCasesForSelect())->prepend("Selecione", "");
        $extratoacaolancar = ContaextratoAcao::from(ContaextratoAcao::Lancar);
        $extratoacaotransferir = ContaextratoAcao::from(ContaextratoAcao::Transferir);
        $extratoacaolancarbaixar = ContaextratoAcao::from(ContaextratoAcao::LancarBaixar);
        $contas = Conta::where('empresa_id', Session::get('empresa_padrao')->id)->where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione', '');
        return view('conta.conta_form', compact('Conta', 'tipos', 'bancos', 'users', 'layouts', 'contamovimentotipos', 'condicaopagamentos', 'centrocustos', 'planocontas', 'extratoacoes', 'extratoacaolancar', 'extratoacaotransferir', 'contas', 'extratoacaolancarbaixar'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Conta $co)
    {
        $this->authorize('update', $co);
        $this->validate($request, [
            'descricao' => 'required',
            'conta' => 'required',
            'contatipo_id' => 'required'
        ]);
        $data = $request->only(
            'contatipo_id',
            'banco_id',
            'descricao',
            'ativo',
            'conta',
            'agencia',
            'saldoinicial',
            'saldoatual',
            'boletoemite',
            'users',
            'talaos',
            'codigoofx'
        );
        $data['codigoofx'] = trim($data['codigoofx']);
        $bank = ['1', '5'];
        if (! $data['banco_id']) {
            $data['banco_id'] = null;
            $data['agencia'] = null;
        }
        if (! in_array($data["contatipo_id"], $bank)) {
            $data['banco_id'] = null;
            $data['agencia'] = null;
        }
        if ($data["contatipo_id"] != 1 || !$data["boletoemite"]) {
            $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
            $data["empresa_id"] = Session::get('empresa_padrao')->id;
            $data["boletoemite"] = false;
            $data["boletosequencia"] = null;
            $data["boletocarteira"] = null;
            $data["boletobyte"] = null;
            $data["boletomulta"] = null;
            $data["boletojuros"] = null;
            $data["boletoaceite"] = null;
            $data["boletoespecie"] = null;
            $data["boletoremessasequencia"] = null;
            $data["boletocedente"] = null;
            $data["boletocedentedigito"] = null;
            $data["boletocomprovanteentrega"] = false;
            $data["boletoinstrucoes"] = null;
            $data["boletodiasprotesto"] = null;
            $data["boletoposicoesnossonumero"] = null;
            $data["boletocorrespondente"] = false;
            $data["boletocorrespondentebanco_id"] = null;
        } else if ($data["boletoemite"]) {
            $data = $request->only(
                'contatipo_id',
                'banco_id',
                'descricao',
                'ativo',
                'conta',
                'agencia',
                'saldoinicial',
                'saldoatual',
                'boletoemite',
                'users',
                'talaos',
                'boletosequencia',
                'boletocarteira',
                'boletobyte',
                'boletomulta',
                'boletojuros',
                'boletoaceite',
                'boletoespecie',
                'boletoremessasequencia',
                'boletocedente',
                'boletocedentedigito',
                'boletocomprovanteentrega',
                'boletoinstrucoes',
                'boletodiasprotesto',
                'layoutbanco_id',
                'boletoprotesto_baixadevolucao',
                'boletocorrespondente',
                'boletocorrespondentebanco_id',
                'codigoofx'
            );

            $data['codigoofx'] = trim($data['codigoofx']);
            $data['boletocomprovanteentrega'] = !is_null($data['boletocomprovanteentrega']);
            $data['boletocorrespondente'] = !is_null($data['boletocorrespondente']);
            $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
            $data["empresa_id"] = Session::get('empresa_padrao')->id;
            $data['boletomulta'] = insertPercentualOracle($data['boletomulta']);
            $data['boletojuros'] = insertPercentualOracle($data['boletojuros']);
        }
        $data['saldoinicial'] = insertNumeroDecimalOracle($data['saldoinicial']);
        $data['saldoatual'] = insertNumeroDecimalOracle($data['saldoatual']);

        $data = emptyToNull($data);
        DB::beginTransaction();
        try {
            $conta = Conta::findOrFail($id);
            $this->authorize('igualdade', $conta);

            if ($data["boletoemite"]) {
                if (!strlen($data['boletoprotesto_baixadevolucao'])) {
                    throw new \Exception("O campo Instrução é obrigatório quando Emite Boleto estiver marcado.");
                }
                $instrucoesExp = explode(';', $data['boletoinstrucoes']);
                $count = 0;
                foreach ($instrucoesExp as $inst) {
                    if (!empty($inst))
                        $count++;
                }
                if ($count > 2)
                    throw new \Exception("O sistema só permite um limite de 2 instruções para gerar o boleto. Agrupe-as na mesma linha para inserir mais.");
                $layoutbanco = Layoutbanco::find($data['layoutbanco_id']);
                if (is_null($layoutbanco))
                    throw new \Exception("Não foi possível encontrar o layout de cobrança: " . $data['layoutbanco_id']);

                $data['boletoposicoesnossonumero'] = $layoutbanco->boletoposicoesnossonumero;
            }
            $conta->update($data);
            $conta->save();

            if (in_array($conta->contatipo_id, $bank) && is_null($conta->banco_id))
                throw new \Exception("O campo Banco precisa ser preenchido");

            if ($data["boletoemite"] && $layoutbanco->codigo_banco != $conta->banco->codigo) {
                throw new \Exception("O código do banco escolhido para a conta não é o mesmo do selecionado para o layout.");
            }

            $revionated = $this->givePermissoes($conta, $data["users"], true);
            if (!is_bool($revionated)) {
                throw new \Exception("Erro ao salvar a revisão! $revionated");
            }

            $talaos = $this->createCheques($conta, $data["talaos"], true);
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::back()
                ->withErrors($e->getMessage())
                ->withInput();
        }
        DB::commit();
        return \Redirect::route('conta.index')->withMessageSuccess('Conta atualizada com sucesso!');;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Conta $co)
    {
        $this->authorize('delete', $co);
        $conta = Conta::findOrFail($id);
        $this->authorize('igualdade', $conta);
        DB::beginTransaction();
        try {
            $conta->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    private function givePermissoes($conta, $users, $revisionAble = false)
    {
        $old = $conta->ContaUsers;
        $contaUser = new Contauser();
        $contaUsers = collect([]);
        if ($users) {
            $usersreq = json_decode($users);
            foreach ($usersreq as $user) {
                $us = new Contauser();
                $us->conta_id = $conta->id;
                $us->user_id = $user[0];
                $us->visualizar = $user[2] == 'Sim';
                $us->operar = $user[3] == 'Sim';
                $us->transferir = $user[4] == 'Sim';
                $us->estornar = $user[5] == 'Sim';
                $us->lancarfechado = $user[6] == 'Sim';
                $us->created_at = Carbon::now();
                $us->updated_at = Carbon::now();
                $contaUsers->push($us);
            }
        }
        $conta->ContaUsers()->delete();
        $contaUser->insert($contaUsers->toArray());

        if ($revisionAble)
            return $this->revisionsUsers($old, $contaUsers, $conta);

        return true;
    }

    private function createCheques($conta, $cheques, $revisionAble = false)
    {
        $taloes = $conta->contaTalaos;
        $old = $taloes;
        $contaTalao = new Contatalao();
        $talaos = collect([]);
        if ($cheques) {
            $talaosreq = json_decode($cheques);
            foreach ($talaosreq as $talao) {
                if ($talao[0] == -1) {
                    $us = new Contatalao();
                    $us->conta_id = $conta->id;
                    $us->chequenuminicial = $talao[1];
                    $us->chequenumfinal = $talao[2];
                    $us->chequenumatual = $talao[3];
                    $us->created_at = Carbon::now();
                    $us->updated_at = Carbon::now();
                    $talaos->push($us);
                } else if ($taloes && $taloes->contains('id', $talao[0])) {
                    $taloes = $taloes->keyBy('id')->forget($talao[0]);
                }
            }
        }
        if ($taloes) {
            $ids = $taloes->pluck("id")->toArray();
            $del = DB::table('contatalaos')->whereIn('id', $ids)->delete();
        }
        $up = $contaTalao->insert($talaos->toArray());

        if ($revisionAble) {
            $new = isset($talaosreq) ? $talaosreq : [];
            return $this->revisoesCheques($old, $new, $conta);
        }
        return true;
    }

    private function revisoesCheques($old, $new, $conta)
    {
        $novo = [];
        foreach ($new as $n) {
            $novo[] = "Cheques: Inicial: " . $n[1] . " Final: " . $n[2];
        }
        $velho = [];
        foreach ($old as $o) {
            $velho[] = "Cheques: Inicial: " . $o->chequenuminicial . " Final: " . $o->chequenumfinal;
        }
        $old = implode(' | ', $velho);
        $new = implode(' | ', $novo);
        $dif = $old !== $new;
        if ($dif) {
            return $this->revisions($old, $new, $conta, "contaTalaos", "empresa_id");
        }
        return true;
    }

    private function revisionsUsers($old, $new, $conta)
    {
        $old = $old->sortBy('id');
        $new = $new->sortBy('id');
        $velho = [];
        $first = true;
        foreach ($old as $ol) {
            if ($first) {
                $st = "Usuários: ";
            } else {
                $st = "";
            }
            $velho[] = $st . "ID: $ol->user_id, V: $ol->visualizar, O: $ol->operar " .
                "T: $ol->transferir, E: $ol->estornar, R: $ol->lancarfechado";
            $first = false;
        }
        $novo = [];
        $fi = true;
        foreach ($new as $ne) {
            if ($fi) {
                $st = "Usuários: ";
            } else {
                $st = "";
            }
            $v = $ne->visualizar ? 1 : 0;
            $o = $ne->operar ? 1 : 0;
            $t = $ne->transferir ? 1 : 0;
            $e = $ne->estornar ? 1 : 0;
            $r = $ne->lancarfechado ? 1 : 0;
            $novo[] = $st . "ID: $ne->user_id, V: $v, O: $o T: $t, E: $e, R: $r";
            $fi = false;
        }
        $old = implode(' | ', $velho);
        $new = implode(' | ', $novo);
        $dif = $new !== $old;
        if ($dif) {
            return $this->revisions($old, $new, $conta, "contausers", "empresa_id");
        }
        return true;
    }

    /**
     * Manually updates Revisions Table with deleted or creation of users
     *
     * @param \Illuminate\Database\Eloquent\Model  $model
     * @param array     $newUsers
     * @param array     $oldUsers
     * @param string 	$key
     * @param string	$identity
     * @return boolean  $saved
     */
    private function revisions($old, $new, $model, $key, $identity)
    {
        $user_id = \Auth::user()->getAuthIdentifier();
        try {
            $revision = new \Venturecraft\Revisionable\Revision;
            $revision->revisionable_type = $model->getMorphClass();
            $revision->revisionable_id   = $model->getKey();
            $revision->key               = $key;
            $revision->old_value         = $old;
            $revision->new_value         = $new;
            $revision->user_id           = $user_id;
            $revision->created_at        = Carbon::now();
            $revision->updated_at        = Carbon::now();
            $revision->identity          = $model->{$identity};
            $revision->identityBy        = $identity;
            $saved = $revision->save();
        } catch (\Exception $e) {
            return getErrorsException($e);
        }
        return true;
    }

    public function addEditExtratoconfig(Request $request)
    {
        try {
            $data = $request->all();
            $operacao = $data['operacao'];

            switch ($operacao) {
                case "ADD":
                    if (
                        $data['acao_id'] == ContaextratoAcao::from(ContaextratoAcao::Lancar)->getValue() ||
                        $data['acao_id'] == ContaextratoAcao::from(ContaextratoAcao::LancarBaixar)->getValue()
                    ) {
                        $this->validate($request, [
                            'descricao'     => 'required',
                            'condicaopagamento_id'    => 'required',
                            'contamovimentotipo_id' => 'required',
                            'pc_id' => 'required',
                            'cc_id' => 'required',
                            // 'cliente_id' => 'required'
                        ], $this->msgsValidacaoExtratoconfig);
                    } elseif ($data['acao_id'] == ContaextratoAcao::from(ContaextratoAcao::Transferir)->getValue()) {
                        $this->validate($request, [
                            'descricao'     => 'required',
                            'contaorigem_id'    => 'required',
                        ], $this->msgsValidacaoExtratoconfig);
                    } else {
                        $this->validate($request, [
                            'descricao'     => 'required',
                        ], $this->msgsValidacaoExtratoconfig);
                    }
                    $conta = Conta::find($data['conta_id']);
                    throwIf(!$conta, 'Conta não encontrada');
                    $extratoconfig = new Contaextratoconfig();
                    $extratoconfig->descricao = $data['descricao'];
                    $extratoconfig->condicaopagamento_id = $data['condicaopagamento_id'];
                    $extratoconfig->contamovimentotipo_id = $data['contamovimentotipo_id'];
                    $extratoconfig->planoconta_id = $data['pc_id'];
                    $extratoconfig->centrocusto_id = $data['cc_id'];
                    $extratoconfig->cliente_id = $data['cliente_id'];
                    $extratoconfig->conta_id = $conta->id;
                    $extratoconfig->contaorigem_id = $data['contaorigem_id'];
                    $extratoconfig->acao = $data['acao_id'];
                    $extratoconfig->save();
                    $conta->refresh();
                    return responseSuccess($conta->contaExtratoconfigs());

                case "DEL":
                    $extratoconfig = Contaextratoconfig::find($data['extratoconfig_id']);
                    throwIf(!$extratoconfig, 'Configuração não encontrada');
                    throwIf($extratoconfig->conta_id != $data['conta_id'], 'Conta não encontrada');
                    $extratoconfig->delete();
                    $conta = Conta::find($data['conta_id']);
                    return responseSuccess($conta->contaExtratoconfigs());

                case "UPD":
                    if (
                        $data['acao_id'] == ContaextratoAcao::from(ContaextratoAcao::Lancar)->getValue() ||
                        $data['acao_id'] == ContaextratoAcao::from(ContaextratoAcao::LancarBaixar)->getValue()
                    ) {
                        $this->validate($request, [
                            'descricao'     => 'required',
                            'condicaopagamento_id'    => 'required',
                            'contamovimentotipo_id' => 'required',
                            'pc_id' => 'required',
                            'cc_id' => 'required',
                            // 'cliente_id' => 'required'
                        ], $this->msgsValidacaoExtratoconfig);
                        $data['contaorigem_id'] = null;
                    } elseif ($data['acao_id'] == ContaextratoAcao::from(ContaextratoAcao::Transferir)->getValue()) {
                        $this->validate($request, [
                            'descricao'     => 'required',
                            'contaorigem_id'    => 'required',
                        ], $this->msgsValidacaoExtratoconfig);
                        $data['condicaopagamento_id'] = null;
                        $data['contamovimentotipo_id'] = null;
                        $data['pc_id'] = null;
                        $data['cc_id'] = null;
                        $data['cliente_id'] = null;
                    } else {
                        $this->validate($request, [
                            'descricao'     => 'required',
                        ], $this->msgsValidacaoExtratoconfig);
                        $data['condicaopagamento_id'] = null;
                        $data['contamovimentotipo_id'] = null;
                        $data['pc_id'] = null;
                        $data['cc_id'] = null;
                        $data['cliente_id'] = null;
                    }
                    $conta = Conta::find($data['conta_id']);
                    throwIf(!$conta, 'Conta não encontrada');
                    $extratoconfig = Contaextratoconfig::find($data['extratoconfig_id']);
                    throwIf(!$extratoconfig, 'Configuração não encontrada');
                    $extratoconfig->descricao = $data['descricao'];
                    $extratoconfig->condicaopagamento_id = $data['condicaopagamento_id'];
                    $extratoconfig->contamovimentotipo_id = $data['contamovimentotipo_id'];
                    $extratoconfig->planoconta_id = $data['pc_id'];
                    $extratoconfig->centrocusto_id = $data['cc_id'];
                    $extratoconfig->cliente_id = $data['cliente_id'];
                    $extratoconfig->conta_id = $conta->id;
                    $extratoconfig->contaorigem_id = $data['contaorigem_id'];
                    $extratoconfig->acao = $data['acao_id'];
                    $extratoconfig->save();
                    $conta->refresh();
                    return responseSuccess($conta->contaExtratoconfigs());
            }
        } catch (\Exception $e) {
            DB::rollback();
            return responseError('Erro: ' . $e->getMessage());
        }
    }
}
