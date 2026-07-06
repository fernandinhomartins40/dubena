<?php

namespace Tests\Feature;

use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Mobile\PagamentoOnline;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 4 do PLANO_SEGURANCA_MULTITENANT_APPS — escopo por CLIENTE no app.
 *
 * O pagar() (cartão) ficou de fora do anti-IDOR que gerarPix/acompanhar já
 * tinham: qualquer cliente da empresa disparava cobrança contra pedido de outro.
 * Agora TODO acesso a pedido do app do cliente exige empresa + cliente do token;
 * e criar pedido com cliente vinculado é sempre para SI mesmo.
 */
class AppPedidoEscopoClienteTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Cliente $clienteA;

    private User $userA;

    private Cliente $clienteB;

    private Pedido $pedidoDeB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();

        $this->userA = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->clienteA = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'user_id' => $this->userA->id,
        ]);
        $this->clienteB = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);

        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create([
            'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->pedidoDeB = Pedido::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->clienteB->id, 'pedidosituacao_id' => $situacao->id,
            'datahora' => now(), 'valor_venda' => 150, 'valor_desconto' => 0,
        ]);
    }

    public function test_cliente_nao_paga_pedido_de_outro_cliente(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$this->pedidoDeB->id}/pagar", ['token' => 'tok-ok'])
            ->assertStatus(404);

        // Nenhuma transação foi criada contra o pedido alheio.
        $this->assertSame(0, PagamentoOnline::withoutTenant()->count());
    }

    public function test_cliente_nao_gera_pix_nem_acompanha_pedido_de_outro(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$this->pedidoDeB->id}/pix")
            ->assertStatus(404);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/app/v1/pedidos/{$this->pedidoDeB->id}")
            ->assertStatus(404);

        $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$this->pedidoDeB->id}/cancelar")
            ->assertStatus(404);
    }

    public function test_dono_do_pedido_paga_normalmente(): void
    {
        $situacao = PedidoSituacao::query()->where('grupo_id', $this->empresa->grupo_id)->first();
        $pedidoDeA = Pedido::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->clienteA->id, 'pedidosituacao_id' => $situacao->id,
            'datahora' => now(), 'valor_venda' => 90, 'valor_desconto' => 0,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/app/v1/pedidos/{$pedidoDeA->id}/pagar", ['token' => 'tok-ok'])
            ->assertCreated()->assertJsonPath('data.situacao', 'AUTORIZADO');
    }

    public function test_cliente_vinculado_cria_pedido_sempre_para_si(): void
    {
        $produto = \App\Models\Produto\Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'preco_venda' => 100, 'ativo' => true,
        ]);
        \App\Models\Estoque\Setor::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'ativo' => true,
        ]);

        // Tenta criar "em nome" do cliente B — o servidor força o cliente do token.
        $id = $this->actingAs($this->userA, 'sanctum')->postJson('/api/app/v1/pedidos', [
            'cliente_id' => $this->clienteB->id,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertCreated()->json('data.id');

        $this->assertSame(
            $this->clienteA->id,
            (int) Pedido::withoutTenant()->find($id)->cliente_id,
        );
    }
}
