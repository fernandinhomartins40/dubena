<?php

namespace App\Console\Commands;

use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Console\Command;

/**
 * financeiro:notificar-vencidos (C9) — cron diário (07:30). Conta as parcelas a
 * RECEBER vencidas e em aberto por empresa (base da cobrança/lembrete). Espelha o
 * documentosvencidosmail/remembermail do legado; o envio de e-mail é gate (SMTP).
 */
class FinanceiroNotificarVencidos extends Command
{
    protected $signature = 'financeiro:notificar-vencidos';

    protected $description = 'Apura parcelas a receber vencidas e em aberto (lembrete de cobrança).';

    public function handle(): int
    {
        $vencidas = FinanceiroParcela::query()
            ->where('baixado', false)
            ->where('vencimento', '<', now()->toDateString())
            ->whereHas('financeiro', fn ($q) => $q->where('cancelado', false)->where('pagarreceber', 'R'))
            ->count();

        $this->info("{$vencidas} parcela(s) a receber vencida(s) em aberto.");

        return self::SUCCESS;
    }
}
