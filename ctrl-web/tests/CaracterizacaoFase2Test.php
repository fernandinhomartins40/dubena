<?php

use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Testes de caracterização — FASE 2 (ctrl-web / ERP).
 *
 * Capturam (a) as correções de segurança da FASE 1 e (b) o comportamento atual
 * de helpers fiscais/financeiros de conversão de moeda/decimais. Servem de REDE
 * DE PROTEÇÃO para a FASE 3 (migração Oracle→PostgreSQL) e FASE 4 (upgrade).
 *
 * Não dependem de schema no banco (o migrate só roda após a Fase 3), por isso
 * focam em unidade de regras puras + cripto.
 *
 * PHPUnit 5.7 / Laravel 5.4.
 */
class CaracterizacaoFase2Test extends TestCase
{
    // ───────────────────────── Segurança (Fase 1) ─────────────────────────

    /**
     * S2: customCrypt agora produz criptografia REAL (não mais base64).
     * Caracteriza: o resultado é decifrável de volta e NÃO é base64 simples.
     */
    public function testCustomCryptUsaCriptografiaRealEReversivel()
    {
        $segredo = 'SenhaCertificadoPFX#2026';
        $enc = customCrypt($segredo, 6);

        // Decifra corretamente.
        $this->assertEquals($segredo, customDecrypt($enc, 6));

        // NÃO é o formato antigo (base64 repetido) — caracteriza a cripto real.
        $legado = customCryptLegacyBase64($segredo, 6);
        $this->assertNotEquals($legado, $enc);

        // O payload do Laravel Crypt é um JSON base64 com iv/value/mac.
        $payload = json_decode(base64_decode($enc), true);
        $this->assertArrayHasKey('iv', $payload);
        $this->assertArrayHasKey('mac', $payload);
    }

    /**
     * Retrocompatibilidade: segredos LEGADOS (base64 repetido) continuam
     * legíveis pelo customDecrypt (não quebra dados já gravados em produção).
     */
    public function testCustomDecryptLeFormatoLegadoBase64()
    {
        $segredo = 'senha-antiga-do-email';
        $legado = customCryptLegacyBase64($segredo, 8);
        $this->assertEquals($segredo, customDecrypt($legado, 8));
    }

    /**
     * Valores vazios/null passam sem erro (comportamento defensivo).
     */
    public function testCustomCryptComValorVazio()
    {
        $this->assertEquals('', customCrypt('', 6));
        $this->assertNull(customCrypt(null, 6));
    }

    // ─────────────────── Regras fiscais/financeiras (baseline) ───────────────────

    /**
     * Conversão de moeda BR ("1.234,56") para float Oracle. Caracteriza a regra
     * exata atual — qualquer mudança na migração de banco deve preservá-la.
     */
    public function testInsertNumeroDecimalOracleConverteMoedaBR()
    {
        $this->assertEquals(1234.56, insertNumeroDecimalOracle('1.234,56'));
        $this->assertEquals(1234.56, insertNumeroDecimalOracle('R$ 1.234,56'));
        $this->assertEquals(0.5, insertNumeroDecimalOracle('0,50'));
        $this->assertEquals(1000.0, insertNumeroDecimalOracle('1.000,00'));
    }

    /**
     * formatDecimalPlaces: arredondamento vs truncamento. Caracteriza o
     * comportamento monetário (crítico em fiscal — não pode mudar sem querer).
     */
    public function testFormatDecimalPlacesArredondaETrunca()
    {
        // Arredonda (padrão).
        $this->assertEquals('10.57', formatDecimalPlaces(10.566, 2, false));
        // Trunca (sem arredondar).
        $this->assertEquals('10.56', formatDecimalPlaces(10.566, 2, true));
        // Precisão diferente.
        $this->assertEquals('10.566', formatDecimalPlaces(10.566, 3, false));
    }

    /**
     * trunc: corta casas decimais sem arredondar. Base de cálculos fiscais.
     */
    public function testTruncCortaSemArredondar()
    {
        $this->assertEquals(10.56, trunc(10.5699, 2));
        $this->assertEquals(10.5, trunc(10.59, 1));
        $this->assertEquals(10.0, trunc(10.99, 0));
    }
}
