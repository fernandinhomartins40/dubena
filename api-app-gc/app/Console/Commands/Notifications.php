<?php

namespace App\Console\Commands;

use App\Http\Resources\ApiResources;
use App\Services\CarbonCustom;
use App\User;
use Artisan;
use Illuminate\Console\Command;
use Input;

class Notifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $to = [env('FCM_ID'), env('FCM_ID_2'), env('FCM_ID_3')];
        define("IS_CURL_OPT", true);
        return ApiResources::notifyDevices("Teste", "Estamos Testando", $to);
    }
}
