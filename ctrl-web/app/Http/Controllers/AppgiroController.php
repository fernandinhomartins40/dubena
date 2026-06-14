<?php

namespace App\Http\Controllers;

use App\Appnotification;
use App\Cliente;
use App\Http\Resources\ApiResources;
use App\Http\Resources\Classes\AppConfig;
use Cache;
use Carbon\Carbon;
use DB;
use Session;
use stdClass;

class AppgiroController extends Controller
{
    public function index()
    {
        $notiLayouts = $this->getNotificationLayout();

        return view("appgiro.index", compact("notiLayouts"));
    }

    public function getGiro()
    {
        $query = $this->getQuery();

        $empresa_id = Session::get("empresa_padrao")->id;

        $clientes = DB::select($query, [
            "empresa_id" => $empresa_id
        ]);

        $notified = $this->getOrSetNotifiedSession();
        $cliData = collect([]);
        foreach ($clientes as $cliente) {
            $primeira = Carbon::createFromFormat("Y-m-d H:i:s", $cliente->primeira_compra);
            $ultima = Carbon::createFromFormat("Y-m-d H:i:s", $cliente->ultima_compra);

            $cliente->primeira_compra = $primeira->format("d/m/Y H:i");
            $cliente->ultima_compra = $ultima->format("d/m/Y H:i");

            $cliente->notificado = false;
            if (count($notified->ids) > 0 && isset($notified->ids[$cliente->cliente_id])) {
                $cliente->notificado = $notified->ids[$cliente->cliente_id];
            }


            if (!is_null($cliente->giro)) {
                $cliente->previsao_compra = $ultima->addDays(round($cliente->giro, 0));
                //
                if ($cliente->previsao_compra->isSameMonth(Carbon::now())) {
                    $cliente->previsao_compra = $cliente->previsao_compra->format("d/m/Y H:i");
                    $cliData->push($cliente);
                }
            }
        }

        return response()->json(["data" => $cliData]);
    }

    public function notifyDevices()
    {
        $clientes_id = request()->get("clientes_id", null);
        $layout_id = request()->get("layout_id", null);

        if (is_null($clientes_id) || is_null($layout_id)) {
            return response()->json(["msg" => "clientes e layout são obrigatórios."], 400);
        }

        try {
            $layout = Appnotification::find($layout_id);
            $clientes_id = json_decode($clientes_id);
            $clientesapi_id = Cliente::whereIn("id", $clientes_id)
                ->select("api_id")
                ->get()
                ->pluck("api_id");

            $payload = [
                "clientes_id" => json_encode($clientesapi_id),
                "title" => $layout->fcmtitle,
                "body" => $layout->fcmbody
            ];

            $response = $this->sendNotifications($payload);
            $fcmResponse = $response->data->fcm_response;
            $success = $fcmResponse->success;
            $failure = $fcmResponse->failure;

            $this->setSessionNotified($clientes_id);

            return responseSuccess([], "Sucesso: {$success}, Falha: {$failure}");
        } catch (\Exception $ex) {
            return responseError($ex->getMessage(), $ex->getCode());
        }
    }

    private function getQuery()
    {
        $query = "SELECT cliente_id, cliente,
            max(dataped) AS ultima_compra,
            min(dataped) AS primeira_compra,
            sum(diff) AS qtde_dias,
            sum(quantidade) AS qtde_itens,
            (CASE WHEN sum(quantidade) IS NOT NULL THEN sum(diff) / sum(quantidade) ELSE 0 END) AS giro
            FROM (
                SELECT cli.id AS cliente_id, cli.nome AS cliente, ped.id AS pedido_id,
                ped.DATAHORAPREVISAOENTREGA AS dataped,
                trunc(lead(ped.DATAHORAPREVISAOENTREGA) OVER (PARTITION BY ped.cliente_id ORDER BY ped.DATAHORAPREVISAOENTREGA)) - (ped.DATAHORAPREVISAOENTREGA)::date AS diff,
                sum(it.quantidade) AS quantidade
                FROM (
                    SELECT cliente_id
                    FROM pedidos a
                    WHERE a.DATAHORAPREVISAOENTREGA BETWEEN
                    date_trunc('month', now() - INTERVAL '12' MONTH)
                    AND now()
                    AND a.APIPEDIDO_ID > 0
                    AND a.empresa_id = 2
                    AND a.PEDIDOSITUACAO_ID IN (
                        SELECT id
                        FROM PEDIDOSITUACAOS p
                        WHERE p.FECHADOCONCLUIDO = 1 OR p.ENTREGAFINALIZADA = 1
                        AND p.empresa_id = :empresa_id
                    )
                    GROUP BY cliente_id
                ) pd_cli
                RIGHT JOIN pedidos ped ON pd_cli.cliente_id = ped.cliente_id
                INNER JOIN pedidoitems it ON it.pedido_id = ped.id
                INNER JOIN clientes cli ON pd_cli.cliente_id = cli.id
                WHERE ped.DATAHORAPREVISAOENTREGA BETWEEN
                date_trunc('month', now() - INTERVAL '12' MONTH)
                AND now()
                AND ped.PEDIDOSITUACAO_ID IN (
                    SELECT id
                    FROM PEDIDOSITUACAOS p
                    WHERE p.FECHADOCONCLUIDO = 1 OR p.ENTREGAFINALIZADA = 1
                    AND p.empresa_id = :empresa_id
                )
                GROUP BY cli.id, cli.nome, ped.id, ped.cliente_id, ped.DATAHORAPREVISAOENTREGA
            ) peds
            GROUP BY cliente_id, cliente
            ORDER BY qtde_itens DESC";

        return $query;
    }

    private function getNotificationLayout()
    {
        $empresa_id = Session::get("empresa_padrao")->id;

        return Appnotification::where("empresa_id", $empresa_id)
            ->where("islayout", 1)
            ->select("id", "fcmtitle", "fcmbody")
            ->get();
    }

    private function sendNotifications($data)
    {
        $config = new AppConfig();

        $config->setConfig();

        $data["user_id"] = $config->empresa_id;

        $url = $config->api_url;

        $url = str_finish($url, '/') . "api/sendNotificationRecompra";

        $api = new ApiResources($url);

        $api->setAuthorizationCode($config->api_authorization);

        $response = $api->post($data, $url);

        return $response;
    }

    private function getOrSetNotifiedSession()
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $notified = Cache::get("notified_ids-{$empresa_id}", null);

        if (!is_null($notified) && Carbon::now()->lessThan($notified->expired_at)) {
            return $notified;
        }

        $expired_at = now()->addDays(3);
        $notified = new stdClass();
        $notified->ids = [];
        $notified->expired_at = $expired_at;

        Cache::put("notified_ids-{$empresa_id}", $notified, $expired_at);

        return $notified;
    }

    private function setSessionNotified($clientes_id)
    {
        $empresa_id = Session::get("empresa_padrao")->id;
        $notified = Cache::get("notified_ids-{$empresa_id}");

        $ids = $notified->ids;
        foreach ($clientes_id as $cliente_id) {
            $ids[$cliente_id] = true;
        }

        $notified->ids = $ids;

        Cache::put("notified_ids-{$empresa_id}", $notified, $notified->expired_at);
    }
}
