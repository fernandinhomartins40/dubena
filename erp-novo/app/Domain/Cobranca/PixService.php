<?php

namespace App\Domain\Cobranca;

use App\Domain\Cobranca\Contracts\PixDriver;
use App\Domain\Cobranca\Events\PixConfirmado;
use App\Domain\Integracao\CredencialNaoConfiguradaException;
use App\Domain\Integracao\IntegracaoTenant;
use App\Models\Cobranca\PixCobranca;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\Pedido\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PixService (N7 — GATE bancário). Cria cobrança imediata (txid + copia-e-cola) e
 * processa o WEBHOOK DE FORMA SEGURA — a correção S3 do plano:
 *   - confirma só cobrança ATIVA;
 *   - valida que o VALOR pago bate com o cobrado (binding);
 *   - idempotente (webhook reentregue não baixa em dobro).
 *
 * F6 (multi-tenant): o registro no PSP é do PixDriver, com a credencial resolvida
 * pela EMPRESA DO RECURSO (parcela/pedido) — id explícito, nunca o TenantContext
 * ambient (cobrança pode nascer/renovar em job/cron). Driver real sem credencial
 * da empresa = fail-closed (CredencialNaoConfiguradaException → 503 neutro).
 */
class PixService
{
    public function __construct(
        private PixDriver $driver,
        private IntegracaoTenant $integracao,
    ) {}

    /** Cria uma cobrança PIX imediata para uma parcela. */
    public function criarCobranca(FinanceiroParcela $parcela, int $expiraSegundos = 3600): PixCobranca
    {
        $financeiro = $parcela->financeiro;
        $txid = $this->gerarTxid();
        $emissao = $this->emitirNoPsp((int) $financeiro->empresa_id, $txid, (float) $parcela->valor, $expiraSegundos);

        return PixCobranca::create([
            'empresa_id' => $financeiro->empresa_id,
            'financeiroparcela_id' => $parcela->id,
            'cliente_id' => $financeiro->cliente_id,
            'txid' => $txid,
            'valor' => $parcela->valor,
            'copia_e_cola' => $emissao['copia_e_cola'],
            'qrcode' => $emissao['qrcode'] ?? null,
            'expira_em' => now()->addSeconds($expiraSegundos),
            'situacao' => SituacaoPix::ATIVA->value,
        ]);
    }

    /**
     * Cria (ou reaproveita) a cobrança PIX de um PEDIDO do app — F4. Idempotente:
     * se já existe uma cobrança ATIVA e não vencida para o pedido, devolve-a (evita
     * gerar QR duplicado a cada reabertura da tela). O webhook (processarWebhook)
     * confirma o pagamento e baixa por txid, igual ao fluxo da parcela.
     */
    public function criarCobrancaPedido(Pedido $pedido, int $expiraSegundos = 300): PixCobranca
    {
        $ativa = PixCobranca::query()
            ->where('pedido_id', $pedido->id)
            ->where('situacao', SituacaoPix::ATIVA->value)
            ->where(fn ($q) => $q->whereNull('expira_em')->orWhere('expira_em', '>', now()))
            ->latest('id')->first();
        if ($ativa) {
            return $ativa;
        }

        $txid = $this->gerarTxid();
        $valor = (float) $pedido->valor_venda;
        $emissao = $this->emitirNoPsp((int) $pedido->empresa_id, $txid, $valor, $expiraSegundos);

        return PixCobranca::create([
            'empresa_id' => $pedido->empresa_id,
            'pedido_id' => $pedido->id,
            'cliente_id' => $pedido->cliente_id,
            'txid' => $txid,
            'valor' => $valor,
            'copia_e_cola' => $emissao['copia_e_cola'],
            'qrcode' => $emissao['qrcode'] ?? null,
            'expira_em' => now()->addSeconds($expiraSegundos),
            'situacao' => SituacaoPix::ATIVA->value,
        ]);
    }

    /**
     * Registra a cobrança no PSP via driver, com a credencial da EMPRESA DO
     * RECURSO (id explícito — I1). Driver REAL exige a credencial da empresa
     * (client_id+secret vivem só em empresa_configs; não há env para eles):
     * ausente = fail-closed, em qualquer ambiente.
     *
     * @return array{copia_e_cola:string, qrcode:?string}
     */
    private function emitirNoPsp(int $empresaId, string $txid, float $valor, int $expiraSegundos): array
    {
        $credencial = $this->integracao->pix($empresaId) ?? [];

        if ($this->driver->nome() !== 'fake' && ! $this->integracao->pixConfigurado($empresaId)) {
            throw CredencialNaoConfiguradaException::pix($empresaId);
        }

        return $this->driver->criarCobranca(
            ['txid' => $txid, 'valor' => $valor, 'expira_segundos' => $expiraSegundos],
            $credencial,
        );
    }

    /** Status atual da cobrança PIX de um pedido (para o app fazer polling). */
    public function statusDoPedido(Pedido $pedido): ?PixCobranca
    {
        return PixCobranca::query()->where('pedido_id', $pedido->id)->latest('id')->first();
    }

    /**
     * empresa_id da cobrança de um txid — usado pelo WEBHOOK (público, sem tenant)
     * para descobrir de QUAL empresa é a cobrança e então validar o HMAC DELA.
     * withoutTenant: o webhook roda fora de um tenant resolvido.
     */
    public function empresaIdDoTxid(string $txid): ?int
    {
        $id = PixCobranca::withoutTenant()->where('txid', $txid)->value('empresa_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Processa a notificação de pagamento (webhook). SEGURO: valida estado, valor
     * e idempotência antes de confirmar e baixar a parcela.
     *
     * @param  array{txid:string, valor:float|string, e2eid?:string}  $payload
     */
    public function processarWebhook(array $payload): PixCobranca
    {
        $txid = $payload['txid'] ?? null;
        if (! $txid) {
            throw ValidationException::withMessages(['txid' => 'Payload sem txid.']);
        }

        // confirmadaAgora: true só na transição real ATIVA→CONCLUIDA (não em reentrega).
        $confirmadaAgora = false;

        $cobranca = DB::transaction(function () use ($payload, $txid, &$confirmadaAgora) {
            $cobranca = PixCobranca::withoutTenant()->where('txid', $txid)->lockForUpdate()->first();
            if (! $cobranca) {
                throw ValidationException::withMessages(['txid' => 'Cobrança não encontrada.']);
            }

            // Idempotência: já concluída → devolve sem reprocessar (webhook reentregue).
            if ($cobranca->situacao === SituacaoPix::CONCLUIDA) {
                return $cobranca;
            }

            if ($cobranca->situacao !== SituacaoPix::ATIVA) {
                throw ValidationException::withMessages(['situacao' => 'Cobrança não está ativa.']);
            }

            // Binding de valor: o pago deve bater com o cobrado (anti-fraude S3).
            $valorPago = round((float) $payload['valor'], 2);
            if (abs($valorPago - (float) $cobranca->valor) > 0.001) {
                throw ValidationException::withMessages([
                    'valor' => "Valor pago ({$valorPago}) difere do cobrado ({$cobranca->valor}).",
                ]);
            }

            $cobranca->update([
                'situacao' => SituacaoPix::CONCLUIDA->value,
                'e2eid' => $payload['e2eid'] ?? null,
                'pago_em' => now(),
            ]);

            // Baixa a parcela vinculada.
            if ($cobranca->financeiroparcela_id) {
                FinanceiroParcela::query()->whereKey($cobranca->financeiroparcela_id)->where('baixado', false)->update([
                    'baixado' => true,
                    'valor_efetivado' => $valorPago,
                    'datahora_baixa' => now(),
                ]);
            }

            $confirmadaAgora = true;

            return $cobranca->refresh();
        });

        // Tempo real (P5): notifica o pagamento confirmado APÓS o commit, só na
        // transição real (não em webhook reentregue). Em dev/CI é no-op.
        if ($confirmadaAgora) {
            PixConfirmado::dispatch($cobranca);
        }

        return $cobranca;
    }

    /**
     * Expira as cobranças ATIVAS cujo prazo (expira_em) já passou. Roda no cron
     * `pix:expirar` (espelha o pix:expired do legado). Retorna a quantidade expirada.
     */
    public function expirarVencidas(): int
    {
        return PixCobranca::withoutTenant()
            ->where('situacao', SituacaoPix::ATIVA->value)
            ->whereNotNull('expira_em')
            ->where('expira_em', '<', now())
            ->update(['situacao' => SituacaoPix::EXPIRADA->value]);
    }

    private function gerarTxid(): string
    {
        // txid: 26-35 chars alfanuméricos (regra do BACEN).
        return Str::lower(Str::random(32));
    }
}
