<?php

namespace App\Events;

use App\Helpers\Utils\Util;
use App\Http\Resources\Classes\AppConfig;
use App\Notificacaouser;
use App\Notificacoes;
use App\Services\CarbonCustom as Carbon;
use DB;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NotifySGC
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var
     */
    public $message;

    /**
     * @var
     */
    public $level;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($message, $level)
    {
        $this->message = $message;
        $this->level = $level;
        switch ($level) {
            case "info":
                $level = 1;
                break;
            case "alert":
                $level = 2;
                break;
            case "avaliacao":
                $level = 4;
                break;
            default:
                $level = 3;
                break;
        }

        $this->createNotification($message, $level);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [];
    }

    private function createNotification($message, $level)
    {
        try {
            DB::beginTransaction();
            $config = new AppConfig();
            $config->setConfig();

            $notificacao = new Notificacoes();
            $notificacao->grupo_id = $config->grupo_id;
            $notificacao->empresa_id = $config->empresa_id;
            $notificacao->descricao = str_limit($message, 496);
            $notificacao->dangerlevel = $level;
            $notificacao->identificador = 0;
            $notificacao->tela = $config->telapermissao;
            $notificacao->appnotification = true;
            $notificacao->created_at = Carbon::now()->format('Y-m-d H:m:s');
            $notificacao->updated_at = Carbon::now()->format('Y-m-d H:m:s');

            $notificacao = Notificacoes::create($notificacao->toArray());

            $usuarios = collect([]);
            $notifyusers = new Notificacaouser();
            $queryUser = $notifyusers->getUsersQuery("1");
            $dbusers = collect(DB::select($queryUser));
            foreach ($dbusers as $user) {
                $notify = new Notificacaouser();
                $notify->user_id = $user->user_id;
                $notify->empresa_id = $user->empresa_id;
                $notify->notificacao_id = $user->notificacao_id;
                $notify->tela = $user->tela;
                $notify->status = $user->status;
                $notify->created_at = Carbon::now()->format('Y-m-d H:m:s');
                $notify->updated_at = Carbon::now()->format('Y-m-d H:m:s');
                $usuarios->push($notify);
            }
            $notifyusers->insert($usuarios->toArray());
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Util::log($e->getMessage(), "error");
        }
    }

    public function __toString()
    {
        return collect($this)->toJson();
    }
}
