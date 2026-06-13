<?php

namespace App\Console\Commands;

use App\Helpers\Utils\Util;
use App\Http\Resources\ApiResources;
use App\Services\CarbonCustom as Carbon;
use Illuminate\Console\Command;

class OrderStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update API orders.';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $testing;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->testing = (boolean) env("TRACK_SIMULATOR", "0");
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->send();
    }

    public function send()
    {
        try {
            if ($this->testing) {
                // autoupdate
            }

            $sent = ApiResources::sendOrders();
            if ($sent == "Sucesso" || $sent == "Nenhum Pedido") {
                $this->info(Carbon::now()->toDateTimeString() . ": " . $sent);
            } else {
                throw new \Exception($sent);
            }
        } catch (\Exception $e) {
            $this->info(Carbon::now()->toDateTimeString() . ": " . $e->getMessage());
            // Util::notify(Carbon::now()->toDateTimeString() . ": " . $e->getMessage());
            Util::log($e->getMessage());
        }
    }
}
