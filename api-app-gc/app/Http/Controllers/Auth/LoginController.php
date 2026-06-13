<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Menu;
use Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    protected $rules = [
        'email' => 'required|exists:users',
        'password' => 'required|min:8' // FASE 1 (segurança — S6): era min:4
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function login(Request $request)
    {
        $this->validate($request, $this->rules);
        $data = $request->all("email", "password");
        if (Auth::attempt($data)) {
            $menu = Menu::menus();
            Session::put('menu', $menu);
            return redirect()->intended('user');
        }
        $errors = new MessageBag([
                'password' => ['Senha inválida.']
            ]);
        return back()->withErrors($errors)->withInput();
    }
}
