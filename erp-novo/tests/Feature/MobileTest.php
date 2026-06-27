<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Mobile\PedidoMobileService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * N10 — API mobile (cliente/entregador), auth real por token, matching geoloc,
 * pagamento online (driver fake).
 */
class MobileTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Setor $setor;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'email' => 'app@teste.com', 'password' => Hash::make('segredo123'), 'support' => true,
        ]);
        $this->setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 1000, 10);
    }

    public function test_login_do_app_emite_token_e_registra_device(): void
    {
        $resp = $this->postJson('/api/app/v1/login', [
            'email' => 'app@teste.com', 'password' => 'segredo123',
            'device_id' => 'dev-1', 'push_token' => 'fcm-abc', 'plataforma' => 'android',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'empresa_id']]);

        $this->assertNotEmpty($resp->json('token'));
        $this->assertDatabaseHas('app_devices', ['user_id' => $this->user->id, 'device_id' => 'dev-1', 'push_token' => 'fcm-abc']);
    }

    public function test_login_invalido_422(): void
    {
        $this->postJson('/api/app/v1/login', ['email' => 'app@teste.com', 'password' => 'errada'])->assertStatus(422);
    }

    public function test_catalogo_de_produtos_da_empresa(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/app/v1/produtos')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.preco', 100);
    }

    public function test_matching_de_cliente_por_geolocalizacao(): void
    {
        $proximo = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'latitude' => -23.5505, 'longitude' => -46.6333,
        ]);
        Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'latitude' => -22.9068, 'longitude' => -43.1729, // RJ, longe
        ]);

        $encontrado = app(PedidoMobileService::class)->clientePorGeoloc($this->empresa->id, -23.5506, -46.6334);
        $this->assertNotNull($encontrado);
        $this->assertEquals($proximo->id, $encontrado->id);
    }

    public function test_cria_pedido_do_app_por_cliente_id(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);

        $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $situacao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]],
        ])->assertCreated()->assertJsonPath('data.valor_venda', 200);
    }

    public function test_pagamento_online_aprovado(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $pedidoId = $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->json('data.id');

        $this->actingAs($this->user, 'sanctum')->postJson("/api/app/v1/pedidos/{$pedidoId}/pagar", [
            'token' => 'tok-ok',
        ])->assertCreated()->assertJsonPath('data.situacao', 'AUTORIZADO');
    }

    public function test_pagamento_online_negado_retorna_402(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $pedidoId = $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->json('data.id');

        $this->actingAs($this->user, 'sanctum')->postJson("/api/app/v1/pedidos/{$pedidoId}/pagar", [
            'token' => 'nego-tok',
        ])->assertStatus(402)->assertJsonPath('data.situacao', 'NEGADO');
    }

    public function test_entregador_lista_e_atualiza_status(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $entregue = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->empresa->grupo_id]);

        // Pedido atribuído ao entregador (o próprio user de suporte).
        $pedidoId = $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id, 'setor_id' => $this->setor->id,
            'entregador_user_id' => $this->user->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->json('data.id');

        $this->actingAs($this->user, 'sanctum')->getJson('/api/app/v1/entregador/pedidos')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->user, 'sanctum')->postJson("/api/app/v1/entregador/pedidos/{$pedidoId}/status", [
            'pedidosituacao_id' => $entregue->id, 'lat' => -23.55, 'lng' => -46.63,
        ])->assertOk()->assertJsonPath('data.situacao_id', $entregue->id);
    }

    public function test_um_pedido_pendente_por_cliente(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);

        $payload = [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ];

        $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', $payload)->assertCreated();
        // Segundo pedido pendente é barrado.
        $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', $payload)->assertStatus(422);
    }

    public function test_historico_e_acompanhar_do_cliente(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $pedidoId = $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]],
        ])->json('data.id');

        $this->actingAs($this->user, 'sanctum')->getJson("/api/app/v1/pedidos?cliente_id={$cliente->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.valor_venda', 200);

        $this->actingAs($this->user, 'sanctum')->getJson("/api/app/v1/pedidos/{$pedidoId}?cliente_id={$cliente->id}")
            ->assertOk()->assertJsonPath('data.efeito', 'PENDENTE');
    }

    public function test_cancelar_pedido_pendente(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        PedidoSituacao::factory()->efeito(EfeitoPedido::CANCELADO)->create(['grupo_id' => $this->empresa->grupo_id]);
        $pedidoId = $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->json('data.id');

        $this->actingAs($this->user, 'sanctum')->postJson("/api/app/v1/pedidos/{$pedidoId}/cancelar", ['cliente_id' => $cliente->id])
            ->assertOk();

        $this->actingAs($this->user, 'sanctum')->getJson("/api/app/v1/pedidos/{$pedidoId}?cliente_id={$cliente->id}")
            ->assertOk()->assertJsonPath('data.efeito', 'CANCELADO');
    }

    public function test_avaliar_pedido_uma_vez(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $pedidoId = $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->json('data.id');

        $this->actingAs($this->user, 'sanctum')->postJson("/api/app/v1/pedidos/{$pedidoId}/avaliar", [
            'cliente_id' => $cliente->id, 'rating' => 5, 'mensagem' => 'Ótimo',
        ])->assertCreated()->assertJsonPath('data.rating', 5);

        // Segunda avaliação é barrada.
        $this->actingAs($this->user, 'sanctum')->postJson("/api/app/v1/pedidos/{$pedidoId}/avaliar", [
            'cliente_id' => $cliente->id, 'rating' => 1,
        ])->assertStatus(422);
    }

    public function test_endpoints_exigem_autenticacao(): void
    {
        $this->getJson('/api/app/v1/produtos')->assertStatus(401);
    }
}
