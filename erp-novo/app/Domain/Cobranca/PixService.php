<?php

namespace App\Domain\Cobranca;

use App\Models\Cobranca\PixCobranca;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PixService (N7 — GATE, Itaú). Cria cobrança imediata (txid + copia-e-cola) e
 * processa o WEBHOOK DE FORMA SEGURA — a correção S3 do plano:
 *   - confirma só cobrança ATIVA;
 *   - valida que o VALOR pago bate com o cobrado (binding);
 *   - idempotente (webhook reentregue não baixa em dobro).
 * A comunicação real com o PSP (registrar cobrança, validar assinatura HMAC do
 * webhook) fica isolada para o gate de homologação.
 */
class PixService
{
    /** Cria uma cobrança PIX imediata para uma parcela. */
    public function criarCobranca(FinanceiroParcela $parcela, int $expiraSegundos = 3600): PixCobranca
    {
        $financeiro = $parcela->financeiro;
        $txid = $this->gerarTxid();

        return PixCobranca::create([
            'empresa_id' => $financeiro->empresa_id,
            'financeiroparcela_id' => $parcela->id,
            'cliente_id' => $financeiro->cliente_id,
            'txid' => $txid,
            'valor' => $parcela->valor,
            'copia_e_cola' => $this->montarBrCode($txid, (float) $parcela->valor),
            'expira_em' => now()->addSeconds($expiraSegundos),
            'situacao' => SituacaoPix::ATIVA->value,
        ]);
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

        return DB::transaction(function () use ($payload, $txid) {
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

            return $cobranca->refresh();
        });
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

    private function montarBrCode(string $txid, float $valor): string
    {
        // Representação simplificada do payload EMV (BR Code). Em produção o PSP
        // devolve o copia-e-cola assinado; aqui é determinístico para teste/exibição.
        return sprintf('00020126%s52040000530398654%02d%.2f5802BR6009GASEMCASA62%02d%s6304',
            strlen($txid) + 22, strlen(number_format($valor, 2, '.', '')) + 0, $valor, strlen($txid) + 4, $txid);
    }
}
