<?php

namespace App\Domain\Fiscal;

/**
 * DTO do imposto calculado de UM item (N9). Número cru.
 */
final class ImpostoItem
{
    public function __construct(
        public readonly float $baseIcms,
        public readonly float $aliqIcms,
        public readonly float $valorIcms,
        public readonly float $aliqPis,
        public readonly float $valorPis,
        public readonly float $aliqCofins,
        public readonly float $valorCofins,
        public readonly float $aliqIpi,
        public readonly float $valorIpi,
    ) {
    }

    /** @return array<string,float> */
    public function toArray(): array
    {
        return [
            'bc_icms' => $this->baseIcms,
            'aliq_icms' => $this->aliqIcms,
            'valor_icms' => $this->valorIcms,
            'aliq_pis' => $this->aliqPis,
            'valor_pis' => $this->valorPis,
            'aliq_cofins' => $this->aliqCofins,
            'valor_cofins' => $this->valorCofins,
            'aliq_ipi' => $this->aliqIpi,
            'valor_ipi' => $this->valorIpi,
        ];
    }
}
