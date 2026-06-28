<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Mobile\PedidoMobileService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteTelefone;
use App\Models\Crm\Promocao;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Monitora\Cerca;
use App\Models\Pedido\Pedido;
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

    public function test_login_cliente_por_telefone_firebase_emite_token_e_vincula_user(): void
    {
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'nome' => 'Maria App', 'user_id' => null, 'ativo' => true,
        ]);
        ClienteTelefone::factory()->create(['cliente_id' => $cliente->id, 'telefone' => '(42) 99888-7766']);

        $resp = $this->postJson('/api/app/v1/cliente/login', [
            'firebase_id_token' => 'fake:+5542998887766',
            'empresa_id' => $this->empresa->id,
            'device_id' => 'cli-dev-1', 'push_token' => 'fcm-cli', 'plataforma' => 'ios',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'empresa_id']]);

        $this->assertNotEmpty($resp->json('token'));

        // O cliente passou a ter um user vinculado, na empresa certa.
        $cliente->refresh();
        $this->assertNotNull($cliente->user_id);
        $this->assertDatabaseHas('users', ['id' => $cliente->user_id, 'empresa_id' => $this->empresa->id]);
        $this->assertDatabaseHas('app_devices', ['user_id' => $cliente->user_id, 'device_id' => 'cli-dev-1']);
    }

    public function test_login_cliente_reusa_o_mesmo_user_em_logins_repetidos(): void
    {
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'user_id' => null,
        ]);
        ClienteTelefone::factory()->create(['cliente_id' => $cliente->id, 'telefone' => '42 99000-1122']);

        $primeiro = $this->postJson('/api/app/v1/cliente/login', [
            'firebase_id_token' => 'fake:+5542990001122', 'empresa_id' => $this->empresa->id,
        ])->assertOk();
        $cliente->refresh();
        $userId = $cliente->user_id;

        $this->postJson('/api/app/v1/cliente/login', [
            'firebase_id_token' => 'fake:+5542990001122', 'empresa_id' => $this->empresa->id,
        ])->assertOk();
        $cliente->refresh();

        $this->assertEquals($userId, $cliente->user_id);
    }

    public function test_login_cliente_telefone_de_outra_empresa_falha(): void
    {
        $outra = Empresa::factory()->create();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id, 'user_id' => null,
        ]);
        ClienteTelefone::factory()->create(['cliente_id' => $cliente->id, 'telefone' => '42 91234-5678']);

        // Telefone existe, mas em OUTRA empresa → não autentica na empresa pedida.
        $this->postJson('/api/app/v1/cliente/login', [
            'firebase_id_token' => 'fake:+5542912345678', 'empresa_id' => $this->empresa->id,
        ])->assertStatus(422);
    }

    public function test_login_cliente_token_invalido_401(): void
    {
        $this->postJson('/api/app/v1/cliente/login', [
            'firebase_id_token' => 'token-que-nao-comeca-com-fake', 'empresa_id' => $this->empresa->id,
        ])->assertStatus(401);
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

    public function test_init_traz_produtos_e_condicoes(): void
    {
        CondicaoPagamento::query()->create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'À vista', 'num_parcelas' => 1, 'a_vista' => true, 'ativo' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/app/v1/init')
            ->assertOk()
            ->assertJsonCount(1, 'data.produtos')
            ->assertJsonCount(1, 'data.condicoes')
            ->assertJsonPath('data.condicoes.0.descricao', 'À vista');
    }

    public function test_cupom_valido_e_invalido(): void
    {
        Promocao::query()->create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Promo 10', 'codigo' => 'DEZ',
            'inicio' => now()->subDay()->toDateString(), 'fim' => now()->addDay()->toDateString(),
            'desconto_percentual' => 10, 'ativo' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/app/v1/cupom?codigo=DEZ')
            ->assertOk()->assertJsonPath('data.desconto_percentual', 10);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/app/v1/cupom?codigo=NAOEXISTE')
            ->assertStatus(422);
    }

    public function test_cupom_aplica_desconto_no_pedido(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        Promocao::query()->create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Promo 10', 'codigo' => 'DEZ',
            'inicio' => now()->subDay()->toDateString(), 'fim' => now()->addDay()->toDateString(),
            'desconto_percentual' => 10, 'ativo' => true,
        ]);

        // 2 x 100 = 200; cupom 10% → desconto 20, venda 180.
        $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id, 'codigo_cupom' => 'DEZ',
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.valor_venda', 180)
            ->assertJsonPath('data.valor_desconto', 20);
    }

    public function test_setor_de_entrega_resolvido_por_geofence(): void
    {
        // Setor alvo coberto por uma cerca poligonal; e um setor "padrão" (id menor).
        $padrao = $this->setor; // já criado no setUp (id menor)
        $alvo = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);

        $cerca = Cerca::query()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Zona', 'setor_id' => $alvo->id, 'ativo' => true,
        ]);
        $cerca->pontos()->createMany([
            ['latitude' => -25.0, 'longitude' => -51.0, 'ordem' => 0],
            ['latitude' => -25.0, 'longitude' => -51.1, 'ordem' => 1],
            ['latitude' => -25.1, 'longitude' => -51.1, 'ordem' => 2],
            ['latitude' => -25.1, 'longitude' => -51.0, 'ordem' => 3],
        ]);

        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'latitude' => -25.05, 'longitude' => -51.05, // dentro da cerca
        ]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);

        $pedidoId = $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->json('data.id');

        $pedido = Pedido::find($pedidoId);
        $this->assertSame($alvo->id, $pedido->setor_id, 'O pedido deveria cair no setor da cerca, não no padrão.');
        $this->assertNotSame($padrao->id, $pedido->setor_id);
    }

    public function test_push_ao_cliente_na_mudanca_de_status(): void
    {
        $clienteUser = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'user_id' => $clienteUser->id]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $entregue = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->empresa->grupo_id]);

        $pedidoId = $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id, 'setor_id' => $this->setor->id,
            'entregador_user_id' => $this->user->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->json('data.id');

        // Sem FCM key (CI), o push é no-op mas o endpoint deve concluir com sucesso.
        $this->actingAs($this->user, 'sanctum')->postJson("/api/app/v1/entregador/pedidos/{$pedidoId}/status", [
            'pedidosituacao_id' => $entregue->id,
        ])->assertOk()->assertJsonPath('data.situacao_id', $entregue->id);
    }

    public function test_endpoints_exigem_autenticacao(): void
    {
        $this->getJson('/api/app/v1/produtos')->assertStatus(401);
    }

    public function test_cotacao_calcula_total_no_servidor(): void
    {
        // 2 x 100 = 200, sem cupom.
        $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/carrinho/cotacao', [
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]],
        ])->assertOk()
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.desconto', 0)
            ->assertJsonPath('data.total', 200)
            ->assertJsonPath('data.itens.0.preco_unitario', 100);
    }

    public function test_cotacao_aplica_cupom_e_lista_indisponiveis(): void
    {
        Promocao::query()->create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Promo 10', 'codigo' => 'DEZ',
            'inicio' => now()->subDay()->toDateString(), 'fim' => now()->addDay()->toDateString(),
            'desconto_percentual' => 10, 'ativo' => true,
        ]);

        // 1 x 100 = 100; cupom 10% → desconto 10, total 90. Produto 999 inexistente.
        $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/carrinho/cotacao', [
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => 1],
                ['produto_id' => 999999, 'quantidade' => 1],
            ],
            'codigo_cupom' => 'DEZ',
        ])->assertOk()
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonPath('data.desconto', 10)
            ->assertJsonPath('data.total', 90)
            ->assertJsonPath('data.indisponiveis', [999999]);
    }

    public function test_cotacao_nao_aceita_preco_do_cliente(): void
    {
        // Cliente tenta forçar preço 1; o servidor ignora e usa o preço do catálogo (100).
        $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/carrinho/cotacao', [
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1, 'preco_unitario' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.total', 100);
    }

    public function test_config_do_app_da_empresa(): void
    {
        \App\Models\EmpresaConfig::query()->create([
            'empresa_id' => $this->empresa->id,
            'tempoentrega' => 45,
            'dados' => ['app' => ['gaspovo_ativo' => true, 'video' => ['url' => 'https://x/v.mp4', 'titulo' => 'Abertura']]],
        ]);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/app/v1/config')
            ->assertOk()
            ->assertJsonPath('data.gaspovo_ativo', true)
            ->assertJsonPath('data.tempo_entrega_min', 45)
            ->assertJsonPath('data.video.titulo', 'Abertura');
    }

    public function test_cotacao_usa_preco_por_condicao_de_pagamento(): void
    {
        $condicao = CondicaoPagamento::query()->create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Crédito', 'num_parcelas' => 1, 'a_vista' => false, 'ativo' => true,
        ]);
        // Produto custa 100 à vista, mas 120 nesta condição (crédito).
        \App\Models\Produto\ProdutoCondicaoPreco::query()->create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $this->produto->id,
            'condicaopagamento_id' => $condicao->id, 'gasdopovo' => false, 'valor' => 120,
        ]);

        // Sem condição → preço base 100.
        $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/carrinho/cotacao', [
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->assertOk()->assertJsonPath('data.total', 100)
            ->assertJsonPath('data.itens.0.preco_unitario', 100);

        // Com a condição → preço 120.
        $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/carrinho/cotacao', [
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
            'condicao_id' => $condicao->id,
        ])->assertOk()->assertJsonPath('data.total', 120)
            ->assertJsonPath('data.itens.0.preco_unitario', 120);
    }

    public function test_pedido_usa_preco_por_condicao_e_ignora_preco_do_cliente(): void
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $condicao = CondicaoPagamento::query()->create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Crédito', 'num_parcelas' => 1, 'a_vista' => false, 'ativo' => true,
        ]);
        \App\Models\Produto\ProdutoCondicaoPreco::query()->create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $this->produto->id,
            'condicaopagamento_id' => $condicao->id, 'gasdopovo' => false, 'valor' => 120,
        ]);

        // Cliente tenta forçar preço 1; servidor usa 120 (preço da condição) × 2 = 240.
        $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id,
            'condicaopagamento_id' => $condicao->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2, 'preco_unitario' => 1]],
        ])->assertCreated()->assertJsonPath('data.valor_venda', 240);
    }

    public function test_pix_do_pedido_gera_cobranca_e_confirma_via_webhook(): void
    {
        // Cliente do app vinculado ao usuário do token (resolve sem cliente_id).
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'user_id' => $this->user->id,
        ]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);

        // Cria pedido 1 x 100 = 100.
        $pedidoId = $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->assertCreated()->json('data.id');

        // Gera a cobrança PIX do pedido.
        $pix = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$pedidoId}/pix")
            ->assertCreated()
            ->assertJsonPath('data.valor', 100)
            ->assertJsonPath('data.situacao', 'ATIVA');
        $txid = $pix->json('data.txid');
        $this->assertNotEmpty($txid);

        // Idempotência: chamar de novo devolve a MESMA cobrança (mesmo txid).
        $this->actingAs($this->user, 'sanctum')->postJson("/api/app/v1/pedidos/{$pedidoId}/pix")
            ->assertCreated()->assertJsonPath('data.txid', $txid);

        // Antes do pagamento, status não-pago.
        $this->actingAs($this->user, 'sanctum')->getJson("/api/app/v1/pedidos/{$pedidoId}/pix/status")
            ->assertOk()->assertJsonPath('data.pago', false);

        // Webhook do PSP confirma o pagamento (valor bate).
        $this->postJson('/api/pix/webhook', ['txid' => $txid, 'valor' => 100, 'e2eid' => 'E123'])
            ->assertOk()->assertJsonPath('situacao', 'CONCLUIDA');

        // Agora o status do pedido aparece pago.
        $this->actingAs($this->user, 'sanctum')->getJson("/api/app/v1/pedidos/{$pedidoId}/pix/status")
            ->assertOk()->assertJsonPath('data.pago', true);
    }

    public function test_pix_webhook_rejeita_valor_divergente(): void
    {
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'user_id' => $this->user->id,
        ]);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $pedidoId = $this->actingAs($this->user, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $pendente->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->json('data.id');
        $txid = $this->actingAs($this->user, 'sanctum')->postJson("/api/app/v1/pedidos/{$pedidoId}/pix")->json('data.txid');

        // Valor pago (50) difere do cobrado (100) → rejeitado (anti-fraude S3).
        $this->postJson('/api/pix/webhook', ['txid' => $txid, 'valor' => 50])->assertStatus(422);

        $this->actingAs($this->user, 'sanctum')->getJson("/api/app/v1/pedidos/{$pedidoId}/pix/status")
            ->assertOk()->assertJsonPath('data.pago', false);
    }

    public function test_endereco_do_cliente_resolvido_pelo_token(): void
    {
        // Cliente vinculado ao usuário do token (modelo F1).
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'user_id' => $this->user->id, 'endereco' => 'Rua A', 'numero' => '100',
        ]);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/app/v1/perfil/endereco')
            ->assertOk()
            ->assertJsonPath('data.endereco', 'Rua A')
            ->assertJsonPath('data.numero', '100');

        $this->actingAs($this->user, 'sanctum')->putJson('/api/app/v1/perfil/endereco', [
            'endereco' => 'Rua Nova', 'numero' => '200', 'cep' => '85000-000', 'uf' => 'PR',
            'latitude' => -25.4, 'longitude' => -51.4,
        ])->assertOk()->assertJsonPath('data.endereco', 'Rua Nova');

        $cliente->refresh();
        $this->assertEquals('Rua Nova', $cliente->endereco);
        $this->assertEquals('200', $cliente->numero);
    }
}
