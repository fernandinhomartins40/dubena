<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 27/03/2019
 * Time: 10:33
 */

namespace App\Broadcasting;

use App\Helpers\Util;
use App\Http\Controllers\PedidoController;
use App\Repository\PedidoRepository;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use stdClass;

class WsConnection implements MessageComponentInterface
{
    /**
     * @var $wsClients Collection
     */
    protected $wsClients;

    /**
     * @var array $events
     */
    protected $events = [
        "POSITION_UPDATED",
        "VEHICLE_UPDATED",
        "ORDER_UPDATED",
        "INVALID_PARAMS",
        "MAX_CONNECTIONS_LIMIT",
        "UNAUTHENTICATED",
        "INVALID_DATA_FORMAT",
        "JSON_DECODE_ERROR"
    ];

    /**
     * @var array $allowedParameters
     */
    protected $allowedParameters = [
        "client", "pedido_id", "placa", "app_key", "cliente_id"
    ];

    /**
     * @var array $allowedClients
     */
    protected $allowedClients = [
        "monitoramento", "api", "gasemcasa"
    ];

    /**
     * @var null|string
     */
    protected $appKey = null;

    /**
     * WsConnection constructor.
     * @throws Exception
     */
    public function __construct() {
        $envA = explode(PHP_EOL, file_get_contents(".env"));
        foreach ($envA as $env) {
            $exp = explode("=", $env);
            if ($exp[0] === "APP_KEY") {
                $this->appKey = sha1(str_replace("APP_KEY=", "", $env));
            }
        }
        if (! $this->appKey) {
            throw new Exception("Chave do APP não encontrada");
        }
        $this->wsClients = collect([]);
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn) {
        /** @noinspection PhpUndefinedFieldInspection */
        $resource = $conn->resourceId;
        $totalConnections = $this->wsClients->count();
        if ($totalConnections >= 100) {
            $this->throwInvalidConnection($conn, "Número máximo de conexões atingida", "MAX_CONNECTIONS_LIMIT");
            return;
        }

        try {
            $pars = $this->getQueryParameters($conn);
        } catch (Exception $e) {
            $this->throwInvalidConnection($conn, $e->getMessage(), "INVALID_PARAMS");
            return;
        }

        try {
            if (! $this->authorize($conn, $pars)) {
                $this->throwInvalidConnection($conn, "não autorizado", "UNAUTHENTICATED");
                return;
            }
        } catch (Exception $e) {
            $this->throwInvalidConnection($conn, "Você não possui as permissões necessárias para realizar a conexão", "UNAUTHENTICATED");
            return;
        }

        if (! isset($pars["client"])) {
            $this->throwInvalidConnection($conn, "Parâmetro \"client\" não informado ou inválido");
            return;
        } elseif ($pars["client"] === "gasemcasa" && $this->isInvalidParameter($pars, "pedido_id")) {
            $this->throwInvalidConnection($conn, "Parâmetro \"pedido_id\" não informado ou inválido");
            return;
        } elseif ($pars["client"] === "gasemcasa" && $this->isInvalidParameter($pars, "placa")) {
            $this->throwInvalidConnection($conn, "Parâmetro \"placa\" não informado ou inválido");
            return;
        } elseif ($pars["client"] === "gasemcasa" && $this->isInvalidParameter($pars, "cliente_id")) {
            $this->throwInvalidConnection($conn, "Parâmetro \"cliente_id\" não informado ou inválido");
            return;
        } else {

            try {
                if ($pars["client"] === "gasemcasa") {
                    $this->wsClients->put($resource, $this->getClient($conn, $pars));
                    $this->echo("Nova conexão: " . $resource . ". " . $this->wsClients->count() . ' conexões ativas');
                }
            } catch (GuzzleException $e) {
                $this->echo($e->getMessage());
            } catch (Exception $e) {
                $this->echo($e->getMessage());
            }
        }
    }

    /**
     * @param $conn
     * @param $pars
     * @return bool
     */
    private function authorize($conn, $pars)
    {
        if (! isset($pars["app_key"])) {
            $this->throwInvalidConnection($conn, "Parâmetro \"app_key\" não informado ou inválido");
        } elseif ($pars["app_key"] === $this->appKey) {
            return true;
        }
        return false;
    }

    /**
     * @param $pars
     * @param $expectedKey
     * @return bool
     */
    private function isInvalidParameter($pars, $expectedKey)
    {
        return (! isset($pars[$expectedKey]) || (isset($pars[$expectedKey]) && in_array($pars[$expectedKey], ["null", "undefined", "0", "NaN", ""])));
    }

    /**
     * @param ConnectionInterface|Collection $conn
     * @param string $data
     * @param string $event
     * @param bool $echo
     */
    private function throwInvalidConnection(&$conn, $data, $event = "INVALID_PARAMS", $echo = true)
    {
        if (! is_null($conn)) {
            if ($conn instanceof Collection) {
                $conn->conn->close();
                $conn->conn->send(json_encode(
                    (object) [
                        "data"          => $data,
                        "event"         => $event,
                        "data_format"   => "string"
                    ]
                ));
            } else {
                $conn->send(json_encode(
                    (object) [
                        "data"          => $data,
                        "event"         => $event,
                        "data_format"   => "string"
                    ]
                ));
                $conn->close();
            }
        }
        if ($echo) {
            $this->echo($data);
        }
    }

    /**
     * @param ConnectionInterface $conn
     * @param array $parameters
     * @return stdClass
     * @throws GuzzleException|Exception
     */
    private function getClient($conn, $parameters)
    {
        $client = new stdClass();
        $client->conn = $conn;
        $client->order = null;
        if (isset($parameters["pedido_id"]) && $parameters["client"] === "gasemcasa") {
            $client->pedido_id = $parameters["pedido_id"];
            $client->order = $this->getOrder($parameters["cliente_id"], $conn);
        } else {
            $client->pedido_id = null;
        }
        $client->placa = isset($parameters["placa"]) ? $parameters["placa"] : null;
        $client->client = $parameters["client"];
        $client->cliente_id = isset($parameters["cliente_id"]) ? $parameters["cliente_id"] : null;

        return $client;
    }

    /**
     * @param $cliente_id
     * @param $conn
     * @param bool $throwEx
     * @return PedidoRepository|Model|JsonResponse|object|null
     * @throws GuzzleException|Exception
     */
    private function getOrder($cliente_id, $conn, $throwEx = true)
    {
        try {
            $controller = new PedidoController(true);
            return $controller->track(true, $cliente_id);
        } catch (GuzzleException $e ) {
            $this->throwInvalidConnection($conn, $e->getMessage(), "ORDER_NOT_FOUND", false);
            if ($throwEx) {
                throw $e;
            } else {
                return null;
            }
        } catch (Exception $e ) {
            $this->throwInvalidConnection($conn, $e->getMessage(), "ORDER_NOT_FOUND", false);
            if ($throwEx) {
                throw $e;
            } else {
                return null;
            }
        }
    }

    /**
     * @param $message
     */
    private function echo($message)
    {
        echo PHP_EOL . $message . PHP_EOL;
    }

    /**
     * @param ConnectionInterface $from
     * @param string $message
     * @throws GuzzleException
     */
    public function onMessage(ConnectionInterface $from, $message)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $this->echo('Conexão ' . $from->resourceId . ' enviou uma menssagem "' . str_limit($message, 50, "...(truncated)") . '"');

        try {
            try {
                $pars = $this->getQueryParameters($from);
            } catch (Exception $e) {
                $this->throwInvalidConnection($from, $e->getMessage(), "INVALID_PARAMS");
                return;
            }
            if ($pars["client"] === "monitoramento" || $pars["client"] === "api") {
                $this->validateAndSendMessage($from, $message);
                $from->close();
            }
        } catch (Exception $e) {
            Util::log("erro ao validar parâmetros da mensagem recebida no Web Socket:" . $e->getMessage(), "error");
        }
    }

    /**
     * @param ConnectionInterface $from
     * @param $stringMessage
     * @throws GuzzleException
     */
    private function validateAndSendMessage(ConnectionInterface $from, $stringMessage)
    {
        $objMessage = $this->convertMessageToObject($stringMessage, $from);
        if (is_null($objMessage)) {
            $this->echo("Mensagem recebida mas não encaminhada a nenhum cliente");
            return;
        }
        foreach ($this->wsClients as &$client) {
            if ($from === $client) {
                continue;
            }
            try {
                if (! isset($objMessage->data->pedido_id)) {
                    $objMessage->data->pedido_id = null;
                }
                if ($objMessage->event === "VEHICLE_UPDATED" && $objMessage->data->pedido_id == $client->pedido_id) {
                    $client->placa = $objMessage->data->placa;
                }
                //TODO validar para só enviar mensagens quando as requisições vierem de servidores iguais
                $updatePosition = $objMessage->event === "POSITION_UPDATED" && $objMessage->data->placa == $client->placa;
                $isSameOrder = $objMessage->data->pedido_id == $client->pedido_id;
                $updateOrder = $objMessage->event === "VEHICLE_UPDATED" || $objMessage->event === "ORDER_UPDATED";

                if ($updatePosition || ($updateOrder && $isSameOrder)) {
                    if ($updateOrder && $isSameOrder) {
                        $client->order = $this->getOrder($client->cliente_id, $client->conn,false);
                    } else {
                        $client->order->track->location->latitude = (float) $objMessage->data->latitude;
                        $client->order->track->location->longitude = (float) $objMessage->data->longitude;
                        $client->order->track->location->azimuth = (float) $objMessage->data->azimuth;
                        $client->order->track->motorista = $objMessage->data->motorista;
                        $client->order->track->placa = $objMessage->data->placa;
                    }
                    $order = $client->order;
                    $client->conn->send(json_encode(
                        (object) [
                            "data"          => json_encode($order),
                            "event"         => "POSITION_UPDATED",
                            "data_format"   => "json"
                        ]
                    ));
                }
            } catch (Exception $e) {
                $error = "Erro ao atualizar posição do veículo para o cliente: " . $e->getMessage();
                $this->echo($error);
                Util::log($error, "error");
            }
        }
    }

    /**
     * @param $stringMessage
     * @param $from
     * @return object|null
     */
    private function convertMessageToObject($stringMessage, $from)
    {
        $objData = null;
        $objMessage = $this->decodeJson($stringMessage, $from);
        if (is_bool($objMessage)) {
            return null;
        }
        if ($objMessage->event === "POSITION_UPDATED" || $objMessage->event === "ORDER_UPDATED" || $objMessage->event === "VEHICLE_UPDATED") {
            if ($objMessage->data_format !== "json") {
                $this->throwInvalidConnection(
                    $from,
                    "O tipo de data esperado é \"json\"para os eventos \"ORDER_UPDATED\", \"VEHICLE_UPDATED\" e \"POSITION_UPDATED\"", "INVALID_DATA_FORMAT"
                );
                return null;
            }
            $objData = $this->decodeJson($objMessage->data, $from);
        }
        if (is_bool($objData) || is_null($objData)) {
            return null;
        } else {
            $objMessage->data = $objData;
            return $objMessage;
        }
    }

    /**
     * @param $string
     * @param $from
     * @return bool|object
     */
    private function decodeJson($string, $from) {
        if (is_string($string)) {
            $objMessage = json_decode($string);
        } else {
            $objMessage = $string;
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->throwInvalidConnection($from, json_last_error_msg(), "JSON_DECODE_ERROR");
            Util::log(json_last_error_msg());
            return false;
        }
        return $objMessage;
    }

    /**
     * @param ConnectionInterface $from
     * @return array
     * @throws Exception
     */
    private function getQueryParameters($from)
    {
        if (isset($from->httpRequest) && $from->httpRequest instanceof Request) {
            /**@var $request Request*/
            $request = $from->httpRequest;
            $queryString = $request->getUri()->getQuery();
            if (strlen($queryString) === 0) {
                throw new Exception("Nenhum parâmetro informado");
            }
            $queryArray = explode("&", $queryString);
            $parameters = [];
            foreach ($queryArray as $query) {
                $exploded = explode("=", $query);
                if (! in_array($exploded[0], $this->allowedParameters)) {
                    throw new Exception("Parâmetro " . $exploded[0] . " não é aceito ou é inválido: " . (isset($exploded[1]) ? $exploded[1] : "empty" ));
                }
                $parameters[$exploded[0]] = $exploded[1];
            }
            return $parameters;
        } elseif (! isset($from->httpRequest)) {
            throw new Exception("Request vazia");
        } else {
            throw new Exception("Request recebida é inválida");
        }
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn) {
        /** @noinspection PhpUndefinedFieldInspection */
        $resource = $conn->resourceId;

        if ($this->wsClients->has($resource)) {
            $this->wsClients->forget($resource);
        }

        $this->echo("Conexão " . $resource . " fechada. " . $this->wsClients->count() . ' conexões ativas' . "");
    }

    /**
     * @param ConnectionInterface $conn
     * @param Exception $e
     */
    public function onError(ConnectionInterface $conn, Exception $e) {
        $this->echo("Ocorreu um erro: {$e->getMessage()}");

        $conn->close();
    }
}