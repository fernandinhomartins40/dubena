<?php

namespace App\Console\Commands;

use App\Console\Concerns\RelataAutomacao;
use App\Domain\Monitora\MonitoraSyncService;
use App\Domain\Tenant\AutomacaoPorEmpresa;
use Illuminate\Console\Command;

/**
 * monitora:sync-positions (N12/N11) — cron a cada minuto. Sincroniza posições do
 * SGCasa por empresa ativa (gate externo; sem driver real configurado, retorna 0).
 * Substitui o report:positions do legado.
 *
 * Passa pelas empresas com a identidade de automação do tenant: listando-as
 * pelo runtime, sob RLS e sem envelope, o laço não tinha nenhuma volta — o
 * comando saía com "Posições ingeridas: 0" a cada 30 segundos, para sempre.
 */
class MonitoraSyncPositions extends Command
{
    use RelataAutomacao;

    protected $signature = 'monitora:sync-positions';

    protected $description = 'Sincroniza posições de GPS (SGCasa) por empresa.';

    public function handle(MonitoraSyncService $sync, AutomacaoPorEmpresa $automacao): int
    {
        $resultado = $automacao->paraCada(fn (int $empresaId) => $sync->sincronizar($empresaId));

        $this->info("Posições ingeridas: {$resultado->total()} em {$resultado->processadas()} empresa(s).");

        return $this->relatar($resultado);
    }
}
