<?php

namespace Tests\Caracterizacao;

use Tests\TestCase;
use Tests\Caracterizacao\Support\FixturesFiscais;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Processors\financeiroProcessor;
use App\Financeiro;

/**
 * Caracterização (golden master) — FASE 1, leva 3.
 *
 * Fixa a regra de financeiroProcessor::setParcelasRequest: dado um JSON de
 * parcelas + um desconto total, ela rateia o desconto por parcela (desconto *
 * fator) e calcula valorefetivado = valor - desconto rateado. É regra
 * financeira determinística — a refatoração (Fase 4) não pode alterá-la.
 *
 * Usa o cenário sintético só para popular a Session (grupo/empresa que os
 * setters leem); não persiste parcelas (lê via getParcelas()).
 *
 * PHPUnit 7.5 / Laravel 5.4 / PHP 7.4.
 */
class ParcelamentoFinanceiroTest extends TestCase
{
    use DatabaseTransactions;
    use FixturesFiscais;

    /** Processor com um Financeiro mínimo setado (datacompetencia/valor/pagarreceber). */
    private function processorComFinanceiro($pagarreceber = 'R', $datacompetencia = '2026-01-31')
    {
        $proc = new financeiroProcessor();
        $fin = new Financeiro();
        $fin->grupo_id = $this->empresa->grupo_id;
        $fin->empresa_id = $this->empresa->id;
        $fin->pagarreceber = $pagarreceber;
        $fin->valor = 200.0;
        $fin->datacompetencia = $datacompetencia;
        $proc->setFinanceiro($fin);
        $proc->setDataVencimento('2026-02-28');
        return $proc;
    }

    /**
     * Duas parcelas com desconto total 10,00 rateado por fator (0.6 / 0.4):
     *  - parcela 1: valor 100, desconto 6,00, valorefetivado 94,00
     *  - parcela 2: valor 100, desconto 4,00, valorefetivado 96,00
     */
    public function testRateioDeDescontoPorParcela()
    {
        $this->criarCenarioFiscal();
        $proc = $this->processorComFinanceiro();

        $json = json_encode([
            'desconto' => '10,00',
            'data' => [
                ['28/02/2026', 100, 0.6],
                ['30/03/2026', 100, 0.4],
            ],
        ]);
        $proc->setParcelasRequest($json);

        $parcelas = $proc->getParcelas();
        $this->assertCount(2, $parcelas);

        $this->assertEquals(100, (float) $parcelas[0]['valor'], '', 0.0001);
        $this->assertEquals(6.0, (float) $parcelas[0]['desconto'], '', 0.0001);
        $this->assertEquals(94.0, (float) $parcelas[0]['valorefetivado'], '', 0.0001);

        $this->assertEquals(100, (float) $parcelas[1]['valor'], '', 0.0001);
        $this->assertEquals(4.0, (float) $parcelas[1]['desconto'], '', 0.0001);
        $this->assertEquals(96.0, (float) $parcelas[1]['valorefetivado'], '', 0.0001);
    }

    /**
     * Sem desconto: valorefetivado = valor; numeração sequencial das parcelas.
     */
    public function testParcelasSemDesconto()
    {
        $this->criarCenarioFiscal();
        $proc = $this->processorComFinanceiro();

        $json = json_encode([
            'desconto' => '0,00',
            'data' => [
                ['28/02/2026', 50, 1],
                ['30/03/2026', 70, 1],
            ],
        ]);
        $proc->setParcelasRequest($json);

        $parcelas = $proc->getParcelas();
        $this->assertCount(2, $parcelas);
        $this->assertEquals(0, (float) $parcelas[0]['desconto'], '', 0.0001);
        $this->assertEquals(50, (float) $parcelas[0]['valorefetivado'], '', 0.0001);
        $this->assertEquals(1, (int) $parcelas[0]['numero']);
        $this->assertEquals(2, (int) $parcelas[1]['numero']);
        $this->assertEquals('R', $parcelas[1]['pagarreceber']);
    }
}
