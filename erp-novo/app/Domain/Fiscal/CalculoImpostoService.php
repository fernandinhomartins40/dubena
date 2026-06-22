<?php

namespace App\Domain\Fiscal;

/**
 * CalculoImpostoService (N9) — PORTADO da lógica legislada (NfeImpostoProcessor/
 * IcmsBase do legado). Calcula ICMS/PIS/COFINS/IPI por item. Legislado = entra com
 * BASELINE (casos de ouro) antes do merge; não se reinventa a regra.
 *
 * Regras-base (simplificadas para o núcleo testável; ST/FCP/diferimento entram por
 * extensão conforme o caso de ouro):
 *   - base ICMS = (qtd*unitário) - desconto + frete (do item), salvo CST isento;
 *   - ICMS = base * aliq;
 *   - PIS/COFINS = base * aliq (regime não-cumulativo: 1,65% / 7,6%; cumulativo varia);
 *   - IPI = (qtd*unitário) * aliq.
 */
class CalculoImpostoService
{
    /** CSTs que zeram a base de ICMS (isento/não tributado/diferido). */
    private const CST_SEM_ICMS = ['40', '41', '50', '51', '60'];

    /**
     * @param array{
     *   quantidade: float, valor_unitario: float, desconto?: float, frete?: float,
     *   cst_icms?: string, aliq_icms?: float, aliq_pis?: float, aliq_cofins?: float, aliq_ipi?: float
     * } $item
     */
    public function calcular(array $item): ImpostoItem
    {
        $bruto = round($item['quantidade'] * $item['valor_unitario'], 2);
        $desconto = round((float) ($item['desconto'] ?? 0), 2);
        $frete = round((float) ($item['frete'] ?? 0), 2);
        $liquido = round($bruto - $desconto + $frete, 2);

        $cst = (string) ($item['cst_icms'] ?? '00');
        $aliqIcms = (float) ($item['aliq_icms'] ?? 0);
        $semIcms = in_array($cst, self::CST_SEM_ICMS, true);

        $baseIcms = $semIcms ? 0.0 : $liquido;
        $valorIcms = round($baseIcms * $aliqIcms / 100, 2);

        // PIS/COFINS incidem sobre o líquido.
        $aliqPis = (float) ($item['aliq_pis'] ?? 0);
        $aliqCofins = (float) ($item['aliq_cofins'] ?? 0);
        $valorPis = round($liquido * $aliqPis / 100, 2);
        $valorCofins = round($liquido * $aliqCofins / 100, 2);

        // IPI sobre o valor bruto do item.
        $aliqIpi = (float) ($item['aliq_ipi'] ?? 0);
        $valorIpi = round($bruto * $aliqIpi / 100, 2);

        return new ImpostoItem(
            baseIcms: $baseIcms,
            aliqIcms: $aliqIcms,
            valorIcms: $valorIcms,
            aliqPis: $aliqPis,
            valorPis: $valorPis,
            aliqCofins: $aliqCofins,
            valorCofins: $valorCofins,
            aliqIpi: $aliqIpi,
            valorIpi: $valorIpi,
        );
    }
}
