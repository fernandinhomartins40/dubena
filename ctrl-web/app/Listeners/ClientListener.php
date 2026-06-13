<?php

namespace App\Listeners;

use App\Events\NotifySGC;
use App\Helpers\Utils\Util;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ClientListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  SomeEvent  $event
     * @return void
     */
    public function handle(NotifySGC $event)
    {
        Util::log($event->message, $event->level);
    }
}
