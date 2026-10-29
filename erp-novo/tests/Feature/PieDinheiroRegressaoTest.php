<?php

namespace Tests\Feature;

use App\Domain\Caixa\CaixaService;
use App\Domain\Estoque\EstoqueService;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Mobile\PedidoMobileService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Caixa\Conta;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\Financeiro;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Fiscal\NfImposto;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Fiscal\OperacaoFiscal;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regressões da auditoria PIE (docs/pie/) — lote de dinheiro, estoque e fiscal.
 * Cada cenário reproduzia o defeito (prova exploratória do revisor R3, que
 * observou os valores citados nos comentários); aqui a asserção é a correta.
 */
class PieDinheiroRegressaoTest extends TestCase
{
    use RefreshDatabase;

    private function titulo(Empresa $e, float $valor): Financeiro
    {
        return app(FinanceiroService::class)->criar([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'pagarreceber' => 'R', 'valor' => $valor,
        ]);
    }

    private function conta(Empresa $e): Conta
    {
        return app(CaixaService::class)->criarConta([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'descricao' => 'Caixa', 'saldo_inicial' => 0,
        ]);
    }

    private function saldo(Conta $conta): float
    {
        return (float) Conta::withoutTenant()->find($conta->id)->saldo_atual;
    }

    /** @param callable(): mixed $acao */
    private function assertRecusa(callable $acao): void
    {
        try {
            $acao();
            $this->fail('Era esperada recusa (ValidationException).');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_r3_02_parcela_de_titulo_agrupado_nao_recebe_baixa(): void
    {
        $e = Empresa::factory()->create();
        $conta = $this->conta($e);
        $t1 = $this->titulo($e, 100);
        $agr = app(FinanceiroService::class)->agrupar([$t1], ['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'pagarreceber' => 'R']);
        $cx = app(CaixaService::class);

        $cx->baixarParcela($conta->id, $agr->parcelas->first()->id, $e->id);
        // Antes: a parcela do original também baixava e o caixa ficava com 200.
        $this->assertRecusa(fn () => $cx->baixarParcela($conta->id, $t1->parcelas->first()->id, $e->id));
        $this->assertEqualsWithDelta(100.0, $this->saldo($conta), 0.01);
    }

    public function test_r3_02_parcela_de_titulo_cancelado_nao_recebe_baixa(): void
    {
        $e = Empresa::factory()->create();
        $conta = $this->conta($e);
        $t1 = $this->titulo($e, 100);
        app(FinanceiroService::class)->cancelar($t1);

        $this->assertRecusa(fn () => app(CaixaService::class)->baixarParcela($conta->id, $t1->parcelas->first()->id, $e->id));
        $this->assertEqualsWithDelta(0.0, $this->saldo($conta), 0.01);
    }

    public function test_r3_03_desagrupar_agrupador_ja_recebido_e_recusado(): void
    {
        $e = Empresa::factory()->create();
        $conta = $this->conta($e);
        $t1 = $this->titulo($e, 100);
        $fs = app(FinanceiroService::class);
        $agr = $fs->agrupar([$t1], ['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'pagarreceber' => 'R']);
        app(CaixaService::class)->baixarParcela($conta->id, $agr->parcelas->first()->id, $e->id);

        $this->assertRecusa(fn () => $fs->desagrupar($agr->refresh()));
        $this->assertFalse((bool) $agr->refresh()->cancelado);
        $this->assertSame($agr->id, (int) $t1->refresh()->agrupador_id);
    }

    public function test_r3_04_reparcelar_duas_vezes_nao_duplica_a_divida(): void
    {
        $e = Empresa::factory()->create();
        $t1 = $this->titulo($e, 100);
        $fs = app(FinanceiroService::class);

        $fs->reparcelar($t1->refresh(), 2);
        $this->assertRecusa(fn () => $fs->reparcelar($t1->refresh(), 2));

        $novos = Financeiro::withoutTenant()->where('origem', 'reparcelamento')->where('origem_id', $t1->id);
        $this->assertSame(1, $novos->count());
        $this->assertEqualsWithDelta(100.0, (float) $novos->sum('valor'), 0.01);
    }

    public function test_r3_04_parcela_do_original_reparcelado_nao_recebe_baixa(): void
    {
        $e = Empresa::factory()->create();
        $conta = $this->conta($e);
        $t1 = $this->titulo($e, 100);
        app(FinanceiroService::class)->reparcelar($t1->refresh(), 2);

        $this->assertRecusa(fn () => app(CaixaService::class)->baixarParcela($conta->id, $t1->parcelas()->first()->id, $e->id));
    }

    public function test_r2_08_desconto_maior_que_a_parcela_e_recusado(): void
    {
        $e = Empresa::factory()->create();
        $conta = $this->conta($e);
        $t1 = $this->titulo($e, 50);

        // Antes: "Receber -R$ 50,00" — o caixa saía dinheiro numa baixa de receber.
        $this->assertRecusa(fn () => app(CaixaService::class)->baixarParcela($conta->id, $t1->parcelas->first()->id, $e->id, desconto: 100));
        $this->assertEqualsWithDelta(0.0, $this->saldo($conta), 0.01);
        $this->assertFalse((bool) $t1->parcelas()->first()->baixado);

        // Perdão integral (líquido zero) continua possível.
        app(CaixaService::class)->baixarParcela($conta->id, $t1->parcelas->first()->id, $e->id, desconto: 50);
        $this->assertTrue((bool) $t1->parcelas()->first()->baixado);
    }

    /** @return array{0: Empresa, 1: User, 2: Setor, 3: Setor, 4: Produto, 5: Cliente, 6: PedidoSituacao, 7: PedidoSituacao, 8: PedidoSituacao} */
    private function cenarioPedido(): array
    {
        $e = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id]);
        $setor = Setor::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id]);
        $setor2 = Setor::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id]);
        $p = Produto::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'preco_venda' => 100]);
        $c = Cliente::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id]);
        app(EstoqueService::class)->entrada($setor->id, $p->id, 50, 10);
        $pend = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $e->grupo_id]);
        $conc = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $e->grupo_id]);
        $canc = PedidoSituacao::factory()->efeito(EfeitoPedido::CANCELADO)->create(['grupo_id' => $e->grupo_id]);

        return [$e, $user, $setor, $setor2, $p, $c, $pend, $conc, $canc];
    }

    public function test_r3_01_put_do_pedido_nao_muda_a_situacao(): void
    {
        [, $user, $setor, , $p, $c, $pend, $conc] = $this->cenarioPedido();
        $id = $this->actingAs($user, 'sanctum')->postJson('/api/admin/pedidos', [
            'cliente_id' => $c->id, 'pedidosituacao_id' => $pend->id, 'setor_id' => $setor->id,
            'itens' => [['produto_id' => $p->id, 'quantidade' => 10]],
        ])->assertCreated()->json('data.id');

        // Antes: 200, pedido CONCLUIDO sem baixa, sem título, sem histórico.
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/pedidos/{$id}", ['pedidosituacao_id' => $conc->id])
            ->assertStatus(422)->assertJsonValidationErrors('pedidosituacao_id');
        $this->assertSame($pend->id, (int) Pedido::withoutTenant()->find($id)->pedidosituacao_id);

        // Reenviar a mesma situação (formulário que devolve o que leu) passa.
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/pedidos/{$id}", ['pedidosituacao_id' => $pend->id, 'observacao' => 'ok'])
            ->assertOk();
    }

    public function test_r3_01_pedido_concretizado_nao_troca_setor_nem_taxa(): void
    {
        [, $user, $setor, $setor2, $p, $c, $pend, $conc, $canc] = $this->cenarioPedido();
        $id = $this->actingAs($user, 'sanctum')->postJson('/api/admin/pedidos', [
            'cliente_id' => $c->id, 'pedidosituacao_id' => $pend->id, 'setor_id' => $setor->id,
            'itens' => [['produto_id' => $p->id, 'quantidade' => 10]],
        ])->json('data.id');
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/pedidos/{$id}/situacao", ['pedidosituacao_id' => $conc->id])->assertOk();

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/pedidos/{$id}", ['setor_id' => $setor2->id])
            ->assertStatus(422)->assertJsonValidationErrors('setor_id');
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/pedidos/{$id}", ['entrega_taxa' => 999])
            ->assertStatus(422)->assertJsonValidationErrors('entrega_taxa');
        // Campo sem efeito continua editável.
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/pedidos/{$id}", ['observacao' => 'portão azul'])->assertOk();

        // Antes: o cancelamento devolvia os 10 botijões ao setor NOVO.
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/pedidos/{$id}/situacao", ['pedidosituacao_id' => $canc->id])->assertOk();
        $saldo = fn (Setor $s) => collect($this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/estoque/saldos?setor_id={$s->id}")->json('data'))->firstWhere('produto_id', $p->id)['quantidade'] ?? 0;
        $this->assertEquals(50, $saldo($setor));
        $this->assertEquals(0, $saldo($setor2));
    }

    public function test_r3_10_cliente_nao_cancela_pedido_que_ja_foi_concluido(): void
    {
        [$e, , $setor, , $p, $c, $pend, $conc, $canc] = $this->cenarioPedido();
        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'cliente_id' => $c->id,
            'pedidosituacao_id' => $pend->id, 'setor_id' => $setor->id,
        ], [['produto_id' => $p->id, 'quantidade' => 1, 'preco_unitario' => 100]]);
        $telaVelha = Pedido::withoutTenant()->with('situacao')->find($pedido->id); // ainda PENDENTE

        // O entregador conclui enquanto o app do cliente mostra a tela antiga.
        app(PedidoService::class)->mudarSituacao($pedido, $conc->id);

        $this->assertRecusa(fn () => app(PedidoMobileService::class)->cancelar($telaVelha));
        $this->assertSame($conc->id, (int) $pedido->refresh()->pedidosituacao_id);
        $this->assertTrue((bool) $pedido->estoque_movimentado);
        $this->assertNotNull($canc->id);
    }

    public function test_r3_05_e_r3_07_nota_unica_por_pedido_e_cancelamento_exige_cancelar_a_nota(): void
    {
        $e = Empresa::factory()->create();
        $setor = Setor::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id]);
        $produto = Produto::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'preco_venda' => 100]);
        $cliente = Cliente::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id]);
        ConfigFiscal::withoutTenant()->create(['empresa_id' => $e->id, 'ambiente' => 2, 'serie_nfe' => 1, 'serie_nfce' => 1, 'regime_tributario' => 1]);
        $op = OperacaoFiscal::withoutGrupo()->create(['grupo_id' => $e->grupo_id, 'descricao' => 'Venda', 'cfop' => '5102', 'ativo' => true]);
        DB::table('produto_operacao_fiscal')->insert(['operacao_fiscal_id' => $op->id, 'produto_id' => $produto->id, 'created_at' => now(), 'updated_at' => now()]);
        NfImposto::withoutTenant()->create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'operacao_fiscal_id' => $op->id, 'grupo_fiscal_id' => null,
            'cst_icms' => '00', 'aliq_icms' => 18, 'perc_bc_icms' => 100, 'cst_pis' => '01', 'aliq_pis' => 1.65, 'perc_bc_pis' => 100,
            'cst_cofins' => '01', 'aliq_cofins' => 7.6, 'perc_bc_cofins' => 100, 'pf_cst_icms' => '00', 'pf_aliq_icms' => 18,
            'pf_perc_bc_icms' => 100, 'pf_cst_pis' => '01', 'pf_aliq_pis' => 1.65, 'pf_perc_bc_pis' => 100, 'pf_cst_cofins' => '01',
            'pf_aliq_cofins' => 7.6, 'pf_perc_bc_cofins' => 100,
        ]);
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 1000, 10);
        $conc = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $e->grupo_id]);
        $canc = PedidoSituacao::factory()->efeito(EfeitoPedido::CANCELADO)->create(['grupo_id' => $e->grupo_id]);
        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $conc->id, 'setor_id' => $setor->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 3, 'preco_unitario' => 100]]);
        $user = User::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/notas/emitir', ['pedido_id' => $pedido->id, 'modelo' => 55])->assertCreated();
        // Antes: 201 de novo, duas NF-e AUTORIZADAS para o mesmo pedido.
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/notas/emitir', ['pedido_id' => $pedido->id, 'modelo' => 55])->assertStatus(422);
        $this->assertSame(1, NotaFiscal::withoutTenant()->where('pedido_id', $pedido->id)->where('situacao', 'AUTORIZADA')->count());

        // Antes: cancelava, devolvia o estoque e a nota seguia AUTORIZADA.
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/pedidos/{$pedido->id}/situacao", ['pedidosituacao_id' => $canc->id])->assertStatus(422);
        $p = Pedido::withoutTenant()->find($pedido->id);
        $this->assertSame($conc->id, (int) $p->pedidosituacao_id);
        $this->assertTrue((bool) $p->estoque_movimentado);
    }
}
