<?php

namespace Tests\Domain;

use App\Domain\Cobranca\BoletoService;
use App\Domain\Cobranca\PixService;
use App\Domain\Cobranca\SituacaoBoleto;
use App\Domain\Cobranca\SituacaoPix;
use App\Domain\Financeiro\FinanceiroService;
use App\Models\Empresa;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * N7 — Boleto (driver fake) e PIX (webhook seguro). Gate: testamos a ORQUESTRAÇÃO
 * e a segurança, não a integração bancária real.
 */
class CobrancaServiceTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
    }

    private function parcela(float $valor = 100): FinanceiroParcela
    {
        $f = app(FinanceiroService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'pagarreceber' => 'R', 'valor' => $valor,
        ]);

        return $f->parcelas->first();
    }

    public function test_gera_boleto_com_nosso_numero_e_linha_digitavel(): void
    {
        $boleto = app(BoletoService::class)->gerarParaParcela($this->parcela(150));

        $this->assertNotEmpty($boleto->nosso_numero);
        $this->assertNotEmpty($boleto->linha_digitavel);
        $this->assertEquals(SituacaoBoleto::PENDENTE, $boleto->situacao);
        $this->assertEqualsWithDelta(150, (float) $boleto->valor, 0.001);
    }

    public function test_remessa_registra_boletos(): void
    {
        $svc = app(BoletoService::class);
        $b1 = $svc->gerarParaParcela($this->parcela(100));
        $b2 = $svc->gerarParaParcela($this->parcela(200));

        $remessa = $svc->gerarRemessa([$b1, $b2], $this->empresa->id);

        $this->assertEquals(2, $remessa->total_boletos);
        $this->assertEqualsWithDelta(300, (float) $remessa->valor_total, 0.001);
        $this->assertEquals(SituacaoBoleto::REGISTRADO, $b1->refresh()->situacao);
    }

    public function test_remessa_recusa_boleto_de_outra_empresa(): void
    {
        $outra = Empresa::factory()->create();
        $financeiro = app(FinanceiroService::class)->criar([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'pagarreceber' => 'R', 'valor' => 100,
        ]);
        $boletoAlheio = app(BoletoService::class)->gerarParaParcela($financeiro->parcelas->first());

        $this->expectException(ValidationException::class);
        app(BoletoService::class)->gerarRemessa([$boletoAlheio], $this->empresa->id);
    }

    public function test_retorno_de_liquidacao_baixa_a_parcela(): void
    {
        $svc = app(BoletoService::class);
        $parcela = $this->parcela(100);
        $boleto = $svc->gerarParaParcela($parcela);
        $svc->gerarRemessa([$boleto], $this->empresa->id);

        // Linha de retorno fake: nosso_numero presente + código 06 (liquidação) + valor.
        $linha = $boleto->nosso_numero.' 06|100.00';
        $n = $svc->processarRetorno([$linha], $this->empresa->id);

        $this->assertEquals(1, $n);
        $this->assertEquals(SituacaoBoleto::LIQUIDADO, $boleto->refresh()->situacao);
        $this->assertTrue($parcela->refresh()->baixado);
    }

    public function test_retorno_nao_casa_substring_nem_boleto_de_outra_empresa(): void
    {
        $outra = Empresa::factory()->create();
        $financeiro = app(FinanceiroService::class)->criar([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'pagarreceber' => 'R', 'valor' => 100,
        ]);
        $boletoAlheio = app(BoletoService::class)->gerarParaParcela($financeiro->parcelas->first());

        $linha = $boletoAlheio->nosso_numero.' 06|100.00';
        $n = app(BoletoService::class)->processarRetorno([$linha], $this->empresa->id);

        $this->assertSame(0, $n);
        $this->assertFalse($financeiro->parcelas->first()->refresh()->baixado);
        $this->assertSame(0, $boletoAlheio->ocorrencias()->count());
    }

    public function test_reprocessar_retorno_nao_sobrescreve_primeira_baixa(): void
    {
        $svc = app(BoletoService::class);
        $parcela = $this->parcela(100);
        $boleto = $svc->gerarParaParcela($parcela);

        $svc->processarRetorno([$boleto->nosso_numero.' 06|100.00'], $this->empresa->id);
        $primeiraBaixa = $parcela->refresh()->datahora_baixa;

        $this->travel(5)->minutes();
        $svc->processarRetorno([$boleto->nosso_numero.' 06|90.00'], $this->empresa->id);

        $parcela->refresh();
        $this->assertEquals($primeiraBaixa, $parcela->datahora_baixa);
        $this->assertEqualsWithDelta(100, (float) $parcela->valor_efetivado, 0.001);
    }

    public function test_pagamento_pix_seguido_de_retorno_cnab_nao_sobrescreve_a_baixa(): void
    {
        $parcela = $this->parcela(100);
        $boleto = app(BoletoService::class)->gerarParaParcela($parcela);
        $pix = app(PixService::class)->criarCobranca($parcela);

        app(PixService::class)->processarWebhook([
            'txid' => $pix->txid,
            'valor' => 100.00,
            'e2eid' => 'E-PAGAMENTO-ORIGINAL',
        ]);
        $primeiraBaixa = $parcela->refresh()->datahora_baixa;

        $this->travel(5)->minutes();
        app(BoletoService::class)->processarRetorno([
            $boleto->nosso_numero.' 06|90.00',
        ], $this->empresa->id);

        $parcela->refresh();
        $this->assertEquals($primeiraBaixa, $parcela->datahora_baixa);
        $this->assertEqualsWithDelta(100, (float) $parcela->valor_efetivado, 0.001);
        $this->assertEquals(SituacaoBoleto::LIQUIDADO, $boleto->refresh()->situacao);
    }

    public function test_pix_cobranca_gera_txid_e_copia_e_cola(): void
    {
        $cobranca = app(PixService::class)->criarCobranca($this->parcela(80));

        $this->assertNotEmpty($cobranca->txid);
        $this->assertNotEmpty($cobranca->copia_e_cola);
        $this->assertEquals(SituacaoPix::ATIVA, $cobranca->situacao);
    }

    public function test_pix_webhook_confirma_com_valor_correto_e_baixa_parcela(): void
    {
        $parcela = $this->parcela(120);
        $cobranca = app(PixService::class)->criarCobranca($parcela);

        $resultado = app(PixService::class)->processarWebhook([
            'txid' => $cobranca->txid, 'valor' => 120.00, 'e2eid' => 'E123',
        ]);

        $this->assertEquals(SituacaoPix::CONCLUIDA, $resultado->situacao);
        $this->assertTrue($parcela->refresh()->baixado);
    }

    public function test_pix_webhook_rejeita_valor_divergente(): void
    {
        $cobranca = app(PixService::class)->criarCobranca($this->parcela(120));

        $this->expectException(ValidationException::class);
        app(PixService::class)->processarWebhook(['txid' => $cobranca->txid, 'valor' => 50.00]);
    }

    public function test_pix_webhook_idempotente(): void
    {
        $parcela = $this->parcela(120);
        $cobranca = app(PixService::class)->criarCobranca($parcela);
        $svc = app(PixService::class);

        $svc->processarWebhook(['txid' => $cobranca->txid, 'valor' => 120.00]);
        // Reentrega do webhook não deve quebrar nem baixar em dobro.
        $segundo = $svc->processarWebhook(['txid' => $cobranca->txid, 'valor' => 120.00]);

        $this->assertEquals(SituacaoPix::CONCLUIDA, $segundo->situacao);
        $this->assertEquals(1, FinanceiroParcela::query()->where('id', $parcela->id)->where('baixado', true)->count());
    }
}
