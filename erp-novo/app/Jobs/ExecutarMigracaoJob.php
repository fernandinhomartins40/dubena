<?php

namespace App\Jobs;

use App\Models\Migracao\Migracao;
use App\Services\Migracao\MigracaoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Executa uma migração em segundo plano.
 *
 * Em fila porque uma migração real não cabe numa requisição HTTP: o caso Dubena
 * levou horas e 16 milhões de linhas. O progresso é gravado na própria
 * `migracoes` para a tela poder acompanhar.
 */
class ExecutarMigracaoJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 21600;   // 6h: carga de milhões de linhas

    public int $tries = 1;         // sem retry automático: recarga é decisão humana

    public function __construct(
        public int $migracaoId,
        public array $apenas = [],
    ) {
    }

    public function handle(MigracaoService $service): void
    {
        $migracao = Migracao::find($this->migracaoId);
        if ($migracao === null) {
            return;
        }

        $migracao->update([
            'status' => Migracao::STATUS_MIGRANDO,
            'iniciada_em' => now(),
            'erro' => null,
            'progresso' => 0,
        ]);

        try {
            $resultado = $service->executar($migracao, $this->apenas);

            $migracao->update([
                'status' => Migracao::STATUS_CONCLUIDA,
                'resultado' => $resultado,
                'concluida_em' => now(),
                'progresso' => 100,
            ]);
        } catch (\Throwable $e) {
            $migracao->update([
                'status' => Migracao::STATUS_FALHOU,
                'erro' => mb_substr($e->getMessage(), 0, 2000),
                'concluida_em' => now(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Migracao::where('id', $this->migracaoId)->update([
            'status' => Migracao::STATUS_FALHOU,
            'erro' => mb_substr($e->getMessage(), 0, 2000),
        ]);
    }
}
