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
        // Commands\Inspire::class,
        Commands\MigrateApiModule::class, // FASE 5/6: migra o módulo Api (schema 'api')
        Commands\MigrateMonitoraModule::class, // UNIFICAÇÃO: migra o módulo Monitoramento (schema 'monitora')
        \App\Monitora\Console\Commands\SyncPosicoesSGCasa::class, // jobs do monitoramento (GPS)
        \App\Monitora\Console\Commands\UpdateClientsLocation::class,
        Commands\Notificacao::class,
        Commands\DeleteNotificacoes::class,
        Commands\ProcessIbptFiles::class,
        Commands\UpdateTabelaIbpt::class,
        Commands\CFeWs::class,
        Commands\SendRememberMail::class,
        Commands\OrderStatus::class,
        Commands\NotifyApp::class,
        Commands\PixCancelExpired::class,
        Commands\SendDocumentosVencidosMail::class,
        Commands\SendVendaDiariaMail::class,
        Commands\CheckInconsistencies::class,
        Commands\CheckVehiclePosition::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        putenv("ORACLE_HOME=/u01/app/oracle/product/11.2.0/xe");
        $logPath = storage_path() . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
        $schedule->command('notify:alertas')->dailyAt('07:00')->appendOutputTo($logPath . 'insert.log');
        $schedule->command('vendadiaria:send')->dailyAt('07:15')->appendOutputTo($logPath . 'vendadiariamail.log');
        $schedule->command('notify:delete')->dailyAt('06:00')->appendOutputTo($logPath . 'delete.log');
        $schedule->command('ibpt:update')->dailyAt('05:00')->appendOutputTo($logPath . 'ibpt_update.log');
        $schedule->command('remembermail:send')->weeklyOn(1, '03:00')->appendOutputTo($logPath . 'remembermail.log');
        $schedule->command("order:send")
            ->everyMinute()
            ->appendOutputTo(storage_path('logs' . DIRECTORY_SEPARATOR . now()->toDateString() . '-orders.log'));
        $schedule->command("pix:expired")->everyMinute()->appendOutputTo($logPath . 'pix-expired.log');
        $schedule->command('documentosvencidosmail:send')->dailyAt('07:30')->appendOutputTo($logPath . 'docvencidosmail.log');
        $schedule->command('notify:inconsistencies')->weekly()->mondays()->at("03:00");
        $schedule->command('report:positions')->everyMinute()->appendOutputTo($logPath . 'positions.log');
    }
}
