<?php
namespace App\Broadcasting;

use Exception;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require_once realpath(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . 'autoload.php');

try {
    $server = new HttpServer(new WsServer(new WsConnection()));
    $server = IoServer::factory($server, 8003);
    $server->run();
} catch (Exception $e) {
    echo $e->getMessage();
}