<?php

namespace Tests\Caracterizacao;

use Tests\TestCase;

/**
 * Caracterização (golden master) — FASE 1.
 *
 * Fixa o comportamento ATUAL dos helpers de conversão fiscal/monetária
 * (formato BR ↔ float) usados em TODA gravação/exibição fiscal e financeira.
 * Qualquer mudança nesses helpers durante a refatoração (Fase 2/4) quebraria
 * valores em NF-e, financeiro, estoque — estes testes travam isso.
 *
 * Golden extraído do comportamento real do código (container PHP 7.4).
 *
 * PHPUnit 7.5 / Laravel 5.4 / PHP 7.4.
 */
class HelpersFiscaisTest extends TestCase
{
    /** Formato BR (1.234,56 / "R$ 1.234,56") → float para gravar no banco. */
    public function testInsertNumeroDecimalOracle()
    {
        $this->assertSame(1234.56, insertNumeroDecimalOracle('1.234,56'));
        $this->assertSame(1234.56, insertNumeroDecimalOracle('R$ 1.234,56'));
        $this->assertSame(1234.56, insertNumeroDecimalOracle('1234,56'));
        $this->assertSame(0.0,     insertNumeroDecimalOracle('0,00'));
        $this->assertSame(1000000.99, insertNumeroDecimalOracle('1.000.000,99'));
    }

    /** Float → formato monetário BR para exibição. */
    public function testRequestNumeroDecimalOracle()
    {
        $this->assertSame('R$ 1.234,56',     requestNumeroDecimalOracle(1234.56));
        $this->assertSame('R$ 0,00',         requestNumeroDecimalOracle(0));
        $this->assertSame('R$ 0,50',         requestNumeroDecimalOracle(0.5));
        $this->assertSame('R$ 1.000.000,99', requestNumeroDecimalOracle(1000000.99));
    }

    /** Roundtrip: gravar→exibir→gravar preserva o valor (regra crítica). */
    public function testRoundtripDecimalPreservaValor()
    {
        $valor = 1234.56;
        $exibido = requestNumeroDecimalOracle($valor);          // "R$ 1.234,56"
        $degravado = insertNumeroDecimalOracle($exibido);       // 1234.56
        $this->assertSame($valor, $degravado);
    }

    /** 4 casas decimais (tela de NF-e). */
    public function testRequestNumeroDecimal4DigitosOracle()
    {
        $this->assertSame('2,5000',     requestNumeroDecimal4DigitosOracle(2.5));
        $this->assertSame('0,0000',     requestNumeroDecimal4DigitosOracle(0));
        $this->assertSame('1.234,5678', requestNumeroDecimal4DigitosOracle(1234.5678));
    }

    /** Truncamento (não arredonda) — usado em base de cálculo fiscal. */
    public function testTruncTrunca()
    {
        $this->assertSame(12.34, trunc(12.349, 2));
        $this->assertSame(12.34, trunc(12.345, 2)); // trunca, não arredonda p/ 12.35
        $this->assertSame(99.99, trunc(99.999, 2));
    }
}
