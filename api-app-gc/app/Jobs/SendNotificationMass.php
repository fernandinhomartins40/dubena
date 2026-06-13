<?php

namespace App\Jobs;

use App\Helpers\Util;
use App\Http\Controllers\NotificacaoController;
use App\Http\Resources\ApiResources;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use stdClass;

class SendNotificationMass implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    /**
     * @var title
     */
    public $title;

    /**
     * @var body
     */
    public $body;

    /**
     * @var user_id
     */
    public $user_id;

    /**
     * @var notification_id
     */
    public $notification_id;

    /**
     * @var isCupom
     */
    public $isCupom;

    /**
     * @var imageUrl
     */
    public $imageUrl;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($title, $body, $userId, $notificationId = null, $isCupom = false, $imageUrl = null)
    {
        $this->title = $title;
        $this->body = $body;
        $this->user_id = $userId;
        $this->notification_id = $notificationId;
        $this->isCupom = $isCupom;
        $this->imageUrl = $imageUrl;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $cont = new NotificacaoController();
            $clientes = null;

            if ($this->isCupom) {
                $clientes = $cont->getClientesRegistrationCupom();
            } else {
                $clientes = $cont->getClientesRegistration();
            }

            $result = new stdClass();
            $result->success = 0;
            $result->failure = 0;
            $result->errors = [];
            $notFound = [];

            foreach ($clientes as $cli) {
                $response = ApiResources::notifyDevices($this->title, $this->body, $cli->registration_id, [], true, false, $this->imageUrl);
                //
                if (isset($response->error) && $response->error) {
                    $result->failure += 1;

                    if (! in_array($response->message, $result->errors)) {
                        array_push($result->errors, $response->message);

                        if (str_contains($response->message, "not found")) {
                            array_push($notFound, $cli->id);
                        }
                    }

                    Util::log("Response: " . json_encode($response) . PHP_EOL);
                } else {
                    $result->success += 1;
                }
            }

            if (count($notFound) > 0) {
                DB::table("clienteimportacoes")->whereIn("id", $notFound)
                    ->update(["pushregistration_id" => null]);
            }

            $returnObj = [
                "notificacao"   => [
                    "title" => $this->title,
                    "body"  => $this->body,
                ],
                "fcm_response"  => $result
            ];

            Util::log("Objeto de retorno: " . json_encode($returnObj) . PHP_EOL);

            if (!is_null($this->notification_id))
                return $this->sendReturn($returnObj);
        } catch (\Exception $e) {
            Util::log("Error: " . $e->getMessage() . " Code :" . $e->getCode() . " Line " . $e->getLine() . " on " . $e->getFile() . PHP_EOL);

            if (!is_null($this->notification_id))
                return $this->sendReturn(null, true, $e->getMessage());
        }
    }

    private function sendReturn($returnObj = null, $isErr = false, $errMsg = "")
    {
        $user = User::find($this->user_id);

        $baseUri = str_finish($user->erpurl, '/') . "api/";

        $api = new ApiResources($baseUri, $user, "GET");

        $formParams = [
            "returnObj" => $returnObj,
            "error"     => $isErr,
            "error_msg" => $errMsg
        ];

        $api->setMethod("PUT");

        try {
            $api->request($formParams, "appnotification/massReturn/{$this->notification_id}");
        } catch (\Exception $ex) {
            info("Error returning: " . $ex->getMessage());
        }
    }
}
