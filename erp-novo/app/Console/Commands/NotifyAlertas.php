<?php

namespace App\Console\Commands;

use App\Domain\Relatorio\NotificarEstoqueBaixoJob;
use App\Models\Empresa;
use Illuminate\Console\Command;

/**
 * notify:alertas (N12) — cron diário (07:00). Enfileira a notificação de estoque
 * baixo por empresa ativa. Substitui o notify:alertas do legado.
 */
class NotifyAlertas extends Command
{
    protected $signature = 'notify:alertas';

    protected $description = 'Enfileira alertas diários (estoque baixo) por empresa.';

    public function handle(): int
    {
        $empresas = Empresa::query()->where('ativo', true)->pluck('id');

        foreach ($empresas as $empresaId) {
            NotificarEstoqueBaixoJob::dispatch($empresaId);
        }

        $this->info("Alertas enfileirados para {$empresas->count()} empresa(s).");

        return self::SUCCESS;
    }
}
