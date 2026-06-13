<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\User;
use App\Role;
use App\Empresa;
use Illuminate\Http\Request;

class RoleController extends Controller
{

    protected $rules = [
        "descricao" => "required",
        "tipo_id"   => "required",
    ];

    protected $msg = [
        "descricao.required"    => "O campo Descrição é obrigatório.",
        "tipo_id.required"      => "O campo Tipo é obrigatório.",
    ];
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = Role::all();
        return view('users.roles.index')->with(compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return redirect('roles');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $this->dadosExtras($request);
        DB::beginTransaction();
        try {
            $role = Role::create($data);
        } catch (\Exception $ex) {
            DB::rollback();
            $error = getErrorsException($ex);
            return $error;
        }
        DB::commit();
        return "OK|";

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return redirect('roles');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return redirect('roles');
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
        $data = $this->dadosExtras($request);
        $role = Role::find($id);

        DB::beginTransaction();
        try {
            $up = $role->update($data);
        } catch (\Exception $ex) {
            DB::rollback();
            $error = getErrorsException($ex);
            return $error;
        }
        DB::commit();
        return 'OK|';
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $role = Role::find($id);

        DB::beginTransaction();
        try {
            $role->delete();
            DB::commit();
        } catch (\Exception $ex) {
            DB::rollback();
            return "<br /><br />O registro está sendo usado, então não poderá ser deletado.";
        }
        return "OK|";
    }

    /**
     * Validate and treat the data brought from the view
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return array                     $data
     */
    private function dadosExtras($request)
    {
        $this->validate($request, $this->rules, $this->msg);
        $data = $request->all();

        $data["ativo"] = isset($data["ativo"]) && $data["ativo"];
        $data["aparece_user"] = isset($data["aparece_user"]) && $data["aparece_user"];

        return $data;
    }

    public function definicao(Role $rol)
    {
        $this->authorize('definirView', $rol);
        $empresa_id = Session::get('empresa_padrao')->id;
        $id = \Auth::user()->id;
        $users = Empresa::where('id', $empresa_id)->first()->users()
            ->where([
                ['users.support',0],
                ['users.id', '!=', $id]
            ])
            ->orderBy('name')
            ->pluck('name', 'id')
            ->prepend('Selecione', '');
        $tipos = Role::where('aparece_user', 1)->pluck('descricao', 'id')->prepend('Selecione', '');

        return view('users.roles.definicao_role')->with(compact('users', 'tipos'));
    }

    public function definicaoStore(Request $request)
    {
        $rules = [
            "user_id" => "required",
            "tipo_id" => "required",
        ];
        $msg = [
            "user_id.required" => "O campo Usuário é obrigatório.",
            "tipo_id.required" => "O campo Tipo é obrigatório.",
        ];
        $this->validate($request, $rules, $msg);
        $data = $request->all();
        $user = User::find($data["user_id"]);
        DB::beginTransaction();
        try {
            $user->update(["tipo_id" => $data["tipo_id"]]);
        } catch (\Exception $ex) {
            DB::rollback();
            $error = getErrorsException($ex);
            return redirect('definirtipos')
                ->withErrors($error)
                ->withInput();
        }
        DB::commit();
        return redirect('definirtipos')->withMessageSuccess("Cadastro do usuário atualizado");
    }
}
