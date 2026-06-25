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

// Expira cobranças PIX vencidas — a cada minuto (espelha pix:expired do legado). C9.
Schedule::command('pix:expirar')->everyMinute()->withoutOverlapping();

// Apura parcelas a receber vencidas (lembrete de cobrança) — 07:30. C9.
Schedule::command('financeiro:notificar-vencidos')->dailyAt('07:30')->withoutOverlapping();

// Venda diária por empresa — 07:15 (base do e-mail; envio é gate SMTP). C9.
Schedule::command('vendas:diaria')->dailyAt('07:15')->withoutOverlapping();

// Inconsistências de saldo (estoque/caixa) — segunda 03:00. C9.
Schedule::command('notify:inconsistencias')->weeklyOn(1, '03:00')->withoutOverlapping();

// Atualização da tabela IBPT (Lei 12.741) — dia 1 de cada mês 05:00 (gate: só roda
// efetivamente se IBPT_CSV_URL estiver configurada). Espelha o ibpt:update do legado. F09.
Schedule::command('ibpt:atualizar')->monthlyOn(1, '05:00')->withoutOverlapping();
