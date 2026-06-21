<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N4 — API de pedidos: criar com itens, mudar situação (movimenta estoque),
 * kanban, escopo, RBAC.
 */
class PedidoTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);
        $setor = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100]);
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 50, 10);

        return compact('empresa', 'user', 'setor', 'produto', 'cliente');
    }

    public function test_cria_pedido_com_itens(): void
    {
        ['user' => $user, 'empresa' => $e, 'setor' => $setor, 'produto' => $p, 'cliente' => $c] = $this->cenario();
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $e->grupo_id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/pedidos', [
            'cliente_id' => $c->id,
            'pedidosituacao_id' => $situacao->id,
            'setor_id' => $setor->id,
            'itens' => [['produto_id' => $p->id, 'quantidade' => 5, 'preco_unitario' => 100]],
        ])->assertCreated()
            ->assertJsonPath('data.valor_venda', 500);

        $this->assertDatabaseCount('pedidoitens', 1);
    }

    public function test_mudar_situacao_para_concluido_baixa_estoque_via_api(): void
    {
        ['user' => $user, 'empresa' => $e, 'setor' => $setor, 'produto' => $p, 'cliente' => $c] = $this->cenario();
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $e->grupo_id]);
        $concluido = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $e->grupo_id]);

        $pedidoId = $this->actingAs($user, 'sanctum')->postJson('/api/admin/pedidos', [
            'cliente_id' => $c->id, 'pedidosituacao_id' => $pendente->id, 'setor_id' => $setor->id,
            'itens' => [['produto_id' => $p->id, 'quantidade' => 10]],
        ])->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/pedidos/{$pedidoId}/situacao", ['pedidosituacao_id' => $concluido->id])
            ->assertOk()
            ->assertJsonPath('data.efeito', 'CONCLUIDO');

        // 50 - 10 = 40.
        $resp = $this->actingAs($user, 'sanctum')->getJson("/api/admin/estoque/saldos?setor_id={$setor->id}")->json('data');
        $this->assertEqualsWithDelta(40, collect($resp)->firstWhere('produto_id', $p->id)['quantidade'], 0.001);
    }

    public function test_kanban_agrupa_por_situacao(): void
    {
        ['user' => $user, 'empresa' => $e, 'setor' => $setor, 'produto' => $p, 'cliente' => $c] = $this->cenario();
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $e->grupo_id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/pedidos', [
            'cliente_id' => $c->id, 'pedidosituacao_id' => $situacao->id, 'setor_id' => $setor->id,
            'itens' => [['produto_id' => $p->id, 'quantidade' => 3, 'preco_unitario' => 100]],
        ])->assertCreated();

        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/admin/pedidos/kanban')->assertOk();
        $coluna = collect($resp->json('data'))->firstWhere('situacao_id', $situacao->id);
        $this->assertEquals(1, $coluna['total']);
        $this->assertEqualsWithDelta(300, $coluna['valor'], 0.001);
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/pedidos')->assertStatus(403);
    }
}
