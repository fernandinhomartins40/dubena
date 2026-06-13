<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 27/03/2019
 * Time: 10:39
 */

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Broadcasting\CFeConnection;

require_once realpath(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . 'autoload.php');
try {
    $server = IoServer::factory(new HttpServer(new WsServer(new CFeConnection())), 8002);
    $server->run();
} catch (Exception $e) {
    echo $e->getMessage();
}