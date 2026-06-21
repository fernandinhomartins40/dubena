<?php

namespace Tests\Domain;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * BASELINE do N4: a máquina de estados move o estoque corretamente na transição.
 * - CONCLUIDO baixa estoque (SAÍDA); CANCELADO devolve (ENTRADA);
 * - idempotência (não baixa/devolve em dobro); totais conferem.
 */
class PedidoServiceTest extends TestCase
{
    use RefreshDatabase;

    private PedidoService $service;
    private EstoqueService $estoque;
    private Setor $setor;
    private Produto $produto;
    private Cliente $cliente;
    private int $grupoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PedidoService::class);
        $this->estoque = app(EstoqueService::class);

        $this->setor = Setor::factory()->create();
        $this->grupoId = $this->setor->grupo_id;
        $this->produto = Produto::factory()->create(['empresa_id' => $this->setor->empresa_id, 'grupo_id' => $this->grupoId, 'preco_venda' => 100]);
        $this->cliente = Cliente::factory()->create(['empresa_id' => $this->setor->empresa_id, 'grupo_id' => $this->grupoId]);

        // Estoque inicial para o produto.
        $this->estoque->entrada($this->setor->id, $this->produto->id, 100, 10.0);
    }

    private function situacao(EfeitoPedido $efeito): PedidoSituacao
    {
        return PedidoSituacao::factory()->efeito($efeito)->create(['grupo_id' => $this->grupoId]);
    }

    private function criarPedido(PedidoSituacao $situacao, float $qtd = 10): Pedido
    {
        return $this->service->criar([
            'empresa_id' => $this->setor->empresa_id,
            'grupo_id' => $this->grupoId,
            'cliente_id' => $this->cliente->id,
            'pedidosituacao_id' => $situacao->id,
            'setor_id' => $this->setor->id,
        ], [
            ['produto_id' => $this->produto->id, 'quantidade' => $qtd, 'preco_unitario' => 100],
        ]);
    }

    public function test_pedido_pendente_nao_movimenta_estoque(): void
    {
        $this->criarPedido($this->situacao(EfeitoPedido::PENDENTE));

        // Saldo permanece 100 (só a entrada inicial).
        $this->assertEqualsWithDelta(100, $this->estoque->saldoDerivado($this->setor->id, $this->produto->id), 0.001);
    }

    public function test_concluir_baixa_estoque(): void
    {
        $pedido = $this->criarPedido($this->situacao(EfeitoPedido::PENDENTE));
        $this->service->mudarSituacao($pedido, $this->situacao(EfeitoPedido::CONCLUIDO)->id);

        // 100 - 10 = 90.
        $this->assertEqualsWithDelta(90, $this->estoque->saldoDerivado($this->setor->id, $this->produto->id), 0.001);
        $this->assertTrue($pedido->refresh()->estoque_movimentado);
    }

    public function test_pedido_criado_ja_concluido_baixa_estoque(): void
    {
        $this->criarPedido($this->situacao(EfeitoPedido::CONCLUIDO));
        $this->assertEqualsWithDelta(90, $this->estoque->saldoDerivado($this->setor->id, $this->produto->id), 0.001);
    }

    public function test_cancelar_apos_concluir_devolve_estoque(): void
    {
        $pedido = $this->criarPedido($this->situacao(EfeitoPedido::CONCLUIDO));
        $this->assertEqualsWithDelta(90, $this->estoque->saldoDerivado($this->setor->id, $this->produto->id), 0.001);

        $this->service->mudarSituacao($pedido, $this->situacao(EfeitoPedido::CANCELADO)->id);

        // Devolveu: 90 + 10 = 100.
        $this->assertEqualsWithDelta(100, $this->estoque->saldoDerivado($this->setor->id, $this->produto->id), 0.001);
        $this->assertFalse($pedido->refresh()->estoque_movimentado);
    }

    public function test_concluir_duas_vezes_nao_baixa_em_dobro(): void
    {
        $pedido = $this->criarPedido($this->situacao(EfeitoPedido::PENDENTE));
        $concluido1 = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->grupoId, 'descricao' => 'Entregue']);
        $concluido2 = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->grupoId, 'descricao' => 'Faturado']);

        $this->service->mudarSituacao($pedido, $concluido1->id);
        // Mudar para OUTRA situação concluída não deve baixar de novo.
        $this->service->mudarSituacao($pedido, $concluido2->id);

        $this->assertEqualsWithDelta(90, $this->estoque->saldoDerivado($this->setor->id, $this->produto->id), 0.001);
    }

    public function test_totais_do_pedido_conferem(): void
    {
        $pedido = $this->criarPedido($this->situacao(EfeitoPedido::PENDENTE));
        // 10 * 100 = 1000 (sem taxa de entrega).
        $this->assertEqualsWithDelta(1000, (float) $pedido->valor_venda, 0.001);
    }

    public function test_concluir_sem_saldo_suficiente_bloqueia(): void
    {
        // Pedido de 200 (estoque só tem 100).
        $pedido = $this->criarPedido($this->situacao(EfeitoPedido::PENDENTE), qtd: 200);

        $this->expectException(ValidationException::class);
        $this->service->mudarSituacao($pedido, $this->situacao(EfeitoPedido::CONCLUIDO)->id);
    }
}
