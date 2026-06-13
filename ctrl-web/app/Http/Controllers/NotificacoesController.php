<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Notificacaouser;
use App\Androidmensagem as Men;
use App\Console\Commands\Notificacao;
use Illuminate\Support\Facades\Input;

class NotificacoesController extends Controller
{
    protected $error;

    function __construct()
    {
        $this->error = [];
    }

    protected function addError($error)
    {
        array_push($this->error, $error);
    }

    protected function getError()
    {
        return $this->error;
    }

    /**
     * Hub for reading notifications
     *
     * @param array $_GET
     * @return string
     */
    public function readNotification()
    {
        $user_id = \Auth::user()->id;
        $id = Input::get("notification");
        $status = Input::get("status");
        $tipo = intval(Input::get("tipo", 1));

        switch ($tipo) {
            case 1:
                $up = $this->updateAndroid($id, $status, $user_id);
                break;
            case 2:
                $up = $this->updateMobile($id, $status);
                break;
            default:
                $up = $this->updateNormal($id, $status, $user_id);
                break;
        }

        if ($up) return 'OK';
        else return implode(',', $this->getError());
    }

    /**
     * Function me liga android
     *
     * @param void
     */
    public function meLiga()
    {
        $empresa_id = Session::get('empresa_padrao')->id;
        $user = $this->getUser();
        $tipo = request()->get("tipo");

        $notificacoes = DB::table('androidmensagems men')->join('users', 'men.user_id', 'users.id')
            ->join('colaboradors cool', 'users.colaborador_id', 'cool.id')
            ->join('empresas', 'men.empresa_id', 'empresas.id')
            ->where([
                ['men.empresa_id', $empresa_id]
            ])
            ->whereIn('men.situacao', ['E', 'R'])
            ->selectRaw("men.id as men_id, men.situacao, empresas.id as empresa_id, empresas.nome_informal as empresa, cool.nome, " .
                "(case when trim(men.descricao) is null then cast(' Por favor, entrar em contato.' as varchar(30)) else ' ' || men.descricao end) as descricao, 0 as appnotification, 1 as dangerlevel")
            ->orderBy('men.created_at', 'DESC')
            ->get();

        $appNoti = DB::table('notificacoes noti')
            ->join("notificacaousers us", "us.notificacao_id", "noti.id")
            ->join("empresas emp", "emp.id", "us.empresa_id")
            ->whereRaw("us.user_id = $user->id AND us.empresa_id = $empresa_id AND us.status in ('N', 'R') AND noti.appnotification = 1")
            ->selectRaw("us.id as men_id, us.status as situacao, emp.nome_informal as empresa, 'Aplicativo' as nome, " .
                "noti.descricao, noti.appnotification, noti.dangerlevel, emp.id AS empresa_id")
            ->orderBy("noti.created_at", "DESC")
            ->get();

        $notiCerca = DB::table('notificacoes noti')
            ->join("notificacaousers us", "us.notificacao_id", "noti.id")
            ->join("empresas emp", "emp.id", "us.empresa_id")
            ->whereRaw("us.user_id = $user->id AND us.empresa_id = $empresa_id AND us.status in ('N', 'R') AND noti.tela = 'logcercas'")
            ->selectRaw("us.id as men_id, us.status as situacao, emp.nome_informal as empresa, 'Cercas' as nome, " .
                "noti.descricao, noti.appnotification, noti.dangerlevel, emp.id AS empresa_id")
            ->orderBy("noti.created_at", "DESC")
            ->get();


        if ($tipo == 1) {
            $notificacoes = $notificacoes->merge($appNoti, $notiCerca);
        } else if ($tipo == 2) {
            $notificacoes = $notificacoes->merge($appNoti);
        } else {
            $notificacoes = $notiCerca;
        }

        if (count($notificacoes)) return json_encode($notificacoes);
        else return 'null';
    }

    /**
     * Update status of notifications
     *
     * @param int $id
     * @param string $status
     * @param int $user_id
     * @return bool
     */
    protected function updateNormal($id, $status, $user_id)
    {
        DB::beginTransaction();
        try {
            $notiuser = Notificacaouser::find($id);
            if (!is_null($notiuser)) {
                $qual = $notiuser->tela;
                $notiuser->update(["status" => $status]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            $error = "Error: " . $e->getMessage() . " Line: " . $e->getLine();
            $this->addError($error);
            return false;
        }
        DB::commit();

        if (!isset($qual)) {
            $qual = 'new';
        }
        $this->updateNotificationSession($user_id, $qual);

        return true;
    }

    /**
     * Update the notifications and put them up on session
     *
     * @param int    $id
     * @param string $qual
     * @return void
     */
    protected function updateNotificationSession($id, $qual)
    {
        switch ($qual) {
            case 'pneu':
                $which = 'cogs';
                break;
            case 'oleo':
                $which = 'cogs';
                break;
            case 'checklist':
                $which = 'check';
                break;
            case 'colaborador':
                $which = 'exame';
                break;
            case 'veiculo':
                $which = 'car';
                break;
            default:
                $which = 'new';
                break;
        }
        AuthController::organizeNotifications($id, 'status', $which);
    }

    /**
     * Update android notifications
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    protected function updateAndroid($id, $status, $user_id)
    {
        $mensagem = Men::find($id);

        if (is_null($mensagem)) {
            return $this->updateNormal($id, $status, $user_id);
        }

        DB::beginTransaction();
        try {
            if ($status === 'R') $mensagem->update(["situacao" => $status]);
            else  $mensagem->delete();
        } catch (\Exception $e) {
            DB::rollback();
            $error = "Error: " . $e->getMessage() . " Line: " . $e->getLine();
            $this->addError($error);
            return false;
        }
        DB::commit();

        return true;
    }

    private function getUser()
    {
        $user = \Auth::user();
        if (is_null($user))
            $user = auth("api")->user();

        return $user;
    }

    private function updateMobile($id, $status)
    {
        try {
            DB::beginTransaction();
            $notificacao = Notificacaouser::find($id);

            if ($status === "R")
                $notificacao->update(["status"  => $status]);
            else
                $notificacao->update(["status"  => "D"]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            $error = "Error: " . $e->getMessage() . " Line: " . $e->getLine();
            $this->addError($error);
            return false;
        }
        return true;
    }
}
