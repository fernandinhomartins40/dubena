<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Logistica\JornadaService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L6 — Iniciar rota: move as entregas PENDENTES do entregador para a situação
 * "Saiu para entrega" (criando-a no grupo se não existir), de forma idempotente,
 * e exige jornada ativa.
 */
class IniciarRotaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $entregador;

    private Setor $setor;

    private Produto $produto;

    private PedidoSituacao $pendente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->entregador = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 100, 10);
        $this->pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);

        // Jornada ativa (exigida pelo endpoint).
        app(JornadaService::class)->iniciar($this->entregador, null);
    }

    private function pedido(): Pedido
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);

        return app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $this->pendente->id, 'setor_id' => $this->setor->id,
            'entregador_user_id' => $this->entregador->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1]]);
    }

    public function test_iniciar_rota_move_pendentes_para_saiu_para_entrega(): void
    {
        $p1 = $this->pedido();
        $p2 = $this->pedido();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')
            ->assertOk()
            ->assertJsonPath('data.iniciados', 2);

        // A situação "Saiu para entrega" foi criada no grupo e aplicada aos pedidos.
        $alvo = PedidoSituacao::query()
            ->where('grupo_id', $this->empresa->grupo_id)
            ->where('descricao', 'Saiu para entrega')->first();
        $this->assertNotNull($alvo);
        $this->assertSame(EfeitoPedido::PENDENTE, $alvo->efeito);
        $this->assertSame($alvo->id, $p1->refresh()->pedidosituacao_id);
        $this->assertSame($alvo->id, $p2->refresh()->pedidosituacao_id);
    }

    public function test_iniciar_rota_e_idempotente(): void
    {
        $this->pedido();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')->assertOk()
            ->assertJsonPath('data.iniciados', 1);

        // Segunda chamada: nada a mover.
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')->assertOk()
            ->assertJsonPath('data.iniciados', 0);
    }

    public function test_reusa_situacao_de_deslocamento_existente(): void
    {
        $existente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Em rota de entrega']);
        $p = $this->pedido();

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')->assertOk();

        $this->assertSame($existente->id, $p->refresh()->pedidosituacao_id);
        // Não criou uma segunda situação de rota.
        $this->assertSame(0, PedidoSituacao::query()->where('descricao', 'Saiu para entrega')->count());
    }

    public function test_sem_jornada_ativa_rejeita(): void
    {
        app(JornadaService::class)->encerrar(app(JornadaService::class)->jornadaAtiva($this->entregador->id));

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/rota/iniciar')
            ->assertStatus(422);
    }

    public function test_api_sem_accept_json_nao_da_500(): void
    {
        // Fix do shouldRenderJsonWhen: sem Accept, o 401 deve continuar 401 (JSON),
        // não um 500 de Route [login] not defined.
        $this->post('/api/app/v1/entregador/rota/iniciar')->assertStatus(401);
    }
}
