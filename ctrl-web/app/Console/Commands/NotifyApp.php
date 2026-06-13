<?php

namespace App\Console\Commands;

use App\Appnotification;
use App\Helpers\Utils\Util;
use Illuminate\Console\Command;
use App\Http\Controllers\AppnotificationController;

class NotifyApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:app';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications stored in appnotifications to the app';

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
        try {
            $notificacao = $this->getNotificacoes();

            $payload = [
                "id"    => $notificacao->id,
                "title" => $notificacao->fcmtitle,
                "body"  => $notificacao->fcmbody
            ];

            $controller = new AppnotificationController();

            // $response = $controller->enviarNotificacao($payload);

            // $fcm = $response->data->fcm_response;

            // $status = $controller->mapStatus($fcm);

            // $notificacao->update(["status" => $status]);

            // $msg = "Notificação enviada com status: " . $status;
            // $this->info($msg);
            // Util::log($msg);
        } catch (\Exception $ex) {
            $msg = "Aconteceu um erro: " . $ex->getMessage();
            $this->info($msg);
            Util::log($msg);
        }
    }

    private function getNotificacoes()
    {
        return Appnotification::where("instant", 0)
            ->where("status", "pendente")
            ->first();
    }
}
