<?php

namespace App\Console\Commands;

use App\Broadcasting\WsConnection;
use Exception;
use Illuminate\Console\Command;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class WsConnect extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ws:connect';

    /**
     * @var HttpServer
     */
    private $server;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connect with websocket';

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
            $address = env("WEBSOCKET_ADDRESS", null);
            if (! $address) {
                echo "Endereço do websocket não configurado";
                return false;
            }
            $port = explode(":", $address)[2];
            $address = str_replace("//", "", explode(":", $address)[1]);
            $connection = @fsockopen($address, $port);

            if (is_resource($connection))
            {
                echo 'port is in use!';
                fclose($connection);
                return false;
            }
            else
            {
                $ws = new WsConnection();
                $this->server = new HttpServer(new WsServer($ws));
                $this->server = IoServer::factory($this->server, $port, $address);
                $this->server->run();
                return true;
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }
}
