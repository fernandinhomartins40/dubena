<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\Setor;
use App\Estado;
use App\Cliente;
use App\Veiculo;
use App\Veiculotipo;
use App\Colaborador;
use App\Http\Requests;
use App\Tipodocumento;
use App\Tipocombustivel;
use App\Veiculodocumento;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class VeiculoController extends Controller {

    protected $msgsValidacao = array(
            'descricao.required' => 'O campo Descrição é obrigatório.',
            'placa.required' => 'O campo Placa é obrigatório.',
            'placauf.required' => 'O campo UF da Placa é obrigatório.',
            'placa.unique' => 'A Placa já esta em uso.',
            'descricao.required' => 'O campo Descrição é obrigatório.',
            'pneus.required' => 'O campo Pneus é obrigatório.',
            'pneusvidautilkm.required' => 'O campo Pneus vida útil km é obrigatório.',
            'kminicial.required' => 'O campo Km inicial é obrigatório.',
            'kmtrocaoleo.required' => 'O campo Troca de óleo a cada (KM) é obrigatório.',
            'kmultimatrocaoleo.required' => 'O campo Km última troca óleo é obrigatório.',
            'alertasdiasantes.required' => 'O campo Alertas dias antes é obrigatório.'
        );

    public function index(Veiculo $vei) {
        $this->authorize('view',$vei);
        $veiculos = Veiculo::where([
            ['empresa_id', Session::get('empresa_padrao')->id]
            ])->get();
        return view('veiculos.veiculos', compact('veiculos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Veiculo $vei) {
        $this->authorize('create',$vei);
        $tipodocumentos = Tipodocumento::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione','');;
        $veiculotipos = Veiculotipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $tipocombustivels = Tipocombustivel::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione','');
        $colaboradors = Colaborador::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('nome')->pluck('nome', 'id');
        $clientes = Cliente::where('transportador', true)->where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('nome')->pluck('nome', 'id');
        $setors = Setor::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('descricao')->pluck('descricao', 'id');
        $estados = Estado::all()->pluck('descricao', 'uf')->prepend('Selecione', '');
        $inputSetorsListId = '';
        $inputSetorsList = '';

        return view('veiculos.veiculo_form', compact('tipodocumentos', 'veiculotipos', 'tipocombustivels', 'colaboradors', 'clientes', 'setors', 'inputSetorsList', 'inputSetorsListId', 'estados'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Veiculo $vei) {
        $this->authorize('create',$vei);
        $this->validate($request, [
            'placa' => 'required|unique:veiculos,placa,NULL,id,empresa_id,' . Session::get('empresa_padrao')->id,
            'placauf' => 'required',
            'descricao' => 'required',
            'kminicial' => 'required',
            'kmtrocaoleo' => 'required',
            'kmultimatrocaoleo' => 'required',
            'pneus' => 'required',
            'pneusvidautilkm' => 'required',
            'alertasdiasantes' => 'required',
            ], $this->msgsValidacao);
        $data = $request->only('grupo_id', 'empresa_id', 'veiculotipo_id', 'tipocombustivel_id',
            'colaborador_id', 'placa', 'descricao', 'kminicial', 'kmatual', 'kmtrocaoleo', 'kmultimatrocaoleo',
            'pneus', 'pneusvidautilkm', 'alertasdiasantes', 'ativo', 'usarastreamento', 'inputSetorsListId', 'placauf');

        $data["kmatual"] = $data["kminicial"];
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        if($data["ativo"] == null){
            $data["ativo"] = "0";
        }
        if($data["usarastreamento"] == null){
            $data["usarastreamento"] = "0";
        }

        DB::beginTransaction();
        try {
            $veiculo = Veiculo::create($data);

            $docs = [];
            if ($request->only('documentos')["documentos"] != null) {
                $documentos = json_decode($request->only('documentos')["documentos"]);
                foreach ($documentos as $documento) {
                    $doc = new Veiculodocumento();
                    $doc["veiculo_id"] = $veiculo->id;
                    $doc["descricao"] = $documento[0];
                    $doc["numero"] = $documento[3];
                    $doc["vencimento"] = insertDataOracle($documento[4]);
                    $doc["alerta"] = $documento[5] == 'Sim' ? true : false;
                    $doc["tipodocumento_id"] = $documento[1];
                    array_push($docs, $doc);
                }
            }
            $setors_id = [];
            if ($data['inputSetorsListId'] !== '') {
                $setors_id = explode('||', $data['inputSetorsListId']);
            }
            $veiculo->setors()->sync($setors_id);

            $veiculo->veiculodocumentos()->delete();
            $veiculo->veiculodocumentos()->saveMany($docs);
        } catch (ValidationException $e) {
            DB::rollback();
            return Redirect::to('/veiculo/create')
            ->withErrors($e->getMessage())
            ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/veiculo/create')
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        return \Redirect::route('veiculo.index')->withMessageSuccess('Veículo cadastrado com sucesso!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Veiculo $vei) {
        $this->authorize('view',$vei);
        $veiculo = Veiculo::find($id);
        $this->authorize('igualdade',$veiculo);
        $tipodocumentos = Tipodocumento::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $veiculotipos = Veiculotipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $tipocombustivels = Tipocombustivel::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $colaboradors = Colaborador::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('nome')->pluck('nome', 'id');
        $clientes = Cliente::where('transportador', true)->where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('nome')->pluck('nome', 'id');
        $setors = Setor::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('descricao')->pluck('descricao', 'id');
        $estados = Estado::all()->pluck('descricao', 'uf')->prepend('Selecione', '');

        // dd($veiculo->veiculodocumentos);
        foreach ($veiculo->veiculodocumentos as $doc) {
            $doc["vencimento"] = requestDataOracle($doc->vencimento,false);
        }
        $inputSetorsListId = '';
        $inputSetorsList = '';
        $first = true;
        foreach ($veiculo->setors as $value) {
            $inputSetorsListId .= ($first?'':'||') . $value->id;
            $inputSetorsList .= ($first?'':'||') . $value->id;
            $first = false;
        }
        $show = true;
        return view('veiculos.veiculo_form', compact('veiculo', 'show', 'tipodocumentos', 'veiculotipos', 'tipocombustivels', 'colaboradors', 'clientes', 'setors', 'inputSetorsList', 'inputSetorsListId', 'estados'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Veiculo $vei) {
        $this->authorize('update',$vei);
        $veiculo = Veiculo::find($id);
        $this->authorize('igualdade',$veiculo);
        $tipodocumentos = Tipodocumento::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id')->prepend('Selecione','');;
        $veiculotipos = Veiculotipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $tipocombustivels = Tipocombustivel::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $colaboradors = Colaborador::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('nome')->pluck('nome', 'id');
        $clientes = Cliente::where('transportador', true)->where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('nome')->pluck('nome', 'id');
        $setors = Setor::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->orderBy('descricao')->pluck('descricao', 'id');
        $estados = Estado::all()->pluck('descricao', 'uf')->prepend('Selecione', '');

        // dd($veiculo->veiculodocumentos);
        foreach ($veiculo->veiculodocumentos as $doc) {
            $doc["vencimento"] = requestDataOracle($doc->vencimento, false);
        }
        $inputSetorsListId = '';
        $inputSetorsList = '';
        $first = true;
        foreach ($veiculo->setors as $value) {
            $inputSetorsListId .= ($first?'':'||') . $value->id;
            $inputSetorsList .= ($first?'':'||') . $value->id;
            $first = false;
        }
        return view('veiculos.veiculo_form', compact('veiculo', 'tipodocumentos', 'veiculotipos', 'tipocombustivels', 'colaboradors', 'clientes', 'setors', 'inputSetorsList', 'inputSetorsListId', 'estados'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Veiculo $vei) {
        $this->authorize('update',$vei);
        $veiculo = Veiculo::findOrFail($id);
        $this->authorize('igualdade',$veiculo);
        $this->validate($request, [
            'placa' => 'required|unique:veiculos,placa,' . $id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
            'placauf' => 'required',
            'descricao' => 'required',
            'kminicial' => 'required',
            'kmtrocaoleo' => 'required',
            'kmultimatrocaoleo' => 'required',
            'pneus' => 'required',
            'pneusvidautilkm' => 'required',
            'alertasdiasantes' => 'required',
            ], $this->msgsValidacao);
        $data = $request->only('grupo_id', 'empresa_id', 'veiculotipo_id', 'tipocombustivel_id', 'colaborador_id', 'placa', 'descricao', 'kminicial', 'kmatual', 'kmtrocaoleo', 'kmultimatrocaoleo', 'pneus', 'pneusvidautilkm', 'alertasdiasantes', 'ativo', 'usarastreamento', 'inputSetorsListId', 'placauf');
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $data["empresa_id"] = Session::get('empresa_padrao')->id;
        if($data["ativo"] == null){
            $data["ativo"] = "0";
        }
        if($data["usarastreamento"] == null){
            $data["usarastreamento"] = "0";
        }
        DB::beginTransaction();
        try {
            $veiculo->update($data);
            $docs = [];
            if ($request->only('documentos')["documentos"] != null) {
                $documentos = json_decode($request->only('documentos')["documentos"]);
                foreach ($documentos as $documento) {
                    $doc = new Veiculodocumento();
                    $doc["veiculo_id"] = $veiculo->id;
                    $doc["descricao"] = $documento[0];
                    $doc["numero"] = $documento[3];
                    $doc["vencimento"] = insertDataOracle($documento[4]);
                    $doc["alerta"] = $documento[5] == 'Sim' ? true : false;
                    $doc["tipodocumento_id"] = $documento[1];
                    array_push($docs, $doc);
                }
            }
            $veiculo->veiculodocumentos()->delete();
            $veiculo->veiculodocumentos()->saveMany($docs);
            $setors_id = [];
            if ($data['inputSetorsListId'] !== '') {
                $setors_id = explode('||', $data['inputSetorsListId']);
            }
            $veiculo->setors()->sync($setors_id);


        } catch (ValidationException $e) {
            DB::rollback();
            return Redirect::to('/veiculo/' . $id. "/edit")
            ->withErrors($e->getMessage())
            ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/veiculo/' . $id. "/edit")
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        return \Redirect::route('veiculo.index')->withMessageSuccess('Veículo atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Veiculo $vei) {
        $this->authorize('delete',$vei);
        $veiculo = Veiculo::find($id);
        $this->authorize('igualdade',$veiculo);
        DB::beginTransaction();
        try {
            Veiculo::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    //Manutenção de veiculos Ajax
    public function buscarVeiculoAjax($id) {
        return Veiculo::join('colaboradors', 'colaboradors.id', '=', 'veiculos.colaborador_id')
        ->where([
            ['veiculos.empresa_id', Session::get('empresa_padrao')->id],
            ['veiculos.ativo', 1],
            ['veiculos.id', $id]
            ])
        ->select(['colaboradors.id as colaborador_id', 'colaboradors.nome',
            'veiculos.kmatual', 'veiculos.kmultimatrocaoleo', 'veiculos.kmtrocaoleo',
            ])
        ->get();
    }

}
