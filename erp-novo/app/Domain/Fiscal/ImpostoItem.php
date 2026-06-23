<?php

namespace App\Domain\Fiscal;

/**
 * DTO do imposto calculado de UM item (N9 / C7a). Número cru.
 *
 * Os campos de ST/FCP/DIFAL/redução são opcionais (default 0) — itens simples
 * (CST 00 sem ST) os deixam zerados; o porte completo do legado os preenche.
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
        // ── C7a: porte completo ──
        public readonly float $baseIcmsSt = 0.0,
        public readonly float $aliqIcmsSt = 0.0,
        public readonly float $valorIcmsSt = 0.0,
        public readonly float $mvaSt = 0.0,
        public readonly float $percRedBc = 0.0,
        public readonly float $percRedBcSt = 0.0,
        public readonly float $baseFcp = 0.0,
        public readonly float $aliqFcp = 0.0,
        public readonly float $valorFcp = 0.0,
        public readonly float $valorIcmsDeson = 0.0,
        public readonly float $valorDifalDest = 0.0,
        public readonly float $valorDifalRemet = 0.0,
        public readonly float $valorFcpDifal = 0.0,
    ) {}

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
            'bc_icms_st' => $this->baseIcmsSt,
            'aliq_icms_st' => $this->aliqIcmsSt,
            'valor_icms_st' => $this->valorIcmsSt,
            'mva_st' => $this->mvaSt,
            'perc_red_bc' => $this->percRedBc,
            'perc_red_bc_st' => $this->percRedBcSt,
            'bc_fcp' => $this->baseFcp,
            'aliq_fcp' => $this->aliqFcp,
            'valor_fcp' => $this->valorFcp,
            'valor_icms_deson' => $this->valorIcmsDeson,
            'valor_difal_dest' => $this->valorDifalDest,
            'valor_difal_remet' => $this->valorDifalRemet,
            'valor_fcp_difal' => $this->valorFcpDifal,
        ];
    }
}
