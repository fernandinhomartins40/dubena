<?php

namespace Tests\Domain;

use App\Domain\Caixa\CaixaService;
use App\Domain\Caixa\ChequeService;
use App\Domain\Caixa\SituacaoCheque;
use App\Models\Caixa\Cheque;
use App\Models\Caixa\Conta;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * N6 — ChequeService: máquina de estados + compensação credita o caixa.
 */
class ChequeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChequeService $service;
    private CaixaService $caixa;
    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ChequeService::class);
        $this->caixa = app(CaixaService::class);
        $this->empresa = Empresa::factory()->create();
    }

    private function cheque(string $especie = 'R'): Cheque
    {
        return $this->service->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'especie' => $especie, 'numero' => '0001', 'valor' => 200,
        ]);
    }

    public function test_nasce_em_carteira(): void
    {
        $this->assertEquals(SituacaoCheque::CARTEIRA, $this->cheque()->situacao);
    }

    public function test_transicao_invalida_bloqueia(): void
    {
        $cheque = $this->cheque();
        // CARTEIRA não vai direto para COMPENSADO (precisa depositar antes).
        $this->expectException(ValidationException::class);
        $this->service->mudarSituacao($cheque, SituacaoCheque::COMPENSADO);
    }

    public function test_compensar_cheque_recebido_credita_o_caixa(): void
    {
        $conta = $this->caixa->criarConta(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Banco', 'saldo_inicial' => 0]);
        $cheque = $this->cheque('R');

        $this->service->mudarSituacao($cheque, SituacaoCheque::DEPOSITADO);
        $this->service->mudarSituacao($cheque->refresh(), SituacaoCheque::COMPENSADO, $conta->id);

        $this->assertEquals(SituacaoCheque::COMPENSADO, $cheque->refresh()->situacao);
        $this->assertEqualsWithDelta(200, (float) $conta->refresh()->saldo_atual, 0.001);
    }

    public function test_compensar_sem_conta_bloqueia(): void
    {
        $cheque = $this->cheque('R');
        $this->service->mudarSituacao($cheque, SituacaoCheque::DEPOSITADO);

        $this->expectException(ValidationException::class);
        $this->service->mudarSituacao($cheque->refresh(), SituacaoCheque::COMPENSADO);
    }

    public function test_devolvido_pode_redepositar(): void
    {
        $cheque = $this->cheque();
        $this->service->mudarSituacao($cheque, SituacaoCheque::DEPOSITADO);
        $this->service->mudarSituacao($cheque->refresh(), SituacaoCheque::DEVOLVIDO);
        $redep = $this->service->mudarSituacao($cheque->refresh(), SituacaoCheque::DEPOSITADO);

        $this->assertEquals(SituacaoCheque::DEPOSITADO, $redep->situacao);
    }
}
