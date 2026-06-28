<?php

namespace App\Domain\Mobile\Jobs;

use App\Domain\Mobile\Contracts\PushTransport;
use App\Domain\Tenant\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * EnviarPushJob (P0) — entrega de push fora do request, na fila.
 *
 * Antes, o PushService chamava o FCM de forma SÍNCRONA dentro do request (uma
 * chamada HTTP de rede no caminho crítico de mudar status do pedido). Agora a
 * entrega é enfileirada: o request responde imediatamente e o envio (FCM HTTP v1,
 * via PushTransport) ocorre no worker, com retry/backoff da fila.
 *
 * Tenant-aware: re-aplica o tenant capturado no dispatch (TenantAwareJob), embora
 * o envio em si seja por token (sem leitura tenant-scoped) — mantém a barreira
 * caso o transporte futuro consulte dados do tenant.
 */
class EnviarPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAwareJob;

    /** @var int tentativas antes de falhar (entrega de push é best-effort). */
    public int $tries = 3;

    /** @var int backoff (s) entre tentativas. */
    public int $backoff = 10;

    /**
     * @param  list<string>  $tokens
     * @param  array<string,mixed>  $dados
     */
    public function __construct(
        public array $tokens,
        public string $titulo,
        public string $corpo,
        public array $dados = [],
    ) {
        $this->capturarTenant();
    }

    public function handle(PushTransport $transport): void
    {
        $this->aplicarTenant();

        $transport->enviar($this->tokens, $this->titulo, $this->corpo, $this->dados);
    }
}
