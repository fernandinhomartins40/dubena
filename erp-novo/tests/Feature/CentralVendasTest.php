<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Venda\CentralVendasService;
use App\Domain\Venda\SituacaoSolicitacao;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use App\Models\Venda\AlcadaDesconto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3/F4 — Central de Vendas: o franqueado solicita, a Central decide e fatura.
 *
 * A regra do cliente é que o franqueado NÃO fecha o pedido. Estes testes fixam
 * isso: a solicitação não move estoque nem cria pedido; só a aprovação gera.
 */
class CentralVendasTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $vendedor;

    private User $atendente;

    private Setor $setor;

    private Produto $produto;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->vendedor = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->atendente = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->setor = Setor::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->produto = Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'preco_venda' => 100, 'preco_venda_minimo' => null,
        ]);
        $this->cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);

        PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'ordem' => 1]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'ordem' => 2]);

        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 1000, 10);
    }

    private function central(): CentralVendasService
    {
        return app(CentralVendasService::class);
    }

    /** @return \App\Models\Venda\PedidoSolicitacao */
    private function solicitar(float $desconto = 30)
    {
        return $this->central()->solicitar($this->vendedor, [
            'cliente_id' => $this->cliente->id,
            'setor_id' => $this->setor->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]],
            'desconto_solicitado' => $desconto,
            'justificativa' => 'Cliente fechou com concorrente por menos.',
        ]);
    }

    public function test_solicitacao_nao_cria_pedido_nem_move_estoque(): void
    {
        $saldoAntes = app(EstoqueService::class)->saldoDerivado($this->setor->id, $this->produto->id);

        $s = $this->solicitar();

        $this->assertSame(SituacaoSolicitacao::PENDENTE, $s->situacao);
        $this->assertNull($s->pedido_id);
        $this->assertDatabaseCount('pedidos', 0);
        $this->assertSame(
            $saldoAntes,
            app(EstoqueService::class)->saldoDerivado($this->setor->id, $this->produto->id),
            'Rascunho não pode mover estoque.'
        );
    }

    public function test_preco_vem_do_cadastro_e_nao_do_app(): void
    {
        // O app manda só produto e quantidade; se mandar preço, é ignorado —
        // é o oposto do legado (MobileRepository::getPreco:602).
        $s = $this->central()->solicitar($this->vendedor, [
            'cliente_id' => $this->cliente->id,
            'itens' => [[
                'produto_id' => $this->produto->id, 'quantidade' => 1,
                'preco_unitario' => 1, // tentativa de forçar preço
            ]],
        ]);

        $this->assertSame(100.0, (float) $s->itens[0]['preco_unitario']);
    }

    public function test_aprovacao_gera_pedido_com_o_desconto_concedido(): void
    {
        $s = $this->solicitar(30);

        $aprovada = $this->central()->aprovar($s, $this->atendente);

        $this->assertSame(SituacaoSolicitacao::APROVADA, $aprovada->situacao);
        $this->assertNotNull($aprovada->pedido_id);
        $this->assertDatabaseHas('pedidos', ['id' => $aprovada->pedido_id, 'valor_desconto' => 30]);
    }

    public function test_central_pode_aprovar_menos_do_que_foi_pedido(): void
    {
        $s = $this->solicitar(30);

        // Contraproposta: pediu 30, sai com 10.
        $aprovada = $this->central()->aprovar($s, $this->atendente, 10, 'Margem não comporta 30.');

        $this->assertSame(10.0, (float) $aprovada->desconto_aprovado);
        $this->assertDatabaseHas('pedidos', ['id' => $aprovada->pedido_id, 'valor_desconto' => 10]);
    }

    public function test_aprovacao_ignora_a_alcada_do_vendedor(): void
    {
        // Alçada apertada: 1% de 200 = 2. O vendedor não poderia conceder 30
        // sozinho — mas a Central pode, e é justamente para isso que ela existe.
        AlcadaDesconto::create([
            'empresa_id' => $this->empresa->id, 'percentual_max' => 1, 'ativo' => true,
        ]);

        $aprovada = $this->central()->aprovar($this->solicitar(30), $this->atendente);

        $this->assertDatabaseHas('pedidos', ['id' => $aprovada->pedido_id, 'valor_desconto' => 30]);
    }

    public function test_nao_aprova_duas_vezes(): void
    {
        $s = $this->solicitar();
        $this->central()->aprovar($s, $this->atendente);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->central()->aprovar($s->refresh(), $this->atendente);
    }

    public function test_recusa_encerra_sem_pedido(): void
    {
        $s = $this->central()->recusar($this->solicitar(), $this->atendente, 'Cliente inadimplente.');

        $this->assertSame(SituacaoSolicitacao::RECUSADA, $s->situacao);
        $this->assertNull($s->pedido_id);
        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_so_o_solicitante_cancela(): void
    {
        $s = $this->solicitar();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->central()->cancelar($s, $this->atendente);
    }

    public function test_faturar_baixa_estoque_pela_maquina_de_estados(): void
    {
        $saldoAntes = app(EstoqueService::class)->saldoDerivado($this->setor->id, $this->produto->id);
        $aprovada = $this->central()->aprovar($this->solicitar(10), $this->atendente);

        $this->central()->faturar($aprovada, $this->atendente);

        // 2 unidades saíram: quem baixa é o EfeitoPedido, não a Central.
        $this->assertSame(
            $saldoAntes - 2,
            app(EstoqueService::class)->saldoDerivado($this->setor->id, $this->produto->id)
        );
    }

    public function test_nao_fatura_solicitacao_nao_aprovada(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->central()->faturar($this->solicitar(), $this->atendente);
    }

    public function test_analise_mostra_o_quanto_excede_a_alcada(): void
    {
        AlcadaDesconto::create([
            'empresa_id' => $this->empresa->id, 'percentual_max' => 5, 'ativo' => true,
        ]);

        // 2 x 100 = 200; teto 5% = 10. Pediu 30 → excede em 20.
        $analise = $this->central()->analiseDeAlcada($this->solicitar(30));

        $this->assertSame(10.0, $analise['teto_do_solicitante']);
        $this->assertSame(20.0, $analise['excede_em']);
    }

    public function test_fila_lista_apenas_pendentes_da_empresa(): void
    {
        $this->solicitar();
        $this->central()->recusar($this->solicitar(), $this->atendente);

        $fila = $this->central()->fila((int) $this->empresa->id);

        $this->assertCount(1, $fila);
    }
}
