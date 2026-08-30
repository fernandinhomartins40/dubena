<?php

namespace App\Domain\Mobile\Jobs;

use App\Domain\Mobile\Contracts\PushTransport;
use App\Domain\Tenant\TenantAccessDeniedException;
use App\Domain\Tenant\TenantEnvelopeDispatch;
use App\Domain\Tenant\TenantEnvelopeJob;
use App\Domain\Tenant\TenantEnvelopeRuntime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantEnvelopeJob;

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
        public bool $platformJob = false,
    ) {
        if (config('saas_transformation.enforcement.tenant_envelope') && ! $this->platformJob) {
            $envelope = app(TenantEnvelopeDispatch::class)->captureOrNull();
            if ($envelope !== null) {
                $this->captureTenantEnvelope($envelope);
            }
        }
    }

    /** Uma chamada de push pendurada não pode segurar o worker. */
    public int $timeout = 60;

    public function handle(PushTransport $transport, ?TenantEnvelopeRuntime $runtime = null): void
    {
        if ($this->tenantEnvelopePayload !== null) {
            $this->withinTenantEnvelope($runtime ?? app(TenantEnvelopeRuntime::class), fn (): mixed => $transport->enviar($this->tokens, $this->titulo, $this->corpo, $this->dados));

            return;
        }
        if (config('saas_transformation.enforcement.tenant_envelope') && ! $this->platformJob) {
            throw new TenantAccessDeniedException('Push de negocio sem TenantEnvelope serializado.');
        }
        $transport->enviar($this->tokens, $this->titulo, $this->corpo, $this->dados);
    }

    public static function forPlatform(array $tokens, string $titulo, string $corpo, array $dados = []): self
    {
        return new self($tokens, $titulo, $corpo, $dados, true);
    }

    /**
     * Esgotadas as tentativas, a notificação NÃO chegou ao aparelho.
     *
     * Push é best-effort por natureza, mas silêncio total não serve: no fluxo
     * do pedido é assim que o cliente sabe que a entrega saiu. Sem este log, a
     * única pista ficaria em `failed_jobs`, que ninguém lê no dia a dia.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('push: envio falhou apos todas as tentativas', [
            'titulo' => $this->titulo,
            'destinatarios' => count($this->tokens),
            'erro' => $e->getMessage(),
        ]);
    }
}
