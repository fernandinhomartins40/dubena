<?php

namespace Tests\Feature;

use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3-03 — o item da venda congela o que o produto ERA naquele momento.
 *
 * `pedidoitens` e `nota_itens` guardavam `produto_id` e preço. O preço está
 * congelado, e isso sempre esteve certo. A DESCRIÇÃO não — era lida do produto
 * na hora de exibir.
 *
 * No pedido, isso reescreve o histórico: renomear um produto faz o pedido de
 * três meses atrás dizer que o cliente comprou algo que não existia com aquele
 * nome.
 *
 * Na nota fiscal é pior. `XmlNfeBuilder` montava o `xProd` lendo a descrição
 * atual. Depois de autorizada, a NF-e é imutável na SEFAZ — mas uma reimpressão
 * de DANFE passava a mostrar a descrição NOVA, e **o papel deixava de bater com
 * o XML autorizado**. Isso é divergência fiscal, não detalhe de tela.
 */
class SnapshotDoItemTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Empresa, PedidoSituacao, Produto, Cliente} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Botijão P13 azul', 'preco_venda' => 100,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        return [$empresa, $situacao, $produto, $cliente];
    }

    private function pedidoCom(Empresa $empresa, PedidoSituacao $situacao, Cliente $cliente, Produto $produto)
    {
        return app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $situacao->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 2]]);
    }

    /** O item guarda o nome do produto na hora da venda. */
    public function test_item_congela_a_descricao_na_venda(): void
    {
        [$empresa, $situacao, $produto, $cliente] = $this->cenario();

        $pedido = $this->pedidoCom($empresa, $situacao, $cliente, $produto);

        $this->assertSame('Botijão P13 azul', $pedido->itens->first()->descricao_snapshot);
    }

    /**
     * O ponto da tarefa: renomear o produto NÃO reescreve o pedido passado.
     */
    public function test_renomear_o_produto_nao_reescreve_o_historico(): void
    {
        [$empresa, $situacao, $produto, $cliente] = $this->cenario();
        $pedido = $this->pedidoCom($empresa, $situacao, $cliente, $produto);

        $produto->update(['descricao' => 'Botijão P13 verde (novo nome)']);

        $item = $pedido->fresh()->itens->first();

        $this->assertSame('Botijão P13 azul', $item->descricao_snapshot);
        $this->assertSame('Botijão P13 azul', $item->descricaoExibida());
    }

    /**
     * O fallback atende as linhas anteriores a F3-03: `null` no snapshot
     * significa "não foi capturado", e não "produto sem nome".
     */
    public function test_item_antigo_sem_snapshot_cai_no_cadastro_atual(): void
    {
        [$empresa, $situacao, $produto, $cliente] = $this->cenario();
        $pedido = $this->pedidoCom($empresa, $situacao, $cliente, $produto);

        // Simula uma linha gravada antes desta migration.
        $item = $pedido->itens->first();
        $item->forceFill(['descricao_snapshot' => null])->save();

        $this->assertSame('Botijão P13 azul', $item->fresh()->descricaoExibida());
    }

    /** O preço já era congelado — a mudança não pode ter mexido nisso. */
    public function test_o_preco_continua_congelado(): void
    {
        [$empresa, $situacao, $produto, $cliente] = $this->cenario();
        $pedido = $this->pedidoCom($empresa, $situacao, $cliente, $produto);

        $produto->update(['preco_venda' => 999]);

        $this->assertSame('100.00', (string) $pedido->fresh()->itens->first()->preco_unitario);
    }

    /**
     * Um snapshot por item, e não por produto: dois pedidos do mesmo produto em
     * momentos diferentes guardam nomes diferentes, que é a razão de a coluna
     * viver no item.
     */
    public function test_pedidos_de_epocas_diferentes_guardam_nomes_diferentes(): void
    {
        [$empresa, $situacao, $produto, $cliente] = $this->cenario();

        $antigo = $this->pedidoCom($empresa, $situacao, $cliente, $produto);
        $produto->update(['descricao' => 'Botijão P13 verde']);
        $novo = $this->pedidoCom($empresa, $situacao, $cliente, $produto->fresh());

        $this->assertSame('Botijão P13 azul', $antigo->fresh()->itens->first()->descricao_snapshot);
        $this->assertSame('Botijão P13 verde', $novo->itens->first()->descricao_snapshot);
    }
}
