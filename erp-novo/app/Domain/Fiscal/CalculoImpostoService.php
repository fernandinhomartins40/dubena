<?php

namespace App\Domain\Fiscal;

/**
 * CalculoImpostoService (N9 / C7a) — PORTE FIEL da lógica tributária do legado
 * (CalculoImposto + TagIcms + Pis/CofinsBase). Não se reinventa a regra: cada
 * fórmula e cada conjunto de CST abaixo replica o legado, com baseline (casos de
 * ouro) garantindo igualdade legado×novo.
 *
 * Cobre: ICMS (com redução de base), ICMS-ST (MVA + redução BC-ST), FCP, DIFAL
 * (partilha consumidor final interestadual), PIS/COFINS (com redução de base) e IPI.
 *
 * Convenção de arredondamento do legado: calcImpostoProp(v, aliq, p) =
 * round(v * aliq/100, p). ICMS/ST usam 5 casas internas; o resto, 2.
 */
class CalculoImpostoService
{
    // Conjuntos de CST legislados (idênticos ao NfUtil do legado).
    private const CST_USE_ST = ['900', '500', '203', '202', '201', '90', '70', '60', '30', '10'];

    private const CST_USE_REDBC = ['20', '51', '70', '90', '900'];

    private const CST_USE_REDBCST = ['10', '30', '70', '90', '201', '202', '203', '900'];

    private const CST_USE_FCP_NORMAL = ['00', '10', '20', '51', '60', '70', '90'];

    private const CST_SEM_ICMS = ['40', '41', '50', '60']; // isento/não trib./diferido/ST a montante

    // PIS/COFINS: CST que NÃO usam alíquota percentual (03, 99 → por quantidade; fora de escopo).
    private const CST_PISCOFINS_SEM_PERCENT = ['99', '03'];

    /**
     * @param  array{
     *   quantidade: float, valor_unitario: float, desconto?: float, frete?: float,
     *   seguro?: float, outro?: float, valor_ipi?: float,
     *   cst_icms?: string, aliq_icms?: float, perc_bc_icms?: float,
     *   aliq_icms_st?: float, mva_st?: float, perc_bc_icms_st?: float,
     *   aliq_fcp?: float,
     *   difal?: bool, aliq_icms_dest?: float, ano?: int,
     *   cst_pis?: string, aliq_pis?: float, perc_bc_pis?: float,
     *   cst_cofins?: string, aliq_cofins?: float, perc_bc_cofins?: float,
     *   aliq_ipi?: float, perc_bc_ipi?: float
     * }  $item
     */
    public function calcular(array $item): ImpostoItem
    {
        $qtd = (float) $item['quantidade'];
        $bruto = round($qtd * (float) $item['valor_unitario'], 2);
        $desconto = round((float) ($item['desconto'] ?? 0), 2);
        $frete = round((float) ($item['frete'] ?? 0), 2);
        $seguro = round((float) ($item['seguro'] ?? 0), 2);
        $outro = round((float) ($item['outro'] ?? 0), 2);

        // vLiqItem do legado: bruto - desconto + frete + seguro + outro.
        $vLiq = round($bruto - $desconto + $frete + $seguro + $outro, 2);

        $cst = (string) ($item['cst_icms'] ?? '00');

        // ── ICMS ──
        $aliqIcms = (float) ($item['aliq_icms'] ?? 0);
        $percBcIcms = isset($item['perc_bc_icms']) ? (float) $item['perc_bc_icms'] : 100.0;
        $semIcms = in_array($cst, self::CST_SEM_ICMS, true);

        // Redução de base: pRedBC = 100 - percBcIcms (só nos CST do conjunto).
        $pRedBc = in_array($cst, self::CST_USE_REDBC, true) ? (100.0 - $percBcIcms) : 0.0;
        $baseIcms = $semIcms ? 0.0 : round($vLiq - $this->calcProp($vLiq, $pRedBc, 5), 2);
        $valorIcms = $semIcms ? 0.0 : $this->calcProp($baseIcms, $aliqIcms, 2);

        // ── ICMS-ST ──
        $baseSt = 0.0;
        $valorSt = 0.0;
        $mva = (float) ($item['mva_st'] ?? 0);
        $aliqSt = (float) ($item['aliq_icms_st'] ?? 0);
        $pRedBcSt = in_array($cst, self::CST_USE_REDBCST, true)
            ? (100.0 - (float) ($item['perc_bc_icms_st'] ?? 100))
            : 0.0;

        if (in_array($cst, self::CST_USE_ST, true) && $aliqSt > 0) {
            $vIpi = (float) ($item['valor_ipi'] ?? 0);
            $vLiqSt = $vLiq + $vIpi;
            $bcReduzida = round($vLiqSt - $this->calcProp($vLiqSt, $pRedBcSt, 5), 5);
            $fator = $mva > 0 ? ($mva / 100) : 0.0;
            $baseSt = round(($bcReduzida * $fator) + $bcReduzida, 2);
            $valorSt = max(0.0, round($this->calcProp($baseSt, $aliqSt, 5) - $valorIcms, 2));
        }

        // ── FCP (sobre a BC do ICMS) ──
        $aliqFcp = (float) ($item['aliq_fcp'] ?? 0);
        $baseFcp = 0.0;
        $valorFcp = 0.0;
        if ($aliqFcp > 0 && in_array($cst, self::CST_USE_FCP_NORMAL, true) && $baseIcms > 0) {
            $baseFcp = $baseIcms;
            $valorFcp = $this->calcProp($baseFcp, $aliqFcp, 2);
        }

        // ── DIFAL (consumidor final, interestadual) ──
        $difalDest = 0.0;
        $difalRemet = 0.0;
        $fcpDifal = 0.0;
        if (! empty($item['difal']) && ! $semIcms) {
            $aliqDest = (float) ($item['aliq_icms_dest'] ?? 0);
            $aliqDifal = max(0.0, $aliqDest - $aliqIcms);
            $difal = $baseIcms * ($aliqDifal / 100);
            // Partilha: 2018 = 80% destino; depois = 100% destino (regra do legado).
            $percDest = ((int) ($item['ano'] ?? (int) date('Y'))) === 2018 ? 80.0 : 100.0;
            $difalDest = round($difal * ($percDest / 100), 2);
            $difalRemet = round($difal * ((100 - $percDest) / 100), 2);
            if ($aliqFcp > 0) {
                $fcpDifal = round($baseIcms * ($aliqFcp / 100), 2);
            }
        }

        // ── PIS / COFINS (base com possível redução) ──
        [$aliqPis, $valorPis] = $this->pisCofins(
            $vLiq,
            (string) ($item['cst_pis'] ?? '01'),
            (float) ($item['aliq_pis'] ?? 0),
            (float) ($item['perc_bc_pis'] ?? 100),
        );
        [$aliqCofins, $valorCofins] = $this->pisCofins(
            $vLiq,
            (string) ($item['cst_cofins'] ?? '01'),
            (float) ($item['aliq_cofins'] ?? 0),
            (float) ($item['perc_bc_cofins'] ?? 100),
        );

        // ── IPI (sobre o bruto, com possível % de BC) ──
        $aliqIpi = (float) ($item['aliq_ipi'] ?? 0);
        $percBcIpi = isset($item['perc_bc_ipi']) ? (float) $item['perc_bc_ipi'] : 100.0;
        $baseIpi = $this->calcProp($bruto, $percBcIpi, 2);
        $valorIpi = $this->calcProp($baseIpi, $aliqIpi, 2);

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
            baseIcmsSt: $baseSt,
            aliqIcmsSt: $aliqSt,
            valorIcmsSt: $valorSt,
            mvaSt: $mva,
            percRedBc: $pRedBc,
            percRedBcSt: $pRedBcSt,
            baseFcp: $baseFcp,
            aliqFcp: $aliqFcp,
            valorFcp: $valorFcp,
            valorDifalDest: $difalDest,
            valorDifalRemet: $difalRemet,
            valorFcpDifal: $fcpDifal,
        );
    }

    /**
     * PIS/COFINS do legado: vBC = vLiq - calcImpostoProp(vLiq, 100 - base%);
     * valor = calcImpostoProp(vBC, aliq). CST sem percentual → zero.
     *
     * @return array{0: float, 1: float} [aliq, valor]
     */
    private function pisCofins(float $vLiq, string $cst, float $aliq, float $percBase): array
    {
        if (in_array($cst, self::CST_PISCOFINS_SEM_PERCENT, true)) {
            return [$aliq, 0.0];
        }

        $vBC = round($vLiq - $this->calcProp($vLiq, 100.0 - $percBase, 2), 2);
        $valor = $this->calcProp($vBC, $aliq, 2);

        return [$aliq, $valor];
    }

    /** calcImpostoProp do legado: round(vBC * aliq/100, precisão). */
    private function calcProp(float $vBC, float $aliq, int $precisao): float
    {
        return round($vBC * ($aliq / 100), $precisao);
    }
}
