<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\Financeiro;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N5 — API do financeiro + integração com Pedido (gancho N4↔N5).
 */
class FinanceiroTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        return [$user, $empresa];
    }

    public function test_cria_lancamento_e_resumo(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/financeiro/lancamentos', [
            'pagarreceber' => 'R', 'descricao' => 'Venda avulsa', 'valor' => 300, 'num_parcelas' => 3,
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/financeiro/lancamentos')
            ->assertOk()
            ->assertJsonCount(3, 'data'); // 3 parcelas

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/financeiro/lancamentos/resumo')
            ->assertOk()
            ->assertJsonPath('data.a_receber', 300);
    }

    public function test_pedido_concluido_gera_financeiro_e_cancelado_estorna(): void
    {
        [$user, $empresa] = $this->suporte();
        $setor = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100]);
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 100, 10);

        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $empresa->grupo_id]);
        $concluido = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $empresa->grupo_id]);
        $cancelado = PedidoSituacao::factory()->efeito(EfeitoPedido::CANCELADO)->create(['grupo_id' => $empresa->grupo_id]);

        $service = app(PedidoService::class);
        $pedido = $service->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id, 'setor_id' => $setor->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 5, 'preco_unitario' => 100]]);

        // Concluir → gera financeiro de 500 (5 * 100).
        $service->mudarSituacao($pedido, $concluido->id);
        $fin = Financeiro::query()->where('origem', 'pedido')->where('origem_id', $pedido->id)->where('cancelado', false)->first();
        $this->assertNotNull($fin);
        $this->assertEqualsWithDelta(500, (float) $fin->valor, 0.001);

        // Cancelar → estorna o financeiro.
        $service->mudarSituacao($pedido->refresh(), $cancelado->id);
        $this->assertTrue($fin->refresh()->cancelado);
    }

    public function test_plano_de_contas_crud(): void
    {
        [$user] = $this->suporte();

        $id = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/financeiro/planos-conta', ['descricao' => 'Receitas', 'pagarreceber' => 'R'])
            ->assertCreated()->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/financeiro/planos-conta')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/financeiro/planos-conta/{$id}")->assertOk();
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/financeiro/lancamentos')->assertStatus(403);
    }
}
