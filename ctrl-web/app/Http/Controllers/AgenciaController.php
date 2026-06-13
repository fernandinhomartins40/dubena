<?php

namespace App\Http\Controllers;

use Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use App\Http\Controllers\Controller;
use App\Http\Requests;
use DB;
use Redirect;
use App\EmpresasGrupo;
use App\Agencia;
use App\Banco;
use App\Estado;
use App\Telefonetipo;
use App\Cidade;
use App\Agenciatelefone;

class AgenciaController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $agencias = Agencia::where([
            ['grupo_id', Session::get('empresa_padrao')->grupo_id],
            ])->get();
        return view('agencias.agencias', compact('agencias'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $cidades = [];
        $cidadesN = [];
        $estados = Estado::all()->pluck('descricao', 'uf');
        $estados1 = Estado::all()->pluck('uf', 'uf');
        $bairros = [];
        $ruas = [];
        $telefonetipos = Telefonetipo::where([
            ['ativo', true],
            ])->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $bancos = Banco::where([
            ['ativo', true],
            ])->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');

        return view('agencias.agencia_form', compact('cidades', 'cidadesN', 'estados', 'estados1', 'bairros', 'telefonetipos', 'bancos', 'ruas'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'descricao' => 'required|unique:agencias,descricao,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'agencia' => 'required|unique:agencias,agencia,NULL,id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'postobeneficiario' => 'numeric',
            'agenciadigito' => 'required|numeric',
            'cidade_id' => 'required|numeric',
            'bairro_id' => 'required',
            'email' => 'email'
            ]);
        $data = $request->only('id', 'grupo_id', 'banco_id', 'cidade_id', 'bairro_id', 'agencia', 'agenciadigito', 'postobeneficiario', 'descricao', 'ativo', 'rua_id', 'numero', 'complemento', 'email', 'cep', 'uf', 'pontoreferencia');
        //$data["user_id"] = \Auth::user()->id;
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        DB::beginTransaction();
        try {

            $agencia = Agencia::create($data);

//        dd($agencia);
            $fones = [];
            if ($request->only('telefones')["telefones"] != null) {
                $telefones = json_decode($request->only('telefones')["telefones"]);
                foreach ($telefones as $telefone) {
                    $fone = new Agenciatelefone();
                    $fone["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $fone["empresa_id"] = Session::get('empresa_padrao')->id;
                    $fone["telefonetipo_id"] = $telefone[0];
                    $fone["telefone"] = $telefone[2];
                    array_push($fones, $fone);
                }
            }
            $agencia->telefones()->delete();
            $agencia->telefones()->saveMany($fones);
        } catch (ValidationException $e) {
            DB::rollback();
            return Redirect::to('/agencia/create')
            ->withErrors($e->getMessage())
            ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/agencia/create')
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        return \Redirect::route('agencia.index')->withMessageSuccess('Agência cadastrada com sucesso!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $agencia = Agencia::find($id);
        // dd($agencia->telefones);
        $estados = Estado::all()->pluck('descricao', 'uf');
        $cidade = Cidade::find($agencia->{'cidade_id'});
        $cidades = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('uf', $cidade->{'uf'})->orderby('descricao')->pluck('descricao', 'id');
        $cidades1 = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderby('descricao')->pluck('descricao', 'id');
        $bairros = $cidade->bairros()->pluck('descricao', 'id');
        $cidadesN = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('uf', $agencia->cidadenatal_uf)->orderby('descricao')->pluck('descricao', 'id');
        $ruas = $cidade->ruas()->pluck('descricao', 'id');
        $telefonetipos = Telefonetipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $bancos = Banco::where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id');
        $show = true;
        return view('agencias.agencia_form', compact('agencia','show', 'estados', 'cidade', 'cidades', 'cidades1', 'bairros', 'cidadesN', 'telefonetipos', 'bancos', 'ruas'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
       
        $agencia = Agencia::find($id);
        // dd($agencia->telefones);
        $estados = Estado::all()->pluck('descricao', 'uf');
        $cidade = Cidade::find($agencia->{'cidade_id'});
        $cidades = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('uf', $cidade->{'uf'})->orderby('descricao')->pluck('descricao', 'id');
        $cidades1 = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderby('descricao')->pluck('descricao', 'id');
        $bairros = $cidade->bairros()->pluck('descricao', 'id');
        $cidadesN = Cidade::where('grupo_id', Session::get('empresa_padrao')->grupo_id)->where('uf', $agencia->cidadenatal_uf)->orderby('descricao')->pluck('descricao', 'id');
        $ruas = $cidade->ruas()->pluck('descricao', 'id');
        $telefonetipos = Telefonetipo::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->orderBy('descricao')->pluck('descricao', 'id');
        $bancos = Banco::where('ativo', true)->orderBy('descricao')->pluck('descricao', 'id');
        return view('agencias.agencia_form', compact('agencia', 'estados', 'cidade', 'cidades', 'cidades1', 'bairros', 'cidadesN', 'telefonetipos', 'bancos', 'ruas'));
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'cidade_id' => 'required|numeric',
            'bairro_id' => 'required',
            'descricao' => 'required|unique:agencias,descricao,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'agencia' => 'required|unique:agencias,agencia,' . $id . ',id,grupo_id,' . Session::get('empresa_padrao')->grupo_id,
            'postobeneficiario' => 'numeric',
            'agenciadigito' => 'required|numeric',
            'email' => 'email'
            ]);
        $data = $request->only('id', 'grupo_id', 'banco_id', 'cidade_id', 'bairro_id', 'agencia', 'agenciadigito', 'postobeneficiario', 'descricao', 'ativo', 'rua_id', 'numero', 'complemento', 'email', 'cep', 'uf', 'pontoreferencia');
        //$data["user_id"] = \Auth::user()->id;
        $data["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $agencia = Agencia::findOrFail($id);
        DB::beginTransaction();
        try {

            $agencia->update($data);

            $fones = [];
            if ($request->only('telefones')["telefones"] != null) {
                $telefones = json_decode($request->only('telefones')["telefones"]);
                foreach ($telefones as $telefone) {
                    $fone = new Agenciatelefone();
                    $fone["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
                    $fone["empresa_id"] = Session::get('empresa_padrao')->id;
                    $fone["telefonetipo_id"] = $telefone[0];
                    $fone["telefone"] = $telefone[2];
                    array_push($fones, $fone);
                }
            }
            $agencia->telefones()->delete();
            $agencia->telefones()->saveMany($fones);
        } catch (ValidationException $e) {
            DB::rollback();
            return Redirect::to('/agencia/' . $id)
            ->withErrors($e->getMessage())
            ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/agencia/' . $id)
            ->withErrors($e->getMessage())
            ->withInput();
        }
        DB::commit();
        return \Redirect::route('agencia.index')->withMessageSuccess('Agência atualizada com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            Agencia::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
    }

}
