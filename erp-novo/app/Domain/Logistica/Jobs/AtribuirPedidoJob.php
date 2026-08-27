<?php

namespace App\Domain\Logistica\Jobs;

use App\Domain\Logistica\CentralService;
use App\Domain\Logistica\DistribuidorService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Tenant\TenantContext;
use App\Models\Logistica\LogisticaConfig;
use App\Models\Pedido\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * AtribuirPedidoJob (L3) — auto-atribuição. Disparado quando um pedido PENDENTE
 * entra na fila. Só age se a empresa está em modo `auto` (LogisticaConfig); em
 * `sugerir` (default), não faz nada — o operador confirma na Central.
 *
 * Resolve o tenant explicitamente (job roda fora do request, sem middleware de
 * tenant). Idempotente: se o pedido já tem entregador ou saiu de PENDENTE, aborta.
 */
class AtribuirPedidoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** Espera antes da segunda tentativa. */
    public int $backoff = 30;

    /** Distribuição consulta base e calcula rota: limite generoso, mas limite. */
    public int $timeout = 90;

    public function __construct(public int $pedidoId, public int $empresaId, public int $grupoId) {}

    public function handle(CentralService $central, DistribuidorService $distribuidor, TenantContext $tenant): void
    {
        $tenant->set($this->empresaId, $this->grupoId);

        $config = LogisticaConfig::query()->where('empresa_id', $this->empresaId)->first();
        if ($config === null || ! $config->ehAuto()) {
            return; // modo sugerir: decisão é do operador.
        }

        $pedido = Pedido::query()->with(['cliente', 'situacao'])->find($this->pedidoId);
        if ($pedido === null
            || $pedido->entregador_user_id !== null
            || $pedido->situacao?->efeito !== EfeitoPedido::PENDENTE) {
            return; // já atribuído ou não está mais na fila.
        }

        $melhor = $distribuidor->melhorEntregador($pedido);
        if ($melhor === null) {
            return; // ninguém elegível agora; fica na fila para o operador.
        }

        $central->atribuir(
            $pedido,
            $this->empresaId,
            (int) $melhor['entregador_user_id'],
            $melhor['veiculo_id'] ?? null,
            null,
            true, // automático
            'Auto-atribuição (distância/carga)',
        );
    }

    /**
     * Esgotadas as tentativas, o pedido NÃO foi atribuído automaticamente.
     *
     * Não é perda de dado — o pedido continua na fila da central e o operador
     * atribui à mão. Mas precisa ficar registrado: uma falha recorrente aqui
     * significa que a distribuição automática parou de funcionar, e a operação
     * só notaria pelo acúmulo na fila.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('auto-atribuicao falhou; pedido segue na fila para o operador', [
            'pedido_id' => $this->pedidoId,
            'empresa_id' => $this->empresaId,
            'erro' => $e->getMessage(),
        ]);
    }
}
