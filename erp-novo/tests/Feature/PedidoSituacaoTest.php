<?php

namespace Tests\Feature;

use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F18.K — CRUD das colunas do Kanban (situações de pedido): criar/editar/excluir,
 * cor, efeito (status), unicidade por grupo e bloqueio de exclusão com pedidos.
 */
class PedidoSituacaoTest extends TestCase
{
    use RefreshDatabase;

    private function user(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    public function test_cria_situacao_com_cor_e_efeito(): void
    {
        [$user, $e] = $this->user();

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/pedidos/situacoes', [
            'descricao' => 'Em rota',
            'efeito' => EfeitoPedido::PENDENTE->value,
            'cor' => '#FF6200',
        ])->assertCreated();

        $resp->assertJsonPath('data.descricao', 'Em rota');
        $resp->assertJsonPath('data.cor', '#FF6200');
        $this->assertDatabaseHas('pedidosituacoes', ['grupo_id' => $e->grupo_id, 'descricao' => 'Em rota', 'cor' => '#FF6200']);
    }

    public function test_cor_invalida_e_rejeitada(): void
    {
        [$user] = $this->user();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/pedidos/situacoes', [
            'descricao' => 'X', 'efeito' => EfeitoPedido::PENDENTE->value, 'cor' => 'laranja',
        ])->assertStatus(422)->assertJsonValidationErrorFor('cor');
    }

    public function test_descricao_unica_por_grupo(): void
    {
        [$user, $e] = $this->user();
        PedidoSituacao::factory()->create(['grupo_id' => $e->grupo_id, 'descricao' => 'Aberto']);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/pedidos/situacoes', [
            'descricao' => 'Aberto', 'efeito' => EfeitoPedido::PENDENTE->value,
        ])->assertStatus(422)->assertJsonValidationErrorFor('descricao');
    }

    public function test_edita_situacao(): void
    {
        [$user, $e] = $this->user();
        $s = PedidoSituacao::factory()->create(['grupo_id' => $e->grupo_id, 'descricao' => 'Velho']);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/pedidos/situacoes/{$s->id}", [
            'descricao' => 'Novo', 'efeito' => EfeitoPedido::CONCLUIDO->value, 'cor' => '#00AA00',
        ])->assertOk()->assertJsonPath('data.efeito', 'CONCLUIDO');

        $this->assertDatabaseHas('pedidosituacoes', ['id' => $s->id, 'descricao' => 'Novo', 'cor' => '#00AA00']);
    }

    public function test_exclui_situacao_vazia(): void
    {
        [$user, $e] = $this->user();
        $s = PedidoSituacao::factory()->create(['grupo_id' => $e->grupo_id]);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/admin/pedidos/situacoes/{$s->id}")->assertOk();
        $this->assertDatabaseMissing('pedidosituacoes', ['id' => $s->id]);
    }

    public function test_bloqueia_exclusao_com_pedidos_vinculados(): void
    {
        [$user, $e] = $this->user();
        $setor = Setor::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id]);
        $cliente = Cliente::factory()->create(['empresa_id' => $e->id, 'grupo_id' => $e->grupo_id]);
        $s = PedidoSituacao::factory()->create(['grupo_id' => $e->grupo_id]);
        Pedido::query()->create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $s->id, 'setor_id' => $setor->id,
            'datahora' => now(), 'valor_venda' => 0,
        ]);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/admin/pedidos/situacoes/{$s->id}")
            ->assertStatus(422)->assertJsonValidationErrorFor('situacao');
        $this->assertDatabaseHas('pedidosituacoes', ['id' => $s->id]);
    }
}
