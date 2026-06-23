<?php

namespace App\Console\Commands;

use App\Domain\Cobranca\PixService;
use Illuminate\Console\Command;

/**
 * pix:expirar (C9) — cron (a cada minuto) que expira as cobranças PIX ativas
 * vencidas. Espelha o pix:expired do legado.
 */
class PixExpirar extends Command
{
    protected $signature = 'pix:expirar';

    protected $description = 'Expira cobranças PIX ativas cujo prazo já passou.';

    public function handle(PixService $pix): int
    {
        $qtd = $pix->expirarVencidas();
        $this->info("{$qtd} cobrança(s) PIX expirada(s).");

        return self::SUCCESS;
    }
}
