<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use App\Models\Venda\AlcadaDesconto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2 — teto de desconto.
 *
 * O legado não tem alçada: `PedidoFragment2.java:80` libera o campo de preço e
 * `MobileRepository::getPreco:602` aceita o valor que o app mandar. Estes testes
 * fixam o comportamento oposto — fail-closed por padrão, teto por regra, e o
 * piso do produto valendo até para desconto aprovado.
 */
class AlcadaDescontoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Setor $setor;

    private Produto $produto;

    private PedidoSituacao $situacao;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->setor = Setor::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        $this->produto = Produto::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'preco_venda' => 100, 'preco_venda_minimo' => null,
        ]);
        $this->cliente = Cliente::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        // pedidosituacoes é por GRUPO, não por empresa (0005_01_01_000000).
        $this->situacao = PedidoSituacao::factory()
            ->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $this->empresa->grupo_id]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 1000, 10);
        $this->actingAs($this->user);
    }

    /** @param array<string,mixed> $item */
    private function criarComDesconto(array $item): void
    {
        app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id,
            'pedidosituacao_id' => $this->situacao->id,
            'setor_id' => $this->setor->id,
            'user_id' => $this->user->id,
        ], [$item]);
    }

    public function test_sem_regra_cadastrada_nenhum_desconto_passa(): void
    {
        // Fail-closed: ausência de política é "zero", não "à vontade".
        $this->expectException(\DomainException::class);
        $this->criarComDesconto([
            'produto_id' => $this->produto->id, 'quantidade' => 1, 'desconto' => 5,
        ]);
    }

    public function test_pedido_sem_desconto_passa_sem_regra(): void
    {
        $this->criarComDesconto(['produto_id' => $this->produto->id, 'quantidade' => 2]);
        $this->assertDatabaseHas('pedidos', ['cliente_id' => $this->cliente->id, 'valor_desconto' => 0]);
    }

    public function test_desconto_dentro_do_teto_percentual_passa(): void
    {
        AlcadaDesconto::create([
            'empresa_id' => $this->empresa->id, 'percentual_max' => 10, 'ativo' => true,
        ]);

        // 2 x 100 = 200; teto 10% = 20. Desconto de 15 cabe.
        $this->criarComDesconto([
            'produto_id' => $this->produto->id, 'quantidade' => 2, 'desconto' => 15,
        ]);

        $this->assertDatabaseHas('pedidos', ['valor_desconto' => 15]);
    }

    public function test_desconto_acima_do_teto_e_recusado(): void
    {
        AlcadaDesconto::create([
            'empresa_id' => $this->empresa->id, 'percentual_max' => 10, 'ativo' => true,
        ]);

        $this->expectException(\DomainException::class);
        // 2 x 100 = 200; teto 10% = 20. Desconto de 21 estoura.
        $this->criarComDesconto([
            'produto_id' => $this->produto->id, 'quantidade' => 2, 'desconto' => 21,
        ]);
    }

    public function test_valor_max_limita_mesmo_com_percentual_folgado(): void
    {
        // Os dois tetos coexistem e vence o MENOR: 50% de 200 = 100, mas o
        // valor_max corta em 10.
        AlcadaDesconto::create([
            'empresa_id' => $this->empresa->id, 'percentual_max' => 50, 'valor_max' => 10, 'ativo' => true,
        ]);

        $this->expectException(\DomainException::class);
        $this->criarComDesconto([
            'produto_id' => $this->produto->id, 'quantidade' => 2, 'desconto' => 11,
        ]);
    }

    public function test_regra_mais_especifica_vence(): void
    {
        // Geral da empresa é restritiva; a do produto é a que vale.
        AlcadaDesconto::create([
            'empresa_id' => $this->empresa->id, 'percentual_max' => 1, 'ativo' => true,
        ]);
        AlcadaDesconto::create([
            'empresa_id' => $this->empresa->id, 'produto_id' => $this->produto->id,
            'percentual_max' => 20, 'ativo' => true,
        ]);

        // 1 x 100; a regra do produto (20%) permite 20.
        $this->criarComDesconto([
            'produto_id' => $this->produto->id, 'quantidade' => 1, 'desconto' => 20,
        ]);

        $this->assertDatabaseHas('pedidos', ['valor_desconto' => 20]);
    }

    public function test_regra_vencida_nao_vale(): void
    {
        AlcadaDesconto::create([
            'empresa_id' => $this->empresa->id, 'percentual_max' => 50, 'ativo' => true,
            'data_fim' => now()->subDay()->toDateString(),
        ]);

        $this->expectException(\DomainException::class);
        $this->criarComDesconto([
            'produto_id' => $this->produto->id, 'quantidade' => 1, 'desconto' => 5,
        ]);
    }

    public function test_regra_inativa_nao_vale(): void
    {
        AlcadaDesconto::create([
            'empresa_id' => $this->empresa->id, 'percentual_max' => 50, 'ativo' => false,
        ]);

        $this->expectException(\DomainException::class);
        $this->criarComDesconto([
            'produto_id' => $this->produto->id, 'quantidade' => 1, 'desconto' => 5,
        ]);
    }

    public function test_preco_minimo_do_produto_barra_ate_desconto_aprovado(): void
    {
        // O piso é limite do PRODUTO: nem a Central passa por cima.
        $this->produto->update(['preco_venda_minimo' => 95]);

        $this->expectException(\DomainException::class);
        app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id,
            'pedidosituacao_id' => $this->situacao->id,
            'setor_id' => $this->setor->id,
            'user_id' => $this->user->id,
            'desconto_aprovado' => true,   // aprovado, mas o piso vale mesmo assim
        ], [[
            'produto_id' => $this->produto->id, 'quantidade' => 1, 'desconto' => 10, // 100-10 = 90 < 95
        ]]);
    }

    public function test_desconto_aprovado_dispensa_o_teto_da_pessoa(): void
    {
        // Sem regra nenhuma (teto zero), mas aprovado pela Central: passa.
        app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id,
            'pedidosituacao_id' => $this->situacao->id,
            'setor_id' => $this->setor->id,
            'user_id' => $this->user->id,
            'desconto_aprovado' => true,
        ], [[
            'produto_id' => $this->produto->id, 'quantidade' => 1, 'desconto' => 30,
        ]]);

        $this->assertDatabaseHas('pedidos', ['valor_desconto' => 30]);
    }
}
