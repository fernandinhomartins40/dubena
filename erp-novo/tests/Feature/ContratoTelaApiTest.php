<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrato entre o que a SPA LÊ e o que a API EMITE.
 *
 * A classe de defeito que estes testes cobrem é invisível para todo o resto da
 * suíte: a requisição responde 200, a lista vem com a quantidade certa de
 * linhas, nenhum erro é logado — e a tela mostra tudo vazio, porque os nomes
 * dos campos não casam.
 *
 * Foi o que aconteceu com o histórico do cliente: a API devolvia
 * `pedido_id`/`data`/`valor_venda` e a aba lia `id`/`datahora`/`valorvenda`.
 * Nenhum dos três casava; a tela exibia "#" sem número, "—" na data e R$ 0,00
 * em todas as linhas. O mesmo padrão zerava a lista de produtos
 * (`preco_venda` × `precovenda`) e os cartões do kanban de pedidos.
 *
 * A origem da divergência é histórica: o legado não usa underscore, e parte da
 * SPA foi escrita a partir das telas antigas. Onde o alias existe, os DOIS
 * nomes viajam — o teste garante que o nome consumido pela tela não suma.
 */
class ContratoTelaApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    /** Não existe `PedidoFactory`: o pedido é criado direto, como nos demais testes. */
    private function pedido(Empresa $empresa, Cliente $cliente, float $valor): Pedido
    {
        $situacao = PedidoSituacao::factory()->create(['grupo_id' => $empresa->grupo_id]);

        return Pedido::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'datahora' => '2026-08-18 10:30:00', 'valor_venda' => $valor, 'valor_desconto' => 0,
        ]);
    }

    /**
     * `HistoricoTab.tsx` lê `id`, `datahora` e `valorvenda`.
     */
    public function test_historico_do_cliente_usa_os_nomes_que_a_aba_le(): void
    {
        [$user, $empresa] = $this->suporte();

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $pedido = $this->pedido($empresa, $cliente, 149.90);

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/clientes/{$cliente->id}/historico")
            ->assertOk();

        $linha = $resp->json('data.0');

        $this->assertSame($pedido->id, $linha['id'], 'a coluna Pedido mostrava "#" sem número');
        $this->assertNotNull($linha['datahora'], 'a coluna Data mostrava "—"');
        $this->assertSame(149.9, $linha['valorvenda'], 'a coluna Valor mostrava R$ 0,00');
    }

    /**
     * `ProdutosListPage.tsx` lê `precovenda`; a coluna é `preco_venda`.
     */
    public function test_lista_de_produtos_devolve_precovenda(): void
    {
        [$user, $empresa] = $this->suporte();

        Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Botijão P13', 'preco_venda' => 120.50,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/produtos')
            ->assertOk()
            ->assertJsonPath('data.0.precovenda', 120.5);
    }

    /**
     * O formulário de produto ENVIA `precovenda`. Sem a normalização no request,
     * o `validated()` descartava o campo: a tela salvava "com sucesso" e o preço
     * continuava o mesmo.
     */
    public function test_salvar_produto_aceita_precovenda_da_spa(): void
    {
        [$user, $empresa] = $this->suporte();

        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'preco_venda' => 100,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/produtos/{$produto->id}", [
                'descricao' => $produto->descricao,
                'precovenda' => 135.75,
            ])
            ->assertOk();

        $this->assertSame(135.75, (float) $produto->fresh()->preco_venda);
    }

    /** `ListaView.tsx`, `KanbanView.tsx` e `PedidoDialogs.tsx` leem `valorvenda`. */
    public function test_pedido_devolve_valorvenda(): void
    {
        [$user, $empresa] = $this->suporte();

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $pedido = $this->pedido($empresa, $cliente, 89.90);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/pedidos/{$pedido->id}")
            ->assertOk()
            ->assertJsonPath('data.valorvenda', 89.9);
    }

    /** `InteracoesTab.tsx` lê `datahora`, `tipo` e `situacao` (rótulos). */
    public function test_interacoes_devolvem_rotulos_e_datahora(): void
    {
        [$user, $empresa] = $this->suporte();

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/clientes/{$cliente->id}/interacoes", [
                'descricao' => 'Cliente pediu retorno na segunda',
            ])
            ->assertCreated();

        $linha = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/clientes/{$cliente->id}/interacoes")
            ->assertOk()
            ->json('data.0');

        $this->assertArrayHasKey('datahora', $linha);
        $this->assertNotNull($linha['datahora'], 'a interação aparecia sem data');
        $this->assertArrayHasKey('tipo', $linha);
        $this->assertArrayHasKey('situacao', $linha);
    }
}
