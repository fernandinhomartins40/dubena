<?php

namespace App\Console\Commands;

use App\Console\Concerns\RelataAutomacao;
use App\Domain\Cobranca\PixService;
use App\Domain\Tenant\AutomacaoPorEmpresa;
use Illuminate\Console\Command;

/**
 * pix:expirar (C9) — cron (a cada minuto) que expira as cobranças PIX ativas
 * vencidas. Espelha o pix:expired do legado.
 */
class PixExpirar extends Command
{
    protected $signature = 'pix:expirar';

    protected $description = 'Expira cobranças PIX ativas cujo prazo já passou.';

    use RelataAutomacao;

    public function handle(PixService $pix, AutomacaoPorEmpresa $automacao): int
    {
        // Este comando GRAVA, então roda com a identidade de automação de cada
        // tenant, e não pela conexão de owner: o UPDATE passa pela RLS e só
        // alcança cobrança do próprio tenant. Pelo runtime sem envelope ele
        // não alcançava nenhuma — cobrança vencida ficava ATIVA para sempre.
        //
        // `expirarVencidas()` não filtra por empresa: a primeira empresa de um
        // tenant expira as de todas as dele, e as seguintes acham zero. A soma
        // fecha.
        $resultado = $automacao->paraCada(fn () => $pix->expirarVencidas());

        $this->info("{$resultado->total()} cobrança(s) PIX expirada(s).");

        return $this->relatar($resultado);
    }
}
