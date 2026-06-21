<?php

namespace App\Domain\Shared;

use Carbon\CarbonImmutable;

/**
 * DTO de uma parcela calculada (substitui os arrays posicionais do legado).
 * Valor é float (número cru), data é CarbonImmutable.
 */
final class Parcela
{
    public function __construct(
        public readonly int $numero,
        public readonly CarbonImmutable $vencimento,
        public readonly float $valor,
        public readonly float $desconto = 0.0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'numero' => $this->numero,
            'vencimento' => $this->vencimento->toDateString(),
            'valor' => $this->valor,
            'desconto' => $this->desconto,
        ];
    }
}
