<?php

namespace Tests\Domain;

use App\Domain\Fiscal\CalculoImpostoService;
use PHPUnit\Framework\TestCase;

/**
 * BASELINE fiscal (casos de ouro) — N9. O cálculo de imposto é legislado: cada
 * caso fixa entrada→saída esperada. Sem banco (cálculo puro).
 */
class CalculoImpostoTest extends TestCase
{
    private CalculoImpostoService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new CalculoImpostoService;
    }

    public function test_icms_pis_cofins_tributados_caso_de_ouro(): void
    {
        // 10 un x R$100 = 1000; ICMS 18% = 180; PIS 1,65% = 16,50; COFINS 7,6% = 76,00.
        $imp = $this->svc->calcular([
            'quantidade' => 10, 'valor_unitario' => 100,
            'cst_icms' => '00', 'aliq_icms' => 18, 'aliq_pis' => 1.65, 'aliq_cofins' => 7.6,
        ]);

        $this->assertEqualsWithDelta(1000, $imp->baseIcms, 0.001);
        $this->assertEqualsWithDelta(180, $imp->valorIcms, 0.001);
        $this->assertEqualsWithDelta(16.50, $imp->valorPis, 0.001);
        $this->assertEqualsWithDelta(76.00, $imp->valorCofins, 0.001);
    }

    public function test_desconto_reduz_a_base(): void
    {
        // 1000 - 100 desconto = 900 de base; ICMS 18% = 162.
        $imp = $this->svc->calcular([
            'quantidade' => 10, 'valor_unitario' => 100, 'desconto' => 100,
            'cst_icms' => '00', 'aliq_icms' => 18,
        ]);

        $this->assertEqualsWithDelta(900, $imp->baseIcms, 0.001);
        $this->assertEqualsWithDelta(162, $imp->valorIcms, 0.001);
    }

    public function test_cst_isento_zera_icms(): void
    {
        // CST 40 (isento) → base e valor de ICMS zero, mas PIS/COFINS ainda incidem.
        $imp = $this->svc->calcular([
            'quantidade' => 5, 'valor_unitario' => 50,
            'cst_icms' => '40', 'aliq_icms' => 18, 'aliq_pis' => 1.65, 'aliq_cofins' => 7.6,
        ]);

        $this->assertEqualsWithDelta(0, $imp->baseIcms, 0.001);
        $this->assertEqualsWithDelta(0, $imp->valorIcms, 0.001);
        // PIS sobre 250 = 4,125 → arredondado a 2 casas (centavos) = 4,13.
        $this->assertEqualsWithDelta(4.13, $imp->valorPis, 0.001);
    }

    public function test_ipi_incide_sobre_o_bruto(): void
    {
        // IPI 5% sobre 1000 (bruto, sem desconto) = 50.
        $imp = $this->svc->calcular([
            'quantidade' => 10, 'valor_unitario' => 100, 'desconto' => 100,
            'cst_icms' => '00', 'aliq_ipi' => 5,
        ]);

        $this->assertEqualsWithDelta(50, $imp->valorIpi, 0.001);
    }

    // ── C7a: porte completo (ST / redução / FCP / DIFAL / PIS-COFINS base) ──

    public function test_icms_st_com_mva_caso_de_ouro(): void
    {
        // vLiq 1000; CST 10; ICMS 18% = 180; MVA 40% → BC-ST = 1400; ST 18% = 252; vICMSST = 252 - 180 = 72.
        $imp = $this->svc->calcular([
            'quantidade' => 10, 'valor_unitario' => 100,
            'cst_icms' => '10', 'aliq_icms' => 18, 'aliq_icms_st' => 18, 'mva_st' => 40,
        ]);

        $this->assertEqualsWithDelta(180, $imp->valorIcms, 0.001);
        $this->assertEqualsWithDelta(1400, $imp->baseIcmsSt, 0.001);
        $this->assertEqualsWithDelta(72, $imp->valorIcmsSt, 0.001);
    }

    public function test_reducao_de_base_cst_20(): void
    {
        // CST 20 com base reduzida a 60% → BC = 600; ICMS 18% = 108.
        $imp = $this->svc->calcular([
            'quantidade' => 10, 'valor_unitario' => 100,
            'cst_icms' => '20', 'aliq_icms' => 18, 'perc_bc_icms' => 60,
        ]);

        $this->assertEqualsWithDelta(600, $imp->baseIcms, 0.001);
        $this->assertEqualsWithDelta(108, $imp->valorIcms, 0.001);
        $this->assertEqualsWithDelta(40, $imp->percRedBc, 0.001);
    }

    public function test_fcp_sobre_base_icms(): void
    {
        // CST 00; BC ICMS = 1000; FCP 2% = 20.
        $imp = $this->svc->calcular([
            'quantidade' => 10, 'valor_unitario' => 100,
            'cst_icms' => '00', 'aliq_icms' => 18, 'aliq_fcp' => 2,
        ]);

        $this->assertEqualsWithDelta(20, $imp->valorFcp, 0.001);
        $this->assertEqualsWithDelta(1000, $imp->baseFcp, 0.001);
    }

    public function test_difal_consumidor_final_interestadual(): void
    {
        // BC 1000; inter 12%, dest 18% → difal 6% = 60; 100% destino (ano != 2018).
        $imp = $this->svc->calcular([
            'quantidade' => 10, 'valor_unitario' => 100,
            'cst_icms' => '00', 'aliq_icms' => 12,
            'difal' => true, 'aliq_icms_dest' => 18, 'ano' => 2024,
        ]);

        $this->assertEqualsWithDelta(60, $imp->valorDifalDest, 0.001);
        $this->assertEqualsWithDelta(0, $imp->valorDifalRemet, 0.001);
    }

    public function test_difal_partilha_2018_oitenta_porcento_destino(): void
    {
        // Mesmo caso, ano 2018 → 80% destino (48) / 20% remetente (12).
        $imp = $this->svc->calcular([
            'quantidade' => 10, 'valor_unitario' => 100,
            'cst_icms' => '00', 'aliq_icms' => 12,
            'difal' => true, 'aliq_icms_dest' => 18, 'ano' => 2018,
        ]);

        $this->assertEqualsWithDelta(48, $imp->valorDifalDest, 0.001);
        $this->assertEqualsWithDelta(12, $imp->valorDifalRemet, 0.001);
    }

    public function test_pis_com_reducao_de_base(): void
    {
        // vLiq 1000; base PIS reduzida a 50% → vBC 500; PIS 1,65% = 8,25.
        $imp = $this->svc->calcular([
            'quantidade' => 10, 'valor_unitario' => 100,
            'cst_icms' => '00', 'aliq_pis' => 1.65, 'perc_bc_pis' => 50,
        ]);

        $this->assertEqualsWithDelta(8.25, $imp->valorPis, 0.001);
    }
}
