<?php

namespace App\Domain\Fiscal;

/**
 * Máquina de estados da nota fiscal (N9).
 */
enum SituacaoNota: string
{
    case RASCUNHO = 'RASCUNHO';     // montada, não transmitida
    case EMITIDA = 'EMITIDA';       // transmitida, aguardando SEFAZ
    case AUTORIZADA = 'AUTORIZADA'; // autorizada pela SEFAZ (com protocolo)
    case REJEITADA = 'REJEITADA';
    case DENEGADA = 'DENEGADA';
    case CANCELADA = 'CANCELADA';

    public function autorizada(): bool
    {
        return $this === self::AUTORIZADA;
    }

    public function podeCancelar(): bool
    {
        return $this === self::AUTORIZADA;
    }
}
