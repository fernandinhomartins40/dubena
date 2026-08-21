<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Fiscal\CupomTextoService;
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
 * F8 (parte independente de hardware) — cupom em texto para impressora térmica.
 *
 * O conteúdo é decidido no servidor; o app só transmite os bytes. Assim uma
 * correção de layout não exige republicar APK — e o layout pode ser testado sem
 * impressora nenhuma, que é o que estes testes fazem.
 */
class CupomTextoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $entregador;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create(['nome_fantasia' => 'Gas Guarapuava']);
        $this->entregador = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $setor = Setor::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'preco_venda' => 100, 'descricao' => 'Botijao 13kg',
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'nome' => 'Dona Maria', 'endereco' => 'Rua das Flores', 'numero' => '100',
        ]);
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $this->empresa->grupo_id]);

        app(EstoqueService::class)->entrada($setor->id, $produto->id, 100, 10);

        $this->pedido = app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'setor_id' => $setor->id,
            'pedidosituacao_id' => $situacao->id,
        ], [[
            'produto_id' => $produto->id, 'quantidade' => 2, 'preco_unitario' => 100,
        ]]);

        $this->pedido->forceFill(['entregador_user_id' => $this->entregador->id])->save();
    }

    private function cupom(int $largura = CupomTextoService::LARGURA_PADRAO): array
    {
        return app(CupomTextoService::class)->doPedido($this->pedido->fresh(), $largura);
    }

    public function test_toda_linha_respeita_a_largura(): void
    {
        // Numa impressora de coluna, uma linha maior que a largura vaza para a
        // seguinte e desmonta o layout inteiro.
        foreach ($this->cupom() as $linha) {
            $this->assertLessThanOrEqual(
                CupomTextoService::LARGURA_PADRAO,
                mb_strlen($linha),
                "Linha excede a largura: {$linha}"
            );
        }
    }

    public function test_largura_menor_tambem_e_respeitada(): void
    {
        // 32 colunas é comum em impressora de bolso.
        foreach ($this->cupom(32) as $linha) {
            $this->assertLessThanOrEqual(32, mb_strlen($linha));
        }
    }

    public function test_traz_o_que_o_cliente_precisa_conferir(): void
    {
        $texto = implode("\n", $this->cupom());

        $this->assertStringContainsString('GAS GUARAPUAVA', $texto);
        $this->assertStringContainsString('COMPROVANTE DE ENTREGA', $texto);
        $this->assertStringContainsString('Dona Maria', $texto);
        $this->assertStringContainsString('Rua das Flores', $texto);
        $this->assertStringContainsString('Botijao 13kg', $texto);
        $this->assertStringContainsString('200,00', $texto);   // 2 x 100
        $this->assertStringContainsString('Assinatura do cliente', $texto);
    }

    public function test_desconto_so_aparece_quando_existe(): void
    {
        // Linha de desconto zerada num cupom só ocupa papel e confunde.
        $this->assertStringNotContainsString('Desconto:', implode("\n", $this->cupom()));

        $this->pedido->forceFill(['valor_desconto' => 15])->save();

        $this->assertStringContainsString('Desconto:', implode("\n", $this->cupom()));
    }

    public function test_a_rota_do_app_devolve_as_linhas(): void
    {
        $token = $this->entregador->createToken('app', ['role:entregador'])->plainTextToken;

        $r = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/app/v1/entregador/pedidos/{$this->pedido->id}/cupom")
            ->assertOk();

        $this->assertSame(55, $r->json('data.largura'));
        $this->assertIsArray($r->json('data.linhas'));
        $this->assertNotEmpty($r->json('data.linhas'));
    }

    public function test_pedido_de_outro_entregador_nao_e_impresso(): void
    {
        $outro = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $token = $outro->createToken('app', ['role:entregador'])->plainTextToken;

        // Imprimir pedido alheio exporia nome e endereço de cliente de outro.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/app/v1/entregador/pedidos/{$this->pedido->id}/cupom")
            ->assertStatus(404);
    }
}
