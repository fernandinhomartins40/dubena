<?php

namespace App\Http\Controllers;

use DB;
use App\Rua;
use Session;
use Redirect;
use App\User;
use App\Setor;
use App\Bairro;
use App\Cidade;
use App\Estado;
use App\Android;
use App\Nfoperacao;
use App\Colaborador;
use App\Estoquesetor;
use App\Produtoclasse;
use App\Setorcolaboradores;
use App\Http\Requests\SetorRequest;

class SetorController extends Controller
{

    function index(Setor $set)
    {
        $this->authorize('view', $set);
        $setores = Setor::where([
            ['empresa_id', Session::get('empresa_padrao')->id]
        ])->get();
        return View('setores.setor', compact('setores'));
    }

    function create(Setor $set)
    {
        $this->authorize('create', $set);
        $estados = Estado::all()->pluck('descricao', 'uf');
        $cidades = [];
        $bairros = [];
        $ruas = [];
        $classeitens = Produtoclasse::where('grupo_id', Session::get('empresa_padrao')->id)->pluck('descricao', 'id');
        $classeitem = '';
        $operacoes = [];
        $operacao = '';
        $inputColaboradoresListId = '';
        $inputColaboradoresList = '';
        $colaboradores = Colaborador::where([
            ['empresa_id', Session::get('empresa_padrao')->id],
            ['ativo', 1]
        ])->orderBy('nome')->pluck('nome', 'id');
        $colaborador = '';
        $cep = Session::get('empresa_padrao')->cep;
        $nfoperacao = Nfoperacao::where(['empresa_id' => Session::get('empresa_padrao')->id])
        ->orderBy('descricao')->pluck('descricao','id')->prepend('Selecione', '');

        return View('setores.setor_form', compact('estados', 'cidades', 'bairros', 'ruas', 'classeitens', 'classeitem', 'operacoes', 'operacao', 'colaboradores', 'colaborador', 'inputColaboradoresList', 'inputColaboradoresListId', 'cep', 'nfoperacao'));
    }

    function store(SetorRequest $request, Setor $set)
    {
        $this->authorize('create', $set);
        DB::beginTransaction();
        try {

            $data = $this->dadosExtras($request->all());

            $setor = Setor::create($data);
            if ($data['inputColaboradoresListId'] !== '') {
                $setorColaborador['setor_id'] = $setor->id;
                $colaboradoresId = explode('||', $data['inputColaboradoresListId']);
                for ($i = 0; $i < count($colaboradoresId) - 1; $i++) {
                    $setorColaborador['colaborador_id'] = $colaboradoresId[$i];
                    Setorcolaboradores::create($setorColaborador);
                    $users = User::where('colaborador_id', $colaboradoresId[$i])->pluck('id');
                    $androids = Android::whereIn('user_id', $users)->get();
                    foreach ($androids as $android) {
                        if ($android->colaborador_id != $colaboradoresId[$i] || $android->setor_id != $setor->id) {
                            $android->colaborador_id = $colaboradoresId[$i];
                            $android->setor_id = $setor->id;
                            $android->save();
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/setor/create')
                ->withErrors($e->getMessage())
                ->withInput();
        }
        DB::commit();
        return \Redirect::route('setor.index')->withMessageSuccess("Setor cadastrado com sucesso!");
    }

    function show($id, Setor $set)
    {
        $this->authorize('view', $set);
        $setor = Setor::findOrFail($id);
        $this->authorize('igualdade', $setor);

        $estados = Estado::all()->pluck('descricao', 'uf');
        $estado = Estado::find($setor->{'uf'});
        if ($estado !== null)
            $cidades = $estado->cidades()->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orWhere('grupo_id', null)->orderBy('descricao')->pluck('descricao', 'id');
        else
            $cidades =  [];

        $cidade = Cidade::find($setor->{'cidade_id'});
        $bairros = Bairro::where([['cidade_id', $cidade->id], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao', 'id');
        $ruas = Rua::where([['cidade_id', $setor->{'cidade_id'}], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao', 'id');
        $operacoes = [];
        $operacao = '';
        $monitoramentocerca = '';
        $colaboradores = Colaborador::where([
            ['empresa_id', Session::get('empresa_padrao')->id],
            ['ativo', 1]
        ])->orderBy('nome')->pluck('nome', 'id');
        $colaboradoresList = Setorcolaboradores::where('setor_id', $id)->get();
        $inputColaboradoresListId = '';
        $inputColaboradoresList = '';
        foreach ($colaboradoresList as $value) {
            $inputColaboradoresListId .= $value->colaborador_id . "||";
            $inputColaboradoresList .= $value->colaborador_id . "||";
        }
        $nfoperacao = Nfoperacao::where(['empresa_id' => Session::get('empresa_padrao')->id])
        ->orderBy('descricao')->pluck('descricao','id')->prepend('Selecione', '');

        $show = true;
        return View('setores.setor_form', compact('show', 'setor', 'estados', 'cidades', 'bairros', 'ruas', 'operacoes', 'operacao', 'colaboradores', 'colaboradoresList', 'inputColaboradoresListId', 'inputColaboradoresList', 'nfoperacao'));
    }

    function edit($id, Setor $set)
    {
        $this->authorize('update', $set);
        $setor = Setor::findOrFail($id);
        $this->authorize('igualdade', $setor);
        $estados = Estado::all()->pluck('descricao', 'uf');
        $estado = Estado::find($setor->{'uf'});
        if ($estado !== null)
            $cidades = $estado->cidades()->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orWhere('grupo_id', null)->orderBy('descricao')->pluck('descricao', 'id');
        else
            $cidades =  [];

        $cidade = Cidade::find($setor->{'cidade_id'});
        $bairros = Bairro::where([['cidade_id', $cidade->id], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao', 'id');
        $ruas = Rua::where([['cidade_id', $setor->{'cidade_id'}], ['grupo_id', Session::get('empresa_padrao')->grupo_id]])->orderby('descricao')->pluck('descricao', 'id');
        $operacoes = [];
        $operacao = '';
        $monitoramentocerca = '';
        $colaboradores = Colaborador::where([
            ['empresa_id', Session::get('empresa_padrao')->id],
            ['ativo', 1]
        ])->orderBy('nome')->pluck('nome', 'id');
        $colaboradoresList = Setorcolaboradores::where('setor_id', $id)->get();
        $inputColaboradoresListId = '';
        $inputColaboradoresList = '';
        foreach ($colaboradoresList as $value) {
            $inputColaboradoresListId .= $value->colaborador_id . "||";
            $inputColaboradoresList .= $value->colaborador_id . "||";
        }
        $nfoperacao = Nfoperacao::where(['empresa_id' => Session::get('empresa_padrao')->id])
        ->orderBy('descricao')->pluck('descricao','id')->prepend('Selecione', '');
        return View('setores.setor_form', compact('setor', 'setor', 'estados', 'cidades', 'bairros', 'ruas', 'operacoes', 'operacao', 'colaboradores', 'colaboradoresList', 'inputColaboradoresListId', 'inputColaboradoresList', 'nfoperacao'));
    }

    function update(SetorRequest $request, $id, Setor $set)
    {
        $this->authorize('update', $set);
        $setor = Setor::findOrFail($id);
        $this->authorize('igualdade', $setor);
        DB::beginTransaction();
        try {

            $data = $this->dadosExtras($request->all(), $id);

            $setor->update($data);

            $setorColaboradores = Setorcolaboradores::where('setor_id', $setor->id)->get();
            $setorColaboradoresInsert = [];
            if ($data['inputColaboradoresListId'] !== '') {
                $colaboradoresId = explode('||', $data['inputColaboradoresListId']);
                foreach ($setorColaboradores as $setorcolaborador) {
                    if (!in_array($setorcolaborador->colaborador_id, $colaboradoresId)) {
                        $androids = Android::where('colaborador_id', $setorcolaborador->colaborador_id)
                            ->where('setor_id', $setor->id)->get();
                        foreach ($androids as $android) {
                            $android->setor_id = null;
                            $android->save();
                        }
                    }
                }
                $setor->setorcolaboradores()->delete();
                for ($i = 0; $i < count($colaboradoresId) - 1; $i++) {
                    $setorColaboradorInsert = new Setorcolaboradores();
                    $setorColaboradorInsert['setor_id'] = $setor->id;
                    $setorColaboradorInsert['colaborador_id'] = $colaboradoresId[$i];
                    array_push($setorColaboradoresInsert, $setorColaboradorInsert);
                    $users = User::where('colaborador_id', $colaboradoresId[$i])->pluck('id');
                    $androids = Android::whereIn('user_id', $users)->get();
                    foreach ($androids as $android) {
                        if ($android->colaborador_id != $colaboradoresId[$i] || $android->setor_id != $setor->id) {
                            $android->colaborador_id = $colaboradoresId[$i];
                            $android->setor_id = $setor->id;
                            $android->save();
                        }
                    }
                }
                $setor->setorcolaboradores()->saveMany($setorColaboradoresInsert);
            } else if (count($setorColaboradores) > 0) {
                foreach ($setorColaboradores as $setorcolaborador) {
                    $androids = Android::where('colaborador_id', $setorcolaborador->colaborador_id)
                        ->where('setor_id', $setor->id)->get();
                    foreach ($androids as $android) {
                        $android->setor_id = null;
                        $android->save();
                    }
                }
                $setor->setorcolaboradores()->delete();
            }
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/setor/' . $id . '/edit/')
                ->withErrors($e->getMessage())
                ->withInput();
        }
        DB::commit();
        return \Redirect::route('setor.index')->withMessageSuccess("Setor atualizado com sucesso!");
    }

    function destroy($id, Setor $set)
    {
        $this->authorize('delete', $set);
        $setor = Setor::find($id);
        $this->authorize('igualdade', $setor);
        DB::beginTransaction();
        try {
            $setor->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    private function dadosExtras($data, $id = null)
    {
        $data['usarastreamento'] = isset($data['usarastreamento']);
        $data['grupo_id'] = Session::get('empresa_padrao')->grupo_id;
        $data['empresa_id'] = Session::get('empresa_padrao')->id;

        if (starts_with($data["rua_id"], "N")) $this->createRua($data);

        $latLong = buscaLatitudeLongitude($data['uf'], $data['cidade_id'], $data['bairro_id'], $data['rua_id'], $data['numero']);

        $data['latitude'] = $latLong->location->lat;
        $data['longitude'] = $latLong->location->lng;

        $data['ativo'] = !isset($data['ativo']) ? 0 : 1;

        if ($data['ativo'] === 0 && !is_null($id)) {
            $estoquesetor = Estoquesetor::where('setor_id', $id)->get();
            foreach ($estoquesetor as $estoque) {
                if ($estoque->quantidade != 0)
                    throw new \Exception("Este setor não pode ser inativado pois contém produtos no estoque!");
            }
        }

        return $data;
    }

    private function createRua(&$data)
    {

        $ruaCon = new RuaController();
        $rua = $ruaCon->createRuaFromOther($data);

        $data["rua_id"] = $rua->id;
    }
}
