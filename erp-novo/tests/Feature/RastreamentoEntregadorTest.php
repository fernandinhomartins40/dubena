<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Logistica\JornadaService;
use App\Domain\Mobile\Events\EntregadorPosicaoAtualizada;
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
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P6 — Rastreamento do entregador em tempo real. Ping persiste o snapshot e publica
 * a posição só nos pedidos ATIVOS do entregador (privacidade: cessa ao concluir).
 * Cobre também o endpoint e o fix de segurança da situação por grupo.
 */
class RastreamentoEntregadorTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $entregador;

    private Setor $setor;

    private Produto $produto;

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

        // L4: o ping de posição agora exige jornada ativa — abre uma para o entregador.
        app(JornadaService::class)->iniciar($this->entregador, null);
    }

    private function pedido(PedidoSituacao $situacao): Pedido
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);

        return app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id, 'setor_id' => $this->setor->id,
            'entregador_user_id' => $this->entregador->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1]]);
    }

    public function test_ping_persiste_snapshot_e_publica_nos_pedidos_ativos(): void
    {
        Event::fake([EntregadorPosicaoAtualizada::class]);

        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $pedido = $this->pedido($pendente);

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/posicao', ['latitude' => -25.39, 'longitude' => -51.46, 'velocidade' => 30])
            ->assertOk()
            ->assertJsonPath('data.pedidos_notificados', 1);

        // Snapshot persistido (1 por entregador).
        $this->assertDatabaseHas('entregador_posicoes_ultima', [
            'entregador_user_id' => $this->entregador->id, 'empresa_id' => $this->empresa->id,
        ]);

        // Evento publicado no canal do pedido ativo.
        Event::assertDispatched(EntregadorPosicaoAtualizada::class, fn (EntregadorPosicaoAtualizada $e) => $e->pedidoId === $pedido->id);
    }

    public function test_ping_nao_publica_em_pedido_concluido(): void
    {
        Event::fake([EntregadorPosicaoAtualizada::class]);

        $concluido = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->empresa->grupo_id]);
        $this->pedido($concluido); // pedido já entregue

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/posicao', ['latitude' => -25.39, 'longitude' => -51.46])
            ->assertOk()
            ->assertJsonPath('data.pedidos_notificados', 0);

        Event::assertNotDispatched(EntregadorPosicaoAtualizada::class);
    }

    public function test_snapshot_e_upsert_unico_por_entregador(): void
    {
        $this->actingAs($this->entregador, 'sanctum')->postJson('/api/app/v1/entregador/posicao', ['latitude' => -25.0, 'longitude' => -51.0]);
        $this->actingAs($this->entregador, 'sanctum')->postJson('/api/app/v1/entregador/posicao', ['latitude' => -26.0, 'longitude' => -52.0]);

        $this->assertDatabaseCount('entregador_posicoes_ultima', 1);
        $this->assertDatabaseHas('entregador_posicoes_ultima', ['entregador_user_id' => $this->entregador->id, 'latitude' => -26.0]);
    }

    public function test_atualizar_status_rejeita_situacao_de_outro_grupo(): void
    {
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $pedido = $this->pedido($pendente);

        // Situação de OUTRO grupo (id válido, grupo errado) — fix de segurança §6.
        $outraEmpresa = Empresa::factory()->create();
        $situacaoIntrusa = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $outraEmpresa->grupo_id]);

        $this->actingAs($this->entregador, 'sanctum')
            ->postJson("/api/app/v1/entregador/pedidos/{$pedido->id}/status", ['pedidosituacao_id' => $situacaoIntrusa->id])
            ->assertStatus(422);
    }
}
