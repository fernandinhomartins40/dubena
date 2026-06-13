<?php

namespace App\Jobs;

use App\Processors\MobileAppProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessPixPedido implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int $pedidoapi_id
     */
    public $pedidoapi_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($pedidoapi_id)
    {
        $this->pedidoapi_id = $pedidoapi_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $processor = new MobileAppProcessor();

        $processor->createPixOrder($this->pedidoapi_id);
    }
}
