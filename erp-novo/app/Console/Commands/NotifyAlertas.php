<?php

namespace App\Console\Commands;

use App\Console\Concerns\RelataAutomacao;
use App\Domain\Relatorio\NotificarEstoqueBaixoJob;
use App\Domain\Tenant\AutomacaoPorEmpresa;
use Illuminate\Console\Command;

/**
 * notify:alertas (N12) — cron diário (07:00). Enfileira a notificação de estoque
 * baixo por empresa ativa. Substitui o notify:alertas do legado.
 *
 * Este comando recusava rodar com o enforcement ligado: o cron não tinha
 * identidade, e escolher um usuário em nome da empresa não era aceitável. Agora
 * a identidade existe (`IdentidadeDeAutomacao`). O job é despachado DENTRO do
 * envelope dela, que é o que ele captura e confere no `handle()` — sem
 * identidade provisionada a empresa é pulada, e nenhum job é enfileirado.
 */
class NotifyAlertas extends Command
{
    use RelataAutomacao;

    protected $signature = 'notify:alertas';

    protected $description = 'Enfileira alertas diários (estoque baixo) por empresa.';

    public function handle(AutomacaoPorEmpresa $automacao): int
    {
        $resultado = $automacao->paraCada(function (int $empresaId): int {
            NotificarEstoqueBaixoJob::dispatch($empresaId);

            return 1;
        });

        $this->info("Alertas enfileirados para {$resultado->processadas()} empresa(s).");

        return $this->relatar($resultado);
    }
}
