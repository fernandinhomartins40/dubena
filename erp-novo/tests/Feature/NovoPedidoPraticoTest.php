<?php

namespace Tests\Feature;

use App\Domain\Pedido\EfeitoPedido;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClientePreco;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Novo pedido" para quem atende o telefone: o que o sistema já sabe não é
 * perguntado de novo, e o que o formulário pede é de fato gravado.
 */
class NovoPedidoPraticoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
    }

    private function doGrupo(): array
    {
        return ['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id];
    }

    /**
     * O defeito: o diálogo exigia condição de pagamento e setor, mas o
     * `PedidoRequest` não validava a condição (o `safe()` a descartava) e o
     * setor ia com o nome errado. Todo pedido do painel nascia sem os dois.
     */
    public function test_condicao_setor_e_atendente_sao_gravados(): void
    {
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $produto = Produto::factory()->create($this->doGrupo() + ['preco_venda' => 100]);
        $cliente = Cliente::factory()->create($this->doGrupo());
        $condicao = CondicaoPagamento::query()->create(['grupo_id' => $this->empresa->grupo_id, 'descricao' => 'À vista', 'ativo' => true]);
        $setor = Setor::query()->create($this->doGrupo() + ['descricao' => 'Depósito', 'ativo' => true]);

        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/pedidos', [
            'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $situacao->id,
            'condicaopagamento_id' => $condicao->id,
            'setor_id' => $setor->id,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertCreated();

        $pedido = Pedido::query()->latest('id')->first();
        $this->assertSame($condicao->id, $pedido->condicaopagamento_id);
        $this->assertSame($setor->id, $pedido->setor_id);
        $this->assertSame($this->user->id, $pedido->atendente_user_id, 'quem lança é quem atende');
    }

    /** Condição de OUTRO grupo não passa: a regra nova respeita a fronteira. */
    public function test_condicao_de_outro_tenant_e_recusada(): void
    {
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $produto = Produto::factory()->create($this->doGrupo());
        $cliente = Cliente::factory()->create($this->doGrupo());
        $outra = Empresa::factory()->create();
        $alheia = CondicaoPagamento::query()->withoutGlobalScopes()->create(['grupo_id' => $outra->grupo_id, 'descricao' => 'Alheia', 'ativo' => true]);

        $this->actingAs($this->user, 'sanctum')->postJson('/api/admin/pedidos', [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'condicaopagamento_id' => $alheia->id,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('condicaopagamento_id');
    }

    /** O "mesmo de sempre": tudo vem do último pedido, com o preço de HOJE. */
    public function test_sugestao_repete_o_ultimo_pedido_com_preco_atual(): void
    {
        $padrao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id, 'padrao_tela_pedido' => true, 'descricao' => 'Novo']);
        $botijao = Produto::factory()->create($this->doGrupo() + ['descricao' => 'Botijão P13', 'preco_venda' => 110, 'ativo' => true]);
        $agua = Produto::factory()->create($this->doGrupo() + ['descricao' => 'Água 20L', 'preco_venda' => 15, 'ativo' => true]);
        $foraDeLinha = Produto::factory()->create($this->doGrupo() + ['descricao' => 'Antigo', 'preco_venda' => 1, 'ativo' => false]);
        $cliente = Cliente::factory()->create($this->doGrupo() + ['nome' => 'Joana', 'numero' => '45']);
        $condicao = CondicaoPagamento::query()->create(['grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Pix', 'ativo' => true]);
        ClientePreco::query()->create(['cliente_id' => $cliente->id, 'produto_id' => $botijao->id, 'preco' => 105]);

        $pedido = Pedido::query()->create($this->doGrupo() + [
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $padrao->id, 'condicaopagamento_id' => $condicao->id, 'datahora' => now()->subDays(20),
        ]);
        $pedido->itens()->create(['produto_id' => $botijao->id, 'quantidade' => 2, 'preco_unitario' => 99, 'valor_total' => 198]);
        $pedido->itens()->create(['produto_id' => $agua->id, 'quantidade' => 1, 'preco_unitario' => 12, 'valor_total' => 12]);
        $pedido->itens()->create(['produto_id' => $foraDeLinha->id, 'quantidade' => 1, 'preco_unitario' => 1, 'valor_total' => 1]);

        $r = $this->actingAs($this->user, 'sanctum')->getJson("/api/admin/pedidos/sugestao?cliente_id={$cliente->id}")->assertOk();

        $r->assertJsonPath('data.situacao_inicial.label', 'Novo')
            ->assertJsonPath('data.ultimo_pedido.condicaopagamento.label', 'Pix')
            ->assertJsonCount(2, 'data.ultimo_pedido.itens')
            ->assertJsonPath('data.ultimo_pedido.itens.0.quantidade', 2)
            // Preço de hoje, não o de 20 dias atrás — e o combinado vence o de tabela.
            ->assertJsonPath('data.ultimo_pedido.itens.0.preco_unitario', 105)
            ->assertJsonPath('data.ultimo_pedido.itens.0.origem_preco', 'Preço combinado com o cliente')
            ->assertJsonPath('data.ultimo_pedido.itens.1.preco_unitario', 15)
            ->assertJsonPath('data.ultimo_pedido.itens.1.origem_preco', 'Preço de venda');
    }

    /** Só número não é endereço: o atendente é avisado antes de salvar. */
    public function test_endereco_sem_rua_e_marcado_incompleto(): void
    {
        $cliente = Cliente::factory()->create($this->doGrupo() + ['numero' => '45', 'endereco' => null, 'rua_id' => null]);

        $this->actingAs($this->user, 'sanctum')->getJson("/api/admin/pedidos/sugestao?cliente_id={$cliente->id}")
            ->assertOk()
            ->assertJsonPath('data.cliente.endereco.completo', false);
    }

    /** Cliente novo: sem histórico, mas a situação inicial já vem pronta. */
    public function test_cliente_sem_pedido_recebe_so_o_que_existe(): void
    {
        PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->empresa->grupo_id]);
        $aberta = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $cliente = Cliente::factory()->create($this->doGrupo());

        $this->actingAs($this->user, 'sanctum')->getJson("/api/admin/pedidos/sugestao?cliente_id={$cliente->id}")
            ->assertOk()
            ->assertJsonPath('data.ultimo_pedido', null)
            ->assertJsonPath('data.situacao_inicial.id', $aberta->id);
    }

    /** Sugestão e preço respeitam a fronteira: cliente de outra empresa é 422. */
    public function test_sugestao_nao_vaza_cliente_de_outra_empresa(): void
    {
        $outra = Empresa::factory()->create();
        $alheio = Cliente::factory()->create(['empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id]);

        $this->actingAs($this->user, 'sanctum')->getJson("/api/admin/pedidos/sugestao?cliente_id={$alheio->id}")->assertStatus(422);
        $this->actingAs($this->user, 'sanctum')->getJson("/api/admin/pedidos/precos?cliente_id={$alheio->id}&produtos[]=1")->assertStatus(422);
    }
}
