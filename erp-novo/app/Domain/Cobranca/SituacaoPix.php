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
}
