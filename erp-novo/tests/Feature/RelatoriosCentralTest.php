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
use App\Models\Rh\Colaborador;
use App\Models\Rh\ColaboradorComissao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F10 — central de relatórios: catálogo, dispatcher genérico /relatorios/{slug},
 * novos relatórios e comissão pela matemática fina (ComissaoService).
 */
class RelatoriosCentralTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Setor $setor;

    private Produto $produto;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100]);
        $this->cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 1000, 10);
    }

    private function venda(float $qtd, ?int $entregadorUserId = null): void
    {
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->empresa->grupo_id, 'descricao' => 'C'.uniqid()]);
        app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'pedidosituacao_id' => $situacao->id, 'setor_id' => $this->setor->id,
            'entregador_user_id' => $entregadorUserId, 'datahora' => now(),
        ], [['produto_id' => $this->produto->id, 'quantidade' => $qtd, 'preco_unitario' => 100]]);
    }

    private function periodo(): string
    {
        return '?inicio='.now()->subDay()->toDateString().'&fim='.now()->addDay()->toDateString();
    }

    public function test_catalogo_lista_todos_os_relatorios(): void
    {
        $data = $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/relatorios/catalogo')->assertOk()->json('data');
        $slugs = collect($data)->pluck('slug');
        foreach (['vendas', 'vendas-entregador', 'nf-emitidas', 'promocoes', 'veiculos'] as $s) {
            $this->assertTrue($slugs->contains($s), "catálogo sem {$s}");
        }
    }

    public function test_novos_relatorios_respondem_via_dispatcher(): void
    {
        $this->venda(2);
        $p = $this->periodo();
        foreach ([
            "/api/admin/relatorios/vendas-entregador{$p}",
            "/api/admin/relatorios/vendas-operacao{$p}",
            "/api/admin/relatorios/vendas-produto{$p}",
            "/api/admin/relatorios/nf-emitidas{$p}",
            "/api/admin/relatorios/nf-recebidas{$p}",
            '/api/admin/relatorios/promocoes',
            '/api/admin/relatorios/veiculos',
        ] as $url) {
            $this->actingAs($this->user, 'sanctum')->getJson($url)->assertOk()->assertJsonStructure(['data']);
        }
    }

    public function test_slug_desconhecido_da_404(): void
    {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/relatorios/inexistente')->assertNotFound();
    }

    public function test_export_csv_do_dispatcher(): void
    {
        $resp = $this->actingAs($this->user, 'sanctum')->get('/api/admin/relatorios/veiculos?formato=csv');
        $resp->assertOk();
        $this->assertStringContainsString('text/csv', $resp->headers->get('Content-Type'));
    }

    public function test_comissoes_usa_matematica_fina(): void
    {
        // Entregador com regra de comissão 10% sobre o produto.
        $colab = Colaborador::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'user_id' => $this->user->id, 'entregador' => true]);
        ColaboradorComissao::query()->create([
            'empresa_id' => $this->empresa->id, 'colaborador_id' => $colab->id,
            'produto_id' => $this->produto->id, 'setor_id' => $this->setor->id,
            'tipo_comissao' => 1, 'percentual' => 10, 'ativo' => true,
        ]);
        $this->venda(2, $this->user->id); // 2 × 100 = 200 → 10% = 20

        $data = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/relatorios/comissoes'.$this->periodo())->assertOk()->json('data');

        $this->assertNotEmpty($data);
        $this->assertEqualsWithDelta(20.0, (float) $data[0]['comissao_total'], 0.01);
        $this->assertArrayHasKey('comissao_percentual', $data[0]); // matemática fina, não média de %
    }
}
