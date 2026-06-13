<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Redirect;
use App\User;
use App\Setor;
use App\Android;
use App\Colaborador;
use App\Setorcolaboradores;
use Illuminate\Http\Request;

class AndroidController extends Controller
{
    protected $msgsValidacao =  array(
        'descricao.required' => 'O campo Descrição é obrigatório.'
    );

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Android $and)
    {
        $this->authorize('view', $and);
        $androids = Android::where('empresa_id', Session::get('empresa_padrao')->id)->get();
        return view('androids.androids', compact('androids'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    public function register(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Android $and)
    {
        $this->authorize('view', $and);
        $android = Android::find($id);
        $this->authorize('igualdade', $android);
        $colaboradors = Colaborador::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->pluck('nome', 'id')->prepend('Selecione', '');
        $setors = Setor::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->pluck('descricao', 'id')->prepend('Selecione', '');
        $users = User::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->pluck('name', 'id')->prepend('Selecione', '');
        $show = true;

        return view('androids.android_form', compact('android', 'colaboradors', 'setors', 'users', 'show'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Android $and)
    {
        $this->authorize('update', $and);
        $android = Android::find($id);
        $this->authorize('igualdade', $android);
        $colaboradors = Colaborador::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->pluck('nome', 'id')->prepend('Selecione', '');
        $setors = Setor::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->pluck('descricao', 'id')->prepend('Selecione', '');
        $users = User::where('ativo', true)->where('empresa_id', Session::get('empresa_padrao')->id)->pluck('name', 'id')->prepend('Selecione', '');


        return view('androids.android_form', compact('android', 'colaboradors', 'setors', 'users'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, Android $and)
    {
        $this->authorize('update', $and);
        $android = Android::find($id);
        $this->authorize('igualdade', $android);
        $this->validate($request, [
            'descricao' => 'required',
        ], $this->msgsValidacao);
        DB::beginTransaction();
        try {
            $data = $request->only('id', 'androidid', 'descricao', 'colaborador_id', 'user_id', 'setor_id', 'ativo');
            $android->update($data);
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::to('/android/' . $id . '/edit')->withErrors($e->getMessage())->withInput();
        } catch (\ValidationException $e) {
            DB::rollback();
            return \Redirect::to('/android/' . $id . '/edit')->withErrors($e->getMessage())->withInput();
        }
        DB::commit();
        return \Redirect::route('android.index')->withMessageSuccess('Registro atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Android $and)
    {
        $this->authorize('delete', $and);
        $android = Android::find($id);
        $this->authorize('igualdade', $android);
        DB::beginTransaction();
        try {
            $android->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    public function getAndroidData($id)
    {
        $android = Android::find($id);
        if ($android == null) {
            return ["status" => 'OK', "colaborador_id" => '', "setor_id" => '', "user_id" => ''];
        }
        if ($android->user == null) {
            return ["status" => 'OK', "colaborador_id" => '', "setor_id" => '', "user_id" => ''];
        }
        $colaborador = Colaborador::find($android->user->colaborador_id);
        if ($colaborador == null) {
            return ["status" => 'OK', "colaborador_id" => '', "setor_id" => '', "user_id" => $android->user->id];
        }
        $setores = Setorcolaboradores::where('colaborador_id', $colaborador->id)->get();
        foreach ($setores as $setor) {
            return ["status" => 'OK', "colaborador_id" => $colaborador->id, "setor_id" => $setor->setor_id, "user_id" => $android->user->id];
        }
        return ["status" => 'OK', "colaborador_id" => $colaborador->id, "setor_id" => '', "user_id" => $android->user->id];
    }

    public function testAndroidNotify($id)
    {
        $api = new ApiController();
        $response = $api->sendNotificacaoMovelTeste(null, $id);
        $data = $response->data;
        return ["status" => $data->fcm_response->failure <= 0 ? 'OK' : 'NOK', "message" => "Sucesso"];
    }
}
