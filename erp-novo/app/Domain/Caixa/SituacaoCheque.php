<?php

namespace App\Domain\Caixa;

/**
 * Máquina de estados do cheque (N6). Transições válidas explícitas
 * (substitui o controle implícito do legado).
 */
enum SituacaoCheque: string
{
    case CARTEIRA = 'CARTEIRA';       // em mãos
    case DEPOSITADO = 'DEPOSITADO';   // enviado ao banco
    case COMPENSADO = 'COMPENSADO';   // crédito confirmado
    case DEVOLVIDO = 'DEVOLVIDO';     // sem fundos/erro
    case REPASSADO = 'REPASSADO';     // repassado a terceiro
    case CANCELADO = 'CANCELADO';

    /** @return list<SituacaoCheque> destinos válidos a partir deste estado */
    public function transicoes(): array
    {
        return match ($this) {
            self::CARTEIRA => [self::DEPOSITADO, self::REPASSADO, self::CANCELADO],
            self::DEPOSITADO => [self::COMPENSADO, self::DEVOLVIDO],
            self::DEVOLVIDO => [self::DEPOSITADO, self::REPASSADO, self::CANCELADO],
            self::COMPENSADO, self::REPASSADO, self::CANCELADO => [],
        };
    }

    public function podeIrPara(SituacaoCheque $destino): bool
    {
        return in_array($destino, $this->transicoes(), true);
    }
}
