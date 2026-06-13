<?php

namespace App\Console\Commands;

use App\Helpers\Util;
use Ratchet\Client;
use Exception;
use Illuminate\Console\Command;

class WsTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ws:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testing a WS connection';

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
        $address = env('WEBSOCKET_ADDRESS', null);
        if (is_null($address)) {
            echo "URL do Websocket não encontrada!";
            return;
        }
        echo "conectando com " . $address . PHP_EOL;

        Client\connect($address . "?client=api&app_key=" . sha1(env("APP_KEY")))->then(
            function(Client\WebSocket $conn) {
                $conn->send(json_encode(
                    (object) [
                        "data"      => (object) [
                            "placa"         => 0,
                            "pedido_id"     => 1
                        ],
                        "event"          => "VEHICLE_UPDATED",
                        "data_format"    => "json"
                    ]
                ));
                $conn->close();
            },
            function (Exception $e) {
                Util::notify("Não foi possível realizar a comunicação com o Websocket: {$e->getMessage()}");
            }
        );
        
        Client\connect($address . "?client=gasemcasa&pedido_id=55&placa=23&app_key=" . sha1(env("APP_KEY")))->then(
            function(Client\WebSocket $conn) {
                $conn->on("message", function ($message) {
                    echo "Mensagem recebida:" . PHP_EOL . $message . PHP_EOL;
                });
//                $conn->close();
            },
            function (Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        );
    }
}
