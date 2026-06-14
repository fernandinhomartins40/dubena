<?php

namespace App\Monitora\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Monitora\Models\User;
use App\Monitora\Models\Menu;
use Illuminate\Support\MessageBag;
use Session;
use View;
use Carbon\Carbon;
use App\Monitora\Models\Config;

//use Validator;

class AuthController extends Controller
{

    public function login()
    {
        return view('monitora.auth.login');
    }

    public function handleLogin(Request $request)
    {
        $this->validate($request, User::$login_validation_rules);
        $data = $request->only('email', 'password', 'ativo');
        if (\Auth::guard('monitora')->attempt($data)) {
            $this->clearBrowserCache();
            $empresa = User::where('email', $data['email'])->first()->empresa;
            Session::put('empresa_padrao', $empresa);
            $config = Config::all()->first();
            Session::put('config', $config);
            
            $menu = Menu::menus();
            Session::put('menu', $menu);
            $authUser = \Auth::guard('monitora')->user();
            return redirect()->intended('/monitora/home');
        }
        $errors = new MessageBag(['password' => ['Email e/ou senha inválidos.']]);

        return back()->withErrors($errors)->withInput();
    }

    public function logout()
    {
        \Auth::guard('monitora')->logout();
        return redirect()->route('monitora.login');
    }

    protected function clearBrowserCache()
    {
        header("Pragma: no-cache");
        header("Expires: Mon, 20 Jul 2000 03:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-cache, cachehack=" . time());
        header("Cache-Control: no-store, must-revalidate");
        header("Cache-Control: post-check=-1, pre-check=-1", false);
    }

}
