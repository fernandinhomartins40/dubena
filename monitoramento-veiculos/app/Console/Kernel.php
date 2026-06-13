<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
         Commands\UpdateClientsLocation::class,
         Commands\SyncPosicoesSGCasa::class, // FASE 4: substitui o módulo integration/ legado
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
         $schedule->command('update-clients-location')->everyMinute();
         // FASE 4: sincronização de posições do SGCasa (substitui o cron do integration/).
         $schedule->command('sync:posicoes-sgcasa')->everyMinute()->withoutOverlapping();
    }
}
