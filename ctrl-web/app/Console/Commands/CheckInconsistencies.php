<?php

namespace App\Console\Commands;

use App\Http\Controllers\InconsistenciaController;
use App\Notificacaouser;
use App\Notificacoes;
use DB;
use Illuminate\Console\Command;

class CheckInconsistencies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:inconsistencies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the database for streets and districts inconsistencies and warns the user.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $grupos = $this->getUserAlertGroups();

        $incController = new InconsistenciaController();
        $qryRua = $incController->getQueryInconRua();
        $qryBairro = $incController->getQueryInconBairro();

        foreach ($grupos as $gr) {
            $ruas = DB::select("SELECT COUNT(*) AS total FROM ({$qryRua}) cont", ["grupo" => $gr->grupo_id, "cidade" => $gr->cidade_id]);
            $bairros = DB::select("SELECT COUNT(*) AS total FROM ({$qryBairro}) cont", ["grupo" => $gr->grupo_id, "cidade" => $gr->cidade_id]);

            $ruas = $ruas[0];
            $bairros = $bairros[0];

            if ($ruas->total <= 0 && $bairros->total <= 0) continue;

            $this->notify($gr->grupo_id);
        }
    }

    private function getUserAlert($grupo_id)
    {
        $qry = "SELECT mu.user_id, em.id AS empresa_id, em.grupo_id
            FROM menus me
            INNER JOIN menuusers mu ON mu.menu_id = me.id
            INNER JOIN empresas em ON mu.empresa_id = em.id
            WHERE me.descricao = 'inconsistencia.index'
            AND mu.alerta = 1
            AND em.grupo_id = :grupo_id";

        return DB::select($qry, ["grupo_id" => $grupo_id]);
    }

    private function getUserAlertGroups()
    {
        $qry = "SELECT em.grupo_id, em.cidade_id
            FROM menus me
            INNER JOIN menuusers mu ON mu.menu_id = me.id
            INNER JOIN empresas em ON mu.empresa_id = em.id
            WHERE me.descricao = 'inconsistencia.index'
            AND mu.alerta = 1
            GROUP BY em.grupo_id, em.cidade_id";

        return DB::select($qry);
    }

    private function notify($grupo_id)
    {
        $users = $this->getUserAlert($grupo_id);

        foreach ($users as $user) {
            $notification = $this->getOrCreateNotification($user->grupo_id, $user->empresa_id);

            $notify = Notificacaouser::where("user_id", $user->user_id)
                ->where("empresa_id", $user->empresa_id)
                ->where("notificacao_id", $notification->id)
                ->first();

            if (!is_null($notify)) {

                if ($notify->status == "N") continue;

                $notify->update(["status" => "N"]);

                continue;
            }

            $notify = new Notificacaouser();
            $notify->user_id = $user->user_id;
            $notify->empresa_id = $user->empresa_id;
            $notify->notificacao_id = $notification->id;
            $notify->tela = $notification->tela;
            $notify->status = "N";
            $notify->created_at = now();
            $notify->updated_at = now();

            $notify->save();
        }
    }

    private function getOrCreateNotification($grupo_id, $empresa_id)
    {
        $notification = Notificacoes::where("grupo_id", $grupo_id)
            ->where("empresa_id", $empresa_id)
            ->where("tela", "inconsistencia")
            ->where("identificador", 0)
            ->first();

        if (!is_null($notification)) return $notification;

        $noti = new Notificacoes();
        $noti->grupo_id = $grupo_id;
        $noti->empresa_id = $empresa_id;
        $noti->descricao = "Foram encontradas possíveis inconsistências nos cadastros de Ruas e Bairros.";
        $noti->identificador = 0;
        $noti->tela = "inconsistencia";
        $noti->dangerlevel = 1;
        $noti->created_at = now();
        $noti->updated_at = now();
        $created = $noti->save();

        if ($created) return $noti;

        throw new \Exception("Houve um erro ao criar a notificação");
    }
}
