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
        $this->svc = new CalculoImpostoService();
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
}
