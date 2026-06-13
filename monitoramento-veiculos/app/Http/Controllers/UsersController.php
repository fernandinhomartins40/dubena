<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\User;
use App\Empresa;
use App\Menu;
use Illuminate\Support\Facades\Input;
use Image;
use Session;
use Illuminate\Support\MessageBag;
use Hash;
use DB;
use Redirect;

class UsersController extends Controller
{

    protected $msgsValidacao = array(
        'name.required' => 'O campo Nome é obrigatório.',
        'name.unique' => 'O campo Nome já está em uso.',
        'email.required' => 'O campo Usuário é obrigatório.',
        'email.min' => 'Usuário deve ter no mínimo 5 caracteres.',
        'email.unique' => 'Usuário já está em uso.',
        'password.required' => 'O campo Senha é obrigatório.',
        'password.confirmed' => 'A confirmação de Senha não confere.');

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::all();
        return view('users.users', compact('users'));
    }

    public function indexchangepassword()
    {
        $User = \Auth::user();
        return view('users.changepassword', compact('User'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $empresas = Empresa::all()->pluck('nome_informal', 'id');
        $empresaslnk = [];
        $menus = Menu::menuspermissoesAll();
        $menuslnk = [];

        return view('users.user_form', compact('empresas', 'empresaslnk', 'menus', 'menuslnk'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        //$this->validate($request, User::$login_validation_rules);
        $empresas = $request->input('empresas_list');
        if ($empresas == null) {
            $request->request->add(['empresa_padrao' => false]);
        } else {
            $request->request->add(['empresa_padrao' => in_array($request->input('empresa_id'), $empresas)]);
        }
        $this->validate($request, [
            'name' => 'required|min:5|unique:users,name,null,id,empresa_id,' . Session::get('empresa_padrao')->id,
            'email' => 'required|min:5|unique:users',
            'password' => 'required|min:4|confirmed',
            'empresa_padrao' => 'empresa_exists'
                ], $this->msgsValidacao);
        DB::beginTransaction();
        try {
            //$menus = $request->input('menus_list');
            $menus = $array = json_decode($request->input('inputMenus'));

            $data = $request->only('id', 'name', 'email', 'password', 'empresa_id', 'ativo', 'client_id', 'support');
            $senha = $data['password'];
            try {
                $token = buscarAccessToken($senha, $data['email'], '', 'password', $data['client_id']);
                $data['access_token'] = $token;
            } catch (\Exception $ex) {
                return Redirect::to('/user/create')
                                ->withErrors($ex->getMessage())
                                ->withInput();
            }
            $data['password'] = bcrypt($data['password']);
            $file = Input::file('foto');
            if ($file != null) {
                //$img = Image::make($file);
                $conteudo = file_get_contents($file->getPathName());
                $conteudo = base64_encode($conteudo);
                //$conteudo = mysql_real_escape_string($conteudo);
                $data["foto"] = $conteudo;
            }
            $data = $this->validateUserSupport($data);
            $user = User::create($data);
            $user->empresas()->attach($empresas);
            $user->menus()->attach($menus);
        } catch (\Exception $e) {
            DB::rollback();
            return Redirect::to('/user/create')
                            ->withErrors($e->getMessage())
                            ->withInput();
        } catch (\ValidationException $e) {
            DB::rollback();
            return Redirect::to('/user/create')
                            ->withErrors($e->getMessage())
                            ->withInput();
        }
        DB::commit();
        return \Redirect::route('user.index')->withMessageSuccess('Usuário cadastrado com sucesso!');
    }
    
    public function validateUserSupport($data)
    {
        if (\Auth::user()->support)
            $data['support'] = isset($data['support']) && $data['support'] == "on";
        return $data;
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::find($id);
        $empresas = Empresa::all()->pluck('nome_informal', 'id');
        $empresaslnk = $user->empresas->pluck('id')->toArray();
        $menus = Menu::menuspermissoes($id);
        $menuslnk = $user->menus->pluck('id')->toArray();
        $show = true;
        return view('users.user_form', compact('oauthClient', 'user', 'empresas', 'empresaslnk', 'menus', 'menuslnk', 'show'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::find($id);
        $empresas = Empresa::all()->pluck('nome_informal', 'id');
        $empresaslnk = $user->empresas->pluck('id')->toArray();
        $menus = Menu::menuspermissoes($id);
        $menuslnk = $user->menus->pluck('id')->toArray();

        return view('users.user_form', compact('user', 'empresas', 'empresaslnk', 'menus', 'menuslnk'));
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
            'name' => 'required|min:5|unique:users,name,' . $id . ',id,empresa_id,' . Session::get('empresa_padrao')->id,
            'email' => 'required|min:5',
            'password' => 'confirmed',
            'empresa_padrao' => 'empresa_exists'
                ], $this->msgsValidacao);

        DB::beginTransaction();
        try {

            $empresas = $request->input('empresas_list');
            $menus = $array = json_decode($request->input('inputMenus'));

            if ($empresas == null) {
                $request->request->add(['empresa_padrao' => false]);
            } else {
                $request->request->add(['empresa_padrao' => in_array($request->input('empresa_id'), $empresas)]);
            }
            $data = $request->only('id', 'name', 'email', 'password', 'empresa_id', 'ativo', 'client_id', 'support');

            $user = User::findOrFail($id);
            $senha = $data['password'];
            if ($data['password'] != '') {
                $data['password'] = bcrypt($data['password']);
                $token = buscarAccessToken($senha, $data['email'], '', 'password', $data['client_id']);
                $data['access_token'] = $token;
            } else {
                $data['password'] = $user->{'password'};
            }
            $captura = $request->only('foto_capture');
            if ($captura != '' && $captura["foto_capture"] != '') {
                $img = Image::make($captura['foto_capture'])->crop(200, 200);
                $conteudo = (string) $img->encode('data-url');
                $conteudo = str_replace('data:image/png;base64,', '', $conteudo);
                $conteudo = str_replace(' ', '+', $conteudo);
                $data["foto"] = base64_decode($conteudo);
            }
            $file = Input::file('foto');
            if ($file != null) {
                $conteudo = file_get_contents($file->getPathName());
                $conteudo = base64_encode($conteudo);
                $data["foto"] = $conteudo;
            }
            $data = $this->validateUserSupport($data);
            $user->update($data);
            $user->empresas()->sync($empresas);
            $user->menus()->sync($menus);
        } catch (\Exception $e) {
            DB::rollback();
            return \Redirect::to('/user/' . $id . '/edit')->withErrors($e->getMessage())->withInput();
        } catch (\ValidationException $e) {
            DB::rollback();
            return \Redirect::to('/user/' . $id . '/edit')->withErrors($e->getMessage())->withInput();
        }
        DB::commit();
        return \Redirect::route('user.index')->withMessageSuccess('Usuário atualizado com sucesso!');
    }

    public function updatepassword(Request $request, $id)
    {
        try {
            $this->validate($request, [
                'password_old' => 'required',
                'password' => 'required|confirmed'
            ]);
            $User = User::findOrFail($id);
            $password_old = $request->only('password_old')["password_old"];
            if (!Hash::check($password_old, $User->password)) {
                $errors = new MessageBag(['password' => ['Senha atual não confere.']]);
                return back()->withErrors($errors)->withInput();
            }
            $data = $request->only('password');
            /*
            $senha = $data['password'];
            $token = buscarAccessToken($senha, $User->email, '', 'password', $User->client_id);
            $data['access_token'] = $token;
            */
            $data['password'] = bcrypt($data['password']);
            $User->update($data);
            return \Redirect::route('home');
        } catch (\Exception $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }
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
            User::find($id)->delete();
        } catch (\Exception $e) {
            DB::rollback();
            return '<br /><br />O registro não pôde ser excluído pois está sendo usado!';
        }
        DB::commit();
        return 'OK|';

        //return view('empresas.empresasgrupo_form',compact('EmpresaGrupo'));
//    return \Redirect::route('user.index');
    }

    public function home()
    {
        return view('users.home');
    }

    public function encode_secret(Request $request)
    {
        $data = $request->all();
        $secret = encodeSecret($data['password']);
        return 'OK|' . $secret;
    }

}
