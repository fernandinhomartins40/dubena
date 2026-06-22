<?php

namespace App\Domain\Satelite;

/**
 * Máquina de estados do vale-gás (cupom pré-pago) — N8.
 */
enum SituacaoValeGas: string
{
    case EMITIDO = 'EMITIDO';       // gerado, aguardando pagamento
    case PAGO = 'PAGO';             // pago (financeiro baixado), pronto para uso
    case UTILIZADO = 'UTILIZADO';   // resgatado num pedido
    case CANCELADO = 'CANCELADO';
    case EXPIRADO = 'EXPIRADO';

    /** @return list<SituacaoValeGas> */
    public function transicoes(): array
    {
        return match ($this) {
            self::EMITIDO => [self::PAGO, self::CANCELADO, self::EXPIRADO],
            self::PAGO => [self::UTILIZADO, self::CANCELADO, self::EXPIRADO],
            self::UTILIZADO, self::CANCELADO, self::EXPIRADO => [],
        };
    }

    public function podeIrPara(SituacaoValeGas $destino): bool
    {
        return in_array($destino, $this->transicoes(), true);
    }
}
