<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Agendamentos (N12) — recriados do legado. Em produção, o cron chama
| `php artisan schedule:run` a cada minuto.
*/

// Alertas diários (estoque baixo) — 07:00.
Schedule::command('notify:alertas')->dailyAt('07:00')->withoutOverlapping();

// Sync de posições GPS (SGCasa) — a cada minuto (gate externo; só roda se configurado).
Schedule::command('monitora:sync-positions')->everyMinute()->withoutOverlapping();
