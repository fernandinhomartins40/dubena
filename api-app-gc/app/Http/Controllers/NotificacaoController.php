<?php

namespace App\Http\Controllers;

use App\Helpers\Util;
use Illuminate\Http\Request;
use App\Http\Resources\ApiResources;
use App\Jobs\SendNotificationMass;
use App\Repository\ClienteRepository;
use DB;
use stdClass;

class NotificacaoController extends Controller
{

    public function sendNotification(Request $request)
    {
        $data = $request->all();

        dispatch(new SendNotificationMass($data["title"], $data["body"], $data["user_id"], $data["notification_id"], false, $data["imagem"]));

        return responseSuccess();
    }

    public function sendNotificationCoupon(Request $request)
    {
        $data = $request->all();

        dispatch(new SendNotificationMass($data["title"], $data["body"], $data["user_id"], null, true));

        return responseSuccess();
    }

    public function sendNotificationRecompra()
    {
        $clientes_id = request()->get("clientes_id");
        $title = request()->get("title");
        $body = request()->get("body");

        try {
            $clientes_id = json_decode($clientes_id);
            $clientes = ClienteRepository::getNotificarById($clientes_id);
            $dif = count($clientes_id) - $clientes->count();
            $notFound = [];
            $result = new stdClass();
            $result->success = 0;
            $result->failure = $dif;
            $result->errors = [];

            foreach ($clientes as $client) {
                $response = ApiResources::notifyDevices($title, $body, $client->registration_id, [], true);

                if (isset($response->error) && $response->error) {
                    $result->failure += 1;

                    if (! in_array($response->message, $result->errors)) {
                        array_push($result->errors, $response->message);

                        if (str_contains($response->message, "not found")) {
                            array_push($notFound, $client->id);
                        }
                    }
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
                    "title" => $title,
                    "body"  => $body,
                ],
                "fcm_response"  => $result
            ];

            return responseSuccess($returnObj);
        } catch (\Exception $ex) {
            return responseError($ex->getMessage());
        }
    }

    public function sendNotificationDelivery(Request $request)
    {
        try {
            $credentials = env("ENTREGAS_CRED");

            putenv("GOOGLE_APPLICATION_CREDENTIALS=$credentials");

            $data = $request->all();

            $tokens = json_decode($data["registration_ids"]);

            if (!is_array($tokens)) {
                $tokens = [$tokens];
            }

            $result = new stdClass();
            $result->success = 0;
            $result->failure = 0;
            $result->errors = [];

            foreach ($tokens as $id) {
                $payload = [
                    "token"     => $id,
                    "data"      => $data["data"],
                    "android"   => [
                        "priority"  => "HIGH"
                    ]
                ];

                $response = ApiResources::notifyDelivery($payload, env("FCM_URL_ENTREGAS"));

                if ($response->error) {
                    $result->failure += 1;

                    array_push($result->errors, $response->message);

                    Util::log("Response: " . json_encode($response) . PHP_EOL);
                } else {
                    $result->success += 1;
                }
            }

            $returnObj = [
                "notificacao" => $data,
                "fcm_response" => $result
            ];

            Util::log("Objeto de retorno entregadores: " . json_encode($returnObj) . PHP_EOL);

            return responseSuccess($returnObj);
        } catch (\Exception $e) {
            Util::log("Error entregadores: " . $e->getMessage() . " Code :" . $e->getCode() . " Line " . $e->getLine() . " on " . $e->getFile() . PHP_EOL);

            return responseError($e->getMessage());
        }
    }

    public function getClientesRegistration()
    {
        $clientes = ClienteRepository::getAtivosNotificar();

        Util::log("Quantidade de clientes que será enviado as notificações " . count($clientes) . PHP_EOL);

        return collect($clientes);
    }

    public function getClientesRegistrationCupom()
    {
        $clientes = ClienteRepository::getAtivosNotificarCupom();

        Util::log("Quantidade de clientes que receberá notificação dos cupons " . count($clientes) . PHP_EOL);

        return collect($clientes);
    }
}
