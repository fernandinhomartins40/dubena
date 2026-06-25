<?php

namespace Tests\Feature;

use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F03 — emissão fiscal a partir do pedido (venda → conclusão → NFC-e). Driver SEFAZ
 * Fake (CI). Cobre o gatilho, a idempotência e o bloqueio de pedido não concluído.
 */
class PedidoNfceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    private function pedido(Empresa $empresa, EfeitoPedido $efeito): Pedido
    {
        $situacao = PedidoSituacao::factory()->create(['grupo_id' => $empresa->grupo_id, 'efeito' => $efeito]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 80, 'ncm' => '27111910']);
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        return app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'setor_id' => \App\Models\Estoque\Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id])->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 2]]);
    }

    public function test_emite_nfce_de_pedido_concluido(): void
    {
        [$user, $empresa] = $this->suporte();
        // Estoque para o pedido concluído poder baixar.
        $pedido = $this->pedido($empresa, EfeitoPedido::PENDENTE);

        // Conclui o pedido (efeito CONCLUIDO) — precisa de saldo de estoque.
        $situacaoConcl = PedidoSituacao::factory()->create(['grupo_id' => $empresa->grupo_id, 'efeito' => EfeitoPedido::CONCLUIDO]);
        app(\App\Domain\Estoque\EstoqueService::class)->entrada($pedido->setor_id, $pedido->itens->first()->produto_id, 10, 50, 'ajuste-teste', null);
        app(PedidoService::class)->mudarSituacao($pedido, $situacaoConcl->id, $user->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/pedidos/{$pedido->id}/emitir-nfce", ['modelo' => '65'])
            ->assertCreated()
            ->assertJsonPath('data.situacao', 'AUTORIZADA');

        $this->assertSame(1, NotaFiscal::query()->where('pedido_id', $pedido->id)->count());
    }

    public function test_emissao_e_idempotente(): void
    {
        [$user, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa, EfeitoPedido::PENDENTE);
        $situacaoConcl = PedidoSituacao::factory()->create(['grupo_id' => $empresa->grupo_id, 'efeito' => EfeitoPedido::CONCLUIDO]);
        app(\App\Domain\Estoque\EstoqueService::class)->entrada($pedido->setor_id, $pedido->itens->first()->produto_id, 10, 50, 'ajuste-teste', null);
        app(PedidoService::class)->mudarSituacao($pedido, $situacaoConcl->id, $user->id);

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/pedidos/{$pedido->id}/emitir-nfce")->assertCreated();
        // Segunda chamada não cria outra nota (devolve a existente, 200).
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/pedidos/{$pedido->id}/emitir-nfce")
            ->assertOk()->assertJsonPath('message', 'Documento já emitido para este pedido.');

        $this->assertSame(1, NotaFiscal::query()->where('pedido_id', $pedido->id)->count());
    }

    public function test_nao_emite_de_pedido_pendente(): void
    {
        [$user, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa, EfeitoPedido::PENDENTE);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/pedidos/{$pedido->id}/emitir-nfce")
            ->assertStatus(422);

        $this->assertSame(0, NotaFiscal::query()->where('pedido_id', $pedido->id)->count());
    }

    public function test_resource_expoe_fechadoconcluido_e_tem_nf(): void
    {
        [$user, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa, EfeitoPedido::PENDENTE);

        $this->actingAs($user, 'sanctum')->getJson("/api/admin/pedidos/{$pedido->id}")
            ->assertOk()
            ->assertJsonPath('data.fechadoconcluido', 0)
            ->assertJsonPath('data.tem_nf', false);
    }
}
