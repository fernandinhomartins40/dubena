<?php

namespace Tests\Domain;

use App\Domain\Financeiro\AgrupamentoStatus;
use App\Domain\Financeiro\FinanceiroService;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * BASELINE do N5: Σ parcelas = valor do título; parcelamento (centavo na última);
 * agrupamento/reparcelamento (enum); cancelamento.
 */
class FinanceiroServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinanceiroService $service;
    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinanceiroService::class);
        $this->empresa = Empresa::factory()->create();
    }

    /** @param array<string,mixed> $extra */
    private function dados(float $valor, array $extra = []): array
    {
        return array_merge([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'pagarreceber' => 'R',
            'descricao' => 'Teste',
            'valor' => $valor,
        ], $extra);
    }

    public function test_soma_das_parcelas_igual_ao_valor_do_titulo(): void
    {
        // 100,00 em 3 parcelas → 33,33 + 33,33 + 33,34.
        $f = $this->service->criar($this->dados(100.00), numParcelas: 3);

        $this->assertCount(3, $f->parcelas);
        $this->assertEqualsWithDelta(100.00, (float) $f->parcelas->sum('valor'), 0.001);
        // a última absorve o centavo
        $this->assertEqualsWithDelta(33.34, (float) $f->parcelas->last()->valor, 0.001);
    }

    public function test_titulo_a_vista_gera_uma_parcela(): void
    {
        $f = $this->service->criar($this->dados(250.00));
        $this->assertCount(1, $f->parcelas);
        $this->assertEqualsWithDelta(250.00, (float) $f->parcelas->first()->valor, 0.001);
    }

    public function test_valor_zero_ou_negativo_bloqueia(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->criar($this->dados(0));
    }

    public function test_agrupar_consolida_titulos(): void
    {
        $t1 = $this->service->criar($this->dados(100));
        $t2 = $this->service->criar($this->dados(150));

        $agrupador = $this->service->agrupar([$t1, $t2], $this->dados(0, ['descricao' => 'Fechamento']));

        // O agrupador soma os títulos.
        $this->assertEqualsWithDelta(250, (float) $agrupador->valor, 0.001);
        $this->assertEquals(AgrupamentoStatus::AGRUPADOR, $agrupador->agrupamento_status);
        // Os originais viram AGRUPADO apontando para o agrupador.
        $this->assertEquals(AgrupamentoStatus::AGRUPADO, $t1->refresh()->agrupamento_status);
        $this->assertEquals($agrupador->id, $t1->agrupador_id);
    }

    public function test_desagrupar_reativa_originais(): void
    {
        $t1 = $this->service->criar($this->dados(100));
        $agrupador = $this->service->agrupar([$t1], $this->dados(0));

        $this->service->desagrupar($agrupador);

        $this->assertEquals(AgrupamentoStatus::NORMAL, $t1->refresh()->agrupamento_status);
        $this->assertNull($t1->agrupador_id);
        $this->assertTrue($agrupador->refresh()->cancelado);
    }

    public function test_reparcelar_cria_novo_titulo_com_saldo_em_aberto(): void
    {
        $original = $this->service->criar($this->dados(300), numParcelas: 3);
        // baixa a 1ª parcela (100 pagos) → restam 200 em aberto.
        $original->parcelas()->where('numero', 1)->update(['baixado' => true, 'valor_efetivado' => 100]);

        $novo = $this->service->reparcelar($original->refresh(), numParcelas: 2);

        $this->assertEquals(AgrupamentoStatus::REPARCELADO, $original->refresh()->agrupamento_status);
        $this->assertEqualsWithDelta(200, (float) $novo->valor, 0.001);
        $this->assertCount(2, $novo->parcelas);
    }

    public function test_cancelar_titulo_com_parcela_baixada_bloqueia(): void
    {
        $f = $this->service->criar($this->dados(100));
        $f->parcelas()->update(['baixado' => true]);

        $this->expectException(ValidationException::class);
        $this->service->cancelar($f);
    }
}
