<?php

namespace App\Console\Commands;

use App\Domain\Monitora\MonitoraSyncService;
use App\Models\Empresa;
use Illuminate\Console\Command;

/**
 * monitora:sync-positions (N12/N11) — cron a cada minuto. Sincroniza posições do
 * SGCasa por empresa ativa (gate externo; sem driver real configurado, retorna 0).
 * Substitui o report:positions do legado.
 */
class MonitoraSyncPositions extends Command
{
    protected $signature = 'monitora:sync-positions';

    protected $description = 'Sincroniza posições de GPS (SGCasa) por empresa.';

    public function handle(MonitoraSyncService $sync): int
    {
        $total = 0;
        foreach (Empresa::query()->where('ativo', true)->pluck('id') as $empresaId) {
            $total += $sync->sincronizar((int) $empresaId);
        }

        $this->info("Posições ingeridas: {$total}.");

        return self::SUCCESS;
    }
}
