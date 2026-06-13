<?php

namespace App\Http\Controllers;

use DB;
use View;
use Session;
use App\User;
use App\Menu;
use App\Menuuser;
use App\Services\CarbonCustom as Carbon;
use App\Empresaconfig;
use App\Http\Requests;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use App\Http\Controllers\Controller;
use App\Repository\PedidoRepository;

class AuthController extends Controller
{

    public function login()
    {
        return view('auth.login');
    }

    public function handleLogin(Request $request)
    {
        $this->validate($request, User::$login_validation_rules);
        $data = $request->only('email', 'password', 'ativo');

        if (\Auth::attempt($data)) {
            $this->clearBrowserCache();
            $user = \Auth::user();
            $empresa = $user->empresa;
            $empresa['bairroDesc'] = $empresa->bairro->descricao;
            $empresa['cidadeDesc'] = $empresa->cidade->descricao;

            if ($empresa->rua != null)
                $empresa['ruaDesc'] = $empresa->rua->descricao;

            Session::put('empresa_padrao', $empresa);

            $config = Empresaconfig::getForSession($empresa->id);

            Session::put('empresa_config', $config);

            $menu = Menu::menus();
            Session::put('menu', $menu);

            $permissions = Menuuser::join('menus', 'menuusers.menu_id', 'menus.id')
                ->where(['user_id' => $user->id, 'empresa_id' => $empresa->id])
                ->get();
            Session::put('permissoes', $permissions);

            $empresas_user = DB::table('empresa_user')->where('user_id', $user->id)
                ->pluck('empresa_id');
            $empresas_id = PedidoRepository::getEmpresas($user);
            Session::put('empresas_user', $empresas_user);
            Session::put('empresas_permitidas', $empresas_id);

            $this->organizeNotifications($user->id);

            return redirect()->to('home');
        }
        $errors = new MessageBag(['password' => ['Email e/ou senha inválidos.']]);
        return back()->withErrors($errors)->withInput();
    }

    public function loginFromApi($data)
    {
        if (\Auth::attempt($data)) {
            $user = \Auth::user();
            $empresa = $user->empresa;

            $config = Empresaconfig::getForSession($empresa->id);

            if (isset($empresa->logoimg) && $empresa->logoimg != null)
                unset($empresa->logoimg);

            $permissions = Menuuser::join('menus', 'menuusers.menu_id', 'menus.id')
                ->where(['user_id' => $user->id, 'empresa_id' => $empresa->id])
                ->get();

            Session::put('permissoes', $permissions);
            Session::put('empresa_padrao', $empresa);
            Session::put('empresa_config', $config);

            return true;
        }

        return false;
    }

    public function logout()
    {
        \Auth::logout();
        return \Redirect::to('login');
    }

    protected function clearBrowserCache()
    {
        // Em CLI/testes (PHPUnit) os headers já foram emitidos pelo runner e
        // headers_sent() é true — chamar header() dispararia ErrorException.
        // Em HTTP real isso nunca ocorre. Sem efeito no comportamento de produção.
        if (php_sapi_name() === 'cli' || headers_sent()) {
            return;
        }
        header("Pragma: no-cache");
        header("Expires: Mon, 20 Jul 2000 03:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-cache, cachehack=" . time());
        header("Cache-Control: no-store, must-revalidate");
        header("Cache-Control: post-check=-1, pre-check=-1", false);
    }

    public static function organizeNotifications($user_id)
    {
        $query = self::getQuery($user_id);
        $db = DB::select($query);
        $count = count($db);
        $anterior = null;
        $check = '<i class="normal fa icone fa-list text-aqua" aria-hidden="true"></i>';
        $car = '<i class="normal fa icone fa-car text-aqua" aria-hidden="true"></i>';
        $exame = '<i class="normal fa icone fa-stethoscope text-aqua" aria-hidden="true"></i>';
        $cogs = '<i class="normal fa icone fa-cogs text-aqua" aria-hidden="true"></i>';
        $checked = '<i class="fa icone fa-check text-aqua" aria-hidden="true"></i>';
        $data_toggle = 'data-toggle="tooltip" data-trigger="hover" data-container="body" ';
        $lef = 'data-placement="left"';
        $bot = 'data-placement="bottom"';
        $notificacoes = collect([]);
        if ($count > 0) {
            foreach ($db as $alerta) {
                if (is_null($anterior) || $anterior != $alerta->empresa_id) {
                    $objempresa = (object) array();
                    $objempresa->label = $alerta->empresa;
                    $objempresa->id = $alerta->empresa_id;
                    $notificacoes->push($objempresa);
                }

                switch ($alerta->tela) {
                    case 'checklist':
                        $icone = $alerta->status != 'N' ? $checked : $check;
                        break;
                    case 'veiculo':
                        $icone = $alerta->status != 'N' ? $checked : $car;
                        break;
                    case 'colaborador':
                        $icone = $alerta->status != 'N' ? $checked : $exame;
                        break;
                    default:
                        $icone = $alerta->status != 'N' ? $checked : $cogs;
                        break;
                }
                $toggle = strlen($alerta->notificacoes) >= 90 ? $data_toggle . $lef : $data_toggle . $bot;

                $objnormal = (object) array();
                $objnormal->descricao = "$icone <span class='alert-desc' $toggle title='$alerta->notificacoes' emp='$alerta->empresa_id' " .
                    "id='$alerta->noti_id'>$alerta->notificacoes</span>";
                $objnormal->status = $alerta->status;
                $notificacoes->push($objnormal);
                $anterior = $alerta->empresa_id;
            }
        }

        Session::forget('notificacoes');
        Session::put('notificacoes', $notificacoes);
        return true;
    }

    private static function getQuery($user_id)
    {
        $query = "select notiu.id as noti_id, muser.empresa_id, empresas.nome_informal as empresa, menus.descricao as menu, " .
            "menus.titulo, noti.descricao as notificacoes, noti.tela, notiu.status " .
            "from menuusers muser " .
            "inner join empresa_user empu on muser.user_id = empu.user_id and muser.empresa_id = empu.empresa_id " .
            "inner join menus on muser.menu_id = menus.id " .
            "inner join notificacoes noti on muser.empresa_id = noti.empresa_id and empu.empresa_id = noti.empresa_id " .
            "inner join empresas on muser.empresa_id = empresas.id and empu.empresa_id = empresas.id and noti.empresa_id = empresas.id " .
            "inner join notificacaousers notiu on notiu.user_id = muser.user_id and notiu.user_id = empu.user_id and notiu.notificacao_id = noti.id " .
            "where alerta = 1 and muser.user_id = $user_id and menus.descricao like '%'||noti.tela||'.%' and notiu.status in ('N','R') " .
            "and noti.appnotification = 0 " .
            "order by empresa_id, empresa, noti.tela";

        return $query;
    }
}
