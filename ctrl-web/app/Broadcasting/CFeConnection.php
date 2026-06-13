<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 27/03/2019
 * Time: 10:33
 */

namespace App\Broadcasting;

use Exception;
use GuzzleHttp\Psr7\Request;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use SplObjectStorage;

class CFeConnection implements MessageComponentInterface
{
    /**
     * @var  $wsClientsSplObjectStorage
     */
    protected $wsClients;

    public function __construct() {
        $this->wsClients = new SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->wsClients->attach($conn);

        $parametersFrom = $this->getQueryParameters($conn);
        if (! array_key_exists("empresa_id", $parametersFrom))
        {
            $message = "Parâmetro empresa_id é esperado";
            echo $message . '\n';
            //TODO fazer validações diferentes dependendo de onde veio a connexão
            $this->sendDataErrorServer($conn, $message);
            $conn->close();
            return;
        }
        /** @noinspection PhpUndefinedFieldInspection */
        echo "Nova conexão! ({$conn->resourceId})\n";
        echo count($this->wsClients) . ' conexões ativas' . "\n";
    }

    /**
     * @param ConnectionInterface $from
     * @param string $message
     */
    public function onMessage(ConnectionInterface $from, $message)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        echo sprintf('Conexão %d enviou uma messagem "%s"' . "\n", $from->resourceId, $message);

        $this->validateAndSendMessage($from, $message);
    }

    /**
     * @param ConnectionInterface $from
     * @param string $message
     */
    private function validateAndSendMessage(ConnectionInterface $from, $message)
    {
        $parametersFrom = $this->getQueryParameters($from);
        if (! array_key_exists("empresa_id", $parametersFrom))
        {
            $message = "Parâmetro empresa_id é esperado para notificar o clientes";
            echo $message;
            $this->sendDataErrorServer($from, $message);
            return;
        }
        if (! array_key_exists("sender", $parametersFrom))
        {
            $message = "Parâmetro sender é esperado para notificar o clientes";
            $this->sendDataErrorServer($from, $message);
            return;
        }
        $send = false;
        foreach ($this->wsClients as $client) {
            if ($from !== $client) {
                $parameters = $this->getQueryParameters($client);
                if (! array_key_exists("empresa_id", $parameters)) {
                    $send = true;
                    /** @noinspection PhpParamsInspection */
                    $message = "Parâmetro empresa_id é esperado para verificar os documentos pendentes";
                    $this->sendDataErrorServer($from, $message);
                } elseif ($parameters["empresa_id"] === $parametersFrom["empresa_id"] ) {
                    if ($parametersFrom["sender"] === "server") {
                        $client->send($message);
                        $this->sendDataSuccess($from, "Documento enviado para transmissão!");
                        $send = true;
                    }
                    break;
                }
            }
        }
        if (! $send) {
            $message = "Nenhum cliente recebeu a mensagem, certifique-se de que está com o Integrador SAT ativo.";
            $this->sendDataErrorServer($from, $message);
        }
    }

    /**
     * @param ConnectionInterface $sender
     * @param $message
     */
    private function sendDataSuccess($sender, $message)
    {
        $sender->send(json_encode(
            (object) [
                "message"   => $message,
                "status"    => 'OK',
                "data"      => null
            ]
        ));
    }

    /**
     * @param ConnectionInterface $sender
     * @param $message
     */
    private function sendDataErrorServer($sender, $message)
    {
        $sender->send(json_encode(
            (object) [
                "message"   => $message,
                "status"    => 'NOK'
            ]
        ));
    }

    /**
     * @param $from
     * @return array
     */
    private function getQueryParameters($from)
    {
        if (isset($from->httpRequest) && $from->httpRequest instanceof Request) {
            $queryString = $from->httpRequest->getUri()->getQuery();
            if ($queryString === "") {
                return [];
            }
            $queryArray = explode("&", $queryString);
            $parameters = [];
            foreach ($queryArray as $query) {
                $exploded = explode("=", $query);
                $parameters[$exploded[0]] = $exploded[1];
            }
            return $parameters;
        } else {
            echo "Request recebida é inválida\n";
            return [];
        }
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->wsClients->detach($conn);

        /** @noinspection PhpUndefinedFieldInspection */
        echo "Conexão {$conn->resourceId} saiu\n";
    }

    /**
     * @param ConnectionInterface $conn
     * @param Exception $e
     */
    public function onError(ConnectionInterface $conn, Exception $e) {
        echo "Ocorreu um erro: {$e->getMessage()}\n";

        $conn->close();
    }
}
