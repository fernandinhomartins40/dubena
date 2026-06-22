<?php

namespace App\Domain\Mobile;

/**
 * Situação de uma transação de pagamento online (Rede) — N10.
 */
enum SituacaoPagamento: string
{
    case PENDENTE = 'PENDENTE';
    case AUTORIZADO = 'AUTORIZADO';
    case CAPTURADO = 'CAPTURADO';
    case NEGADO = 'NEGADO';
    case CANCELADO = 'CANCELADO';
    case ESTORNADO = 'ESTORNADO';

    public function aprovado(): bool
    {
        return $this === self::AUTORIZADO || $this === self::CAPTURADO;
    }
}
