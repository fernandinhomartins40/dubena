<?php

namespace Tests\Domain;

use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\FinanceiroService;
use App\Models\Caixa\Conta;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * BASELINE #1 (risco máximo): Σ contamovimentos = conta.saldo_atual.
 * Cobre baixa com juros/multa/desconto, transferência, estorno (reabre parcela),
 * abrir/fechar caixa.
 */
class CaixaServiceTest extends TestCase
{
    use RefreshDatabase;

    private CaixaService $caixa;
    private FinanceiroService $financeiro;
    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caixa = app(CaixaService::class);
        $this->financeiro = app(FinanceiroService::class);
        $this->empresa = Empresa::factory()->create();
    }

    private function conta(float $inicial = 0): Conta
    {
        return $this->caixa->criarConta([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Caixa',
            'saldo_inicial' => $inicial,
        ]);
    }

    private function assertSaldoConsistente(Conta $conta): void
    {
        $derivado = $this->caixa->saldoDerivado($conta->id);
        $atual = (float) $conta->refresh()->saldo_atual;
        $this->assertEqualsWithDelta($derivado, $atual, 0.001, 'Σ movimentos deve ser igual ao saldo_atual');
    }

    public function test_saldo_inicial_vira_movimento_de_abertura(): void
    {
        $conta = $this->conta(500);
        $this->assertEqualsWithDelta(500, (float) $conta->saldo_atual, 0.001);
        $this->assertSaldoConsistente($conta);
    }

    public function test_invariante_soma_movimentos_igual_saldo_atual(): void
    {
        $conta = $this->conta(100);
        $this->caixa->movimentar($conta->id, 250, CaixaService::AJUSTE);
        $this->caixa->movimentar($conta->id, -75.50, CaixaService::AJUSTE);

        $this->assertEqualsWithDelta(274.50, (float) $conta->refresh()->saldo_atual, 0.001);
        $this->assertSaldoConsistente($conta);
    }

    public function test_baixa_de_parcela_a_receber_com_juros_e_multa(): void
    {
        $conta = $this->conta(0);
        $f = $this->financeiro->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'pagarreceber' => 'R', 'valor' => 100,
        ]);
        $parcela = $f->parcelas->first();

        // recebe com 5 de juros + 2 de multa - 1 de desconto = 106 líquido.
        $mov = $this->caixa->baixarParcela($conta->id, $parcela->id, juros: 5, multa: 2, desconto: 1);

        $this->assertEqualsWithDelta(106, (float) $mov->valor, 0.001);
        $this->assertEqualsWithDelta(106, (float) $conta->refresh()->saldo_atual, 0.001);
        $this->assertTrue($parcela->refresh()->baixado);
        $this->assertEqualsWithDelta(106, (float) $parcela->valor_efetivado, 0.001);
        $this->assertSaldoConsistente($conta);
    }

    public function test_baixa_de_parcela_a_pagar_sai_do_caixa(): void
    {
        $conta = $this->conta(500);
        $f = $this->financeiro->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'pagarreceber' => 'P', 'valor' => 200,
        ]);

        $this->caixa->baixarParcela($conta->id, $f->parcelas->first()->id);

        $this->assertEqualsWithDelta(300, (float) $conta->refresh()->saldo_atual, 0.001); // 500 - 200
        $this->assertSaldoConsistente($conta);
    }

    public function test_baixar_parcela_ja_baixada_bloqueia(): void
    {
        $conta = $this->conta(0);
        $f = $this->financeiro->criar(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'pagarreceber' => 'R', 'valor' => 50]);
        $this->caixa->baixarParcela($conta->id, $f->parcelas->first()->id);

        $this->expectException(ValidationException::class);
        $this->caixa->baixarParcela($conta->id, $f->parcelas->first()->id);
    }

    public function test_transferencia_preserva_o_total_entre_contas(): void
    {
        $origem = $this->conta(1000);
        $destino = $this->conta(0);

        $this->caixa->transferir($origem->id, $destino->id, 400);

        $this->assertEqualsWithDelta(600, (float) $origem->refresh()->saldo_atual, 0.001);
        $this->assertEqualsWithDelta(400, (float) $destino->refresh()->saldo_atual, 0.001);
        $this->assertSaldoConsistente($origem);
        $this->assertSaldoConsistente($destino);
    }

    public function test_estorno_reverte_saldo_e_reabre_parcela(): void
    {
        $conta = $this->conta(0);
        $f = $this->financeiro->criar(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'pagarreceber' => 'R', 'valor' => 100]);
        $mov = $this->caixa->baixarParcela($conta->id, $f->parcelas->first()->id);

        $this->assertEqualsWithDelta(100, (float) $conta->refresh()->saldo_atual, 0.001);

        $this->caixa->estornar($mov->id);

        $this->assertEqualsWithDelta(0, (float) $conta->refresh()->saldo_atual, 0.001);
        $this->assertFalse($f->parcelas->first()->refresh()->baixado);
        $this->assertSaldoConsistente($conta);
    }

    public function test_abrir_e_fechar_caixa_registra_saldos(): void
    {
        $conta = $this->conta(100);
        $abertura = $this->caixa->abrir($conta->id);
        $this->assertEqualsWithDelta(100, (float) $abertura->saldo_inicial, 0.001);

        $this->caixa->movimentar($conta->id, 50, CaixaService::AJUSTE);
        $fechamento = $this->caixa->fechar($conta->id);

        $this->assertEqualsWithDelta(150, (float) $fechamento->saldo_final, 0.001);
        $this->assertFalse($fechamento->aberto);
    }
}
