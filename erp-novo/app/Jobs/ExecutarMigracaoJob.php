<?php

namespace App\Jobs;

use App\Models\Migracao\Migracao;
use App\Models\Saas\PlatformAdmin;
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

    /** ETL é um ato de plataforma declarado, nunca um bypass implícito de tenant. */
    public bool $platformJob = true;

    public int $timeout = 21600;   // 6h: carga de milhões de linhas

    public int $tries = 1;         // sem retry automático: recarga é decisão humana

    /**
     * Fila dedicada a jobs longos (T3.3).
     *
     * A conexão default tem `retry_after = 90s`: com o timeout de 6 h acima, a
     * fila reentregaria este job a cada 90 segundos ENQUANTO ELE AINDA RODA —
     * dezenas de ETLs simultâneos escrevendo no mesmo banco. `redis-longo` usa
     * 7 h, com folga sobre o timeout.
     *
     * Sem isto, o `$tries = 1` acima dá uma falsa sensação de segurança: ele
     * impede o RETRY após falha, não a REENTREGA por prazo estourado.
     */
    public function __construct(
        public int $migracaoId,
        public array $apenas = [],
        public int $platformAdminId = 0,
    ) {
        // Fila dedicada definida no CONSTRUTOR, não como propriedade: a trait
        // `Illuminate\Bus\Queueable` já declara `$connection` e `$queue`, e
        // redeclará-las (com ou sem tipo, com ou sem default) é erro fatal de
        // composição em PHP. `onConnection`/`onQueue` são o caminho idiomático.
        $this->onConnection('redis-longo')->onQueue('migracao');
    }

    public function handle(MigracaoService $service): void
    {
        $migracao = Migracao::find($this->migracaoId);
        if ($migracao === null) {
            return;
        }
        $admin = PlatformAdmin::query()->find($this->platformAdminId);
        if ($this->platformAdminId <= 0
            || $migracao->platform_admin_id !== $this->platformAdminId
            || $admin === null
            || ! $admin->ativo) {
            throw new \LogicException('Job de migração de plataforma sem administrador ativo e correspondente.');
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
