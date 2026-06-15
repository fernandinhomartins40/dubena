<?php

namespace App\Http\Controllers;

use DB;
use Hash;
use Session;
use App\User;
use App\Role;
use App\Menu;
use Exception;
use App\Android;
use App\Menuuser;
use App\Colaborador;
use App\EmpresasGrupo;
use App\Setorcolaboradores;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;

class UsersController extends Controller
{
    protected $msgsValidacao =  [
        'name.required'         => 'O campo Nome é obrigatório.',
        'name.unique'           => 'O campo Nome já está em uso.',
        'email.required'        => 'O campo Usuário é obrigatório.',
        'email.min'             => 'Usuário deve ter no mínimo 5 caracteres.',
        'email.unique'          => 'Usuário já está em uso.',
        'password.required'     => 'O campo Senha é obrigatório.',
        'password.confirmed'    => 'A confirmação de Senha não confere.',
        'tipo_id.required'      => 'O campo Tipo de Usuário é obrigatório.',
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(User $user)
    {
        $users = User::all();
        $this->authorize('view', $user);
        return view('users.users', compact('users'));
    }
    public function indexchangepassword()
    {
        $User = \Auth::user();
        return view('users.changepassword', compact('User'));
    }

    public function create(User $user)
    {
        $this->authorize('create', $user);
        $financeiros = $this->getFinanceiros();
        $alertas = $this->getAlertas();
        $grupos = Empresasgrupo::all()->pluck('descricao', 'id')->prepend('Selecione', '');
        $empresaslnk = [];
        $menus = Menu::whereRaw('descricao is not null')->orderBy('id')->get();
        $menuslnk = [];
        $colaboradors = Colaborador::where('ativo', true)->where('grupo_id', Session::get('empresa_padrao')->grupo_id)->pluck('nome', 'id')->prepend('Selecione', '');
        $tipo = Role::all()->pluck('descricao', 'id')->prepend('Selecione', '');
        return view('users.user_form', compact('grupos', 'empresaslnk', 'financeiros', 'menus', 'menuslnk', 'colaboradors', 'tipo', 'alertas'));
    }

    public function store(Request $request, User $user)
    {
        $this->authorize('create', $user);

        DB::beginTransaction();
        try {
            $data = $this->dadosExtras($request);

            $this->oauthClient($data, $user);

            $data['support'] = isset($data["support"]) && $data["support"] == '1';

            $data['password'] = bcrypt($data['password']);

            $user = User::create($data);

            $this->criarPermissoes($data["empresapermission"], $user);

            $empresas = collect(json_decode($data["empresas_list"]));
            $empresas_id = $empresas->pluck('empresa_id')->toArray();
            $user->empresas()->attach($empresas_id);
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::to('/user/create')
                ->withErrors($e->getMessage())
                ->withInput();
        }
        DB::commit();
        return \Redirect::route('user.index')->withMessageSuccess('Usuário cadastrado com sucesso!');
    }

    public function show($id)
    {
        return $this->showEdit($id);
    }

    public function edit($id)
    {
        return $this->showEdit($id, false);
    }

    public function update(Request $request, $id, User $u)
    {
        $this->authorize('update', $u);
        $data = $this->dadosExtras($request, $id);
        DB::beginTransaction();
        try {
            $empresas = collect(json_decode($request->input('empresas_list')));
            $empresas_id = $empresas->pluck('empresa_id')->toArray();

            $user = User::findOrFail($id);
            $this->compareCall(collect($data), $user);

            $this->oauthClient($data, $user);

            if ($data['password'] != '') {
                $data['password'] = bcrypt($data['password']);
            } else {
                $data['password'] = $user->{'password'};
            }

            $user->update($data);
            $user->menuuser()->delete();
            $this->criarPermissoes($request->input('empresapermission'), $user);
            $user->empresas()->sync($empresas_id);

            $androids = Android::where('user_id', $user->id)->get();
            foreach ($androids as $android) {
                $setor_id = null;

                $setorColaboradores = Setorcolaboradores::where('colaborador_id', $data["colaborador_id"])->get();
                if (count($setorColaboradores) > 0) {
                    $setor_id = $setorColaboradores->first()->setor_id;
                }
                if ($android->colaborador_id != $data["colaborador_id"] || $android->setor_id != $setor_id) {
                    $android->colaborador_id = $data["colaborador_id"];
                    $android->setor_id = $setor_id;
                    $android->save();
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::to('/user/' . $id . '/edit')->withErrors($e->getMessage())->withInput();
        }
        DB::commit();
        return \Redirect::route('user.index')->withMessageSuccess('Usuário atualizado com sucesso!');
    }

    public function updatepassword(Request $request, $id)
    {
        $this->validate($request, [
            'password_old' => 'required',
            'password' => 'required|confirmed'
        ]);
        $User = User::findOrFail($id);
        $this->authorize('igualdade', $User);
        $password_old = $request->only('password_old')["password_old"];
        if (!Hash::check($password_old, $User->password)) {
            $errors = new MessageBag(['password' => ['Senha atual não confere.']]);
            return back()->withErrors($errors)->withInput();
        }
        $data = $request->only('password');
        $senha = $data['password'];
        $data['password'] = bcrypt($data['password']);
        $User->update($data);
        return \Redirect::route('home');
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, User $usuario)
    {
        $this->authorize('delete', $usuario);
        DB::beginTransaction();
        try {
            User::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';
    }

    public function home()
    {
        return view('users.home');
    }

    private function showEdit($id, $show = true)
    {
        $user = User::find($id);
        $show ? $this->authorize('view', $user) : $this->authorize('update', $user);
        $financeiros = $this->getFinanceiros();
        $alertas = $this->getAlertas();
        $grupos = Empresasgrupo::all()->pluck('descricao', 'id')->prepend('Selecione', '');
        $empresas_user = $user->empresas()->select('id', 'nome_informal')->get();
        $grupo_user = $user->empresa->empresasGrupo()->get()->first()->id;
        $menus = Menu::whereRaw('descricao is not null')->orderBy('id')->get();
        $colaboradors = Colaborador::where('ativo', true)->pluck('nome', 'id');
        $oauthClient = \App\Oauthclient::where('user_id', $user->id)->get()->first();
        $menus_permitidos = $this->getMenusUser($user);
        $tipo = Role::all()->pluck('descricao', 'id')->prepend('Selecione', '');

        if ($show) {
            return view('users.user_form', compact(
                'oauthClient',
                'user',
                'grupos',
                'financeiros',
                'alertas',
                'empresas_user',
                'grupo_user',
                'menus',
                'colaboradors',
                'menus_permitidos',
                'tipo',
                'show'
            ));
        } else {
            $edit = true;
            return view('users.user_form', compact(
                'oauthClient',
                'user',
                'grupos',
                'financeiros',
                'alertas',
                'empresas_user',
                'grupo_user',
                'menus',
                'colaboradors',
                'menus_permitidos',
                'tipo',
                'edit'
            ));
        }
    }

    private function criarPermissoes($permissions, $user)
    {
        $menus = collect([]);
        // Oracle START WITH ... CONNECT BY prior parent_id = id: sobe a árvore
        // de menus (dos ids dados até as raízes, via parent_id). Postgres:
        // WITH RECURSIVE ascendente por parent_id.
        $recursive_query = "WITH RECURSIVE anc AS ( " .
            "  SELECT id, parent_id, descricao FROM menus WHERE id in (:id) " .
            "  UNION ALL " .
            "  SELECT m.id, m.parent_id, m.descricao FROM menus m " .
            "    JOIN anc ON m.id = anc.parent_id " .
            ") " .
            "SELECT id FROM anc WHERE descricao IS NULL GROUP BY id";

        DB::beginTransaction();
        try {
            foreach ($permissions as $i => $per) {
                $model_menu = new Menuuser();
                $menu = json_decode($per);
                $col_menu = collect([]);
                foreach ($menu as $men) {
                    $objmenu = new Menuuser();
                    $objmenu->user_id = $user->id;
                    $objmenu->menu_id = $men->menu_id;
                    $objmenu->empresa_id = $men->empresa_id;
                    $objmenu->visualizar = $men->visualizar;
                    $objmenu->criar = $men->criar;
                    $objmenu->editar = $men->editar;
                    $objmenu->baixar = $men->baixar;
                    $objmenu->alerta = $men->alerta;
                    $objmenu->deletar = $men->deletar;
                    $col_menu->push($objmenu);
                }
                $ids = implode(',', $col_menu->pluck('menu_id')->toArray());
                $empresa_id = $col_menu->pluck('empresa_id')->first();
                $query = str_replace(':id', $ids, $recursive_query);
                $root = DB::select($query);
                foreach ($root as $pai) {
                    $objmenu = new Menuuser();
                    $objmenu->user_id = $user->id;
                    $objmenu->menu_id = $pai->id;
                    $objmenu->empresa_id = $empresa_id;
                    $objmenu->visualizar = 1;
                    $objmenu->criar = 0;
                    $objmenu->editar = 0;
                    $objmenu->baixar = 0;
                    $objmenu->alerta = 0;
                    $objmenu->deletar = 0;
                    $col_menu->push($objmenu);
                }
                $user->menuuser()->saveMany($col_menu);
                $menus->push($col_menu);
            }
        } catch (Exception $e) {
            DB::rollback();
            return false;
        }
        DB::commit();
        return true;
    }

    private function getMenusUser($user)
    {
        $menu = DB::table('menuusers as mnu')->join('users', 'mnu.user_id', 'users.id')
            ->join('menus', 'mnu.menu_id', 'menus.id')
            ->where('users.id', $user->id)
            ->whereRaw('menus.descricao is not null')
            ->select(
                'mnu.user_id as user_id',
                'menus.parent_id',
                'menus.titulo',
                'mnu.empresa_id',
                'mnu.visualizar',
                'mnu.criar',
                'mnu.editar',
                'mnu.deletar',
                'menus.id as menu_id',
                'mnu.alerta',
                'mnu.baixar'
            )
            ->orderBy('menus.id')->get();
        return $menu;
    }

    /**
     * Return json with menus that use financial scheme
     *
     * @return json $financeiros
     */
    private function getFinanceiros()
    {
        // Oracle START WITH id=(SUBQ) CONNECT BY prior id = parent_id: desce a
        // árvore de menus a partir do menu "Financeiros". Postgres: WITH RECURSIVE.
        $query = "WITH RECURSIVE desce AS ( " .
            "  SELECT id, parent_id, descricao FROM menus " .
            "  WHERE id = (select id from menus where descricao is null and lower(titulo) like 'financeiros' and parent_id is null) " .
            "  UNION ALL " .
            "  SELECT m.id, m.parent_id, m.descricao FROM menus m JOIN desce ON m.parent_id = desce.id " .
            ") SELECT id FROM desce WHERE descricao is not null";

        return json_encode(DB::select($query));
    }

    /**
     * Return JSON with menus that use notification
     *
     * @return json $alertas
     */
    private function getAlertas()
    {
        $arr_alertas = [
            "pedido.index",
            "veiculotrocaoleo.index",
            "checklist.index",
            "veiculopneu.index",
            "veiculo.index",
            "colaborador.index",
            "inconsistencia.index",
            "report.logcercas"
        ];

        return json_encode(Menu::whereIn("descricao", $arr_alertas)->select('id', 'descricao')->get());
    }

    /**
     * Compare the old and new data and check if the
     * user changed especific columns for call center
     *
     * @return boolean
     */
    private function compareCall($data, $user)
    {
        $change = $data->only("usaandroid", "identificadorchamada", "usarastreamento");
        $track = collect($user)->only("usaandroid", "identificadorchamada", "usarastreamento");

        $changed = false;

        if ($change["usaandroid"] == "1" && !!$change["usaandroid"] != !!$track["usaandroid"]) {
            $changed = true;
        }

        if ($change["identificadorchamada"] == "1" && !!$change["identificadorchamada"] != !!$track["identificadorchamada"]) {
            $changed = true;
        }

        if ($change["usarastreamento"] == "1" && !!$change["usarastreamento"] != !!$track["usarastreamento"]) {
            $changed = true;
        }

        if ($changed && ! $data["password"]) {
            throw new \Exception("Para mudanças nos dados para o Call Center é obrigatório informar a senha.");
        }

        return true;
    }

    private function oauthClient($data, $user)
    {
        $senha = $data['password'];

        $oauth_clients = \App\Oauthclient::where('user_id', $user->id)->get();
        if (($data['usaandroid'] !== null || $data['identificadorchamada'] !== null
            || $data['usarastreamento'] !== null) && count($oauth_clients) === 0) {
            if ($senha != '') {
                $oauth_client = new \App\Oauthclient();
                $oauth_client->user_id = $user->id;
                $oauth_client->name = $user->email;
                $oauth_client->secret = base64_encode(hash_hmac('sha256', $senha, env('OAUTH_CLIENT_HMAC_KEY', 'secret'), true));
                $oauth_client->password_client = 1;
                $oauth_client->personal_access_client = 0;
                $oauth_client->redirect = 'null';
                $oauth_client->revoked = 0;
                $oauth_client->save();
            } else {
                throw new \Exception("Informe sua senha para prosseguir");
            }
        } else if (($data['usaandroid'] !== null || $data['identificadorchamada'] !== null || $data['usarastreamento'] !== null)) {
            foreach ($oauth_clients as $cli) {
                $cli->secret = base64_encode(hash_hmac('sha256', $senha, env('OAUTH_CLIENT_HMAC_KEY', 'secret'), true));
                $cli->save();
            }
        }
        if (($data['usaandroid'] === null && $data['identificadorchamada'] === null  && $data['usarastreamento'] === null)) {
            foreach ($oauth_clients as $client) {
                $client->delete();
            }
        }
    }

    private function dadosExtras($request, $id = null)
    {
        $arr_rules = [
            'email'             => 'required|min:5',
            'password'          => 'confirmed',
            'empresa_padrao'    => 'empresa_exists',
            'tipo_id'           => 'required',
        ];

        if (!is_null($id)) {
            $arr_rules = array_merge($arr_rules, [
                'name'          => 'required|min:5|unique:users,name,' . $id . ',id,empresa_id,' . Session::get('empresa_padrao')->id
            ]);

            $data = $request->only(
                'id',
                'name',
                'email',
                'password',
                'empresa_id',
                'colaborador_id',
                'support',
                'ativo',
                'usaandroid',
                'identificadorchamada',
                'usarastreamento',
                'impressoratermica',
                'tipo_id'
            );
        } else {
            $arr_rules = array_merge($arr_rules, [
                'name'          => 'required|min:5|unique:users,name,null,id,empresa_id,' . Session::get('empresa_padrao')->id
            ]);
            $data = $request->all();
            $data["usaandroid"] = isset($data["usaandroid"]) && $data["usaandroid"];
            $data["identificadorchamada"] = isset($data["identificadorchamada"]) && $data["identificadorchamada"];
            $data["usarastreamento"] = isset($data["usarastreamento"]) && $data["usarastreamento"];
            $data["impressoratermica"] = isset($data["impressoratermica"]) && $data["impressoratermica"];
        }

        $this->validate($request, $arr_rules, $this->msgsValidacao);
        $data['support'] = isset($data["support"]) && $data["support"] == '1';

        return $data;
    }
}
