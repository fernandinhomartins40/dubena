<?php

namespace App\Domain\Cobranca;

/**
 * Situação de uma cobrança PIX (N7).
 */
enum SituacaoPix: string
{
    case ATIVA = 'ATIVA';             // cobrança gerada, aguardando pagamento
    case CONCLUIDA = 'CONCLUIDA';     // paga (webhook confirmou)
    case REMOVIDA_PSP = 'REMOVIDA_PSP';
    case EXPIRADA = 'EXPIRADA';
    // PIE-24: o PSP confirmou pagamento de uma cobrança que já tinha expirado
    // AQUI (o cron `pix:expirar` roda pelo relógio local). O dinheiro entrou;
    // a baixa não é automática porque a parcela pode ter sido recebida por
    // outro meio no intervalo — fica para a conciliação (decisão pendente).
    case PAGA_APOS_EXPIRAR = 'PAGA_APOS_EXPIRAR';
}
