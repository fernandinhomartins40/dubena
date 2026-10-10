<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\CanalVenda;
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

    private function venda(float $qtd, ?int $entregadorUserId = null, ?CanalVenda $canal = null): void
    {
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->empresa->grupo_id, 'descricao' => 'C'.uniqid()]);
        app(PedidoService::class)->criar(array_filter([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'pedidosituacao_id' => $situacao->id, 'setor_id' => $this->setor->id,
            'entregador_user_id' => $entregadorUserId, 'datahora' => now(),
            'canal' => $canal,
        ], fn ($v) => $v !== null), [['produto_id' => $this->produto->id, 'quantidade' => $qtd, 'preco_unitario' => 100]]);
    }

    /**
     * F3-05 — "quanto do meu faturamento já vem do app?". O canal era gravado
     * desde F3-05 e não aparecia em tela nenhuma.
     */
    public function test_vendas_por_canal_separa_faturamento_e_participacao(): void
    {
        $this->venda(3, null, CanalVenda::APP_CLIENTE);   // 300
        $this->venda(1, null, CanalVenda::APP_CLIENTE);   // 100
        $this->venda(4, null, CanalVenda::INTERNO);       // 400
        $this->venda(2);                                  // 200, sem canal declarado

        $data = collect($this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/relatorios/vendas-canal'.$this->periodo())->assertOk()->json('data'))
            ->keyBy('canal');

        $this->assertSame(2, $data['App do cliente']['pedidos']);
        $this->assertEqualsWithDelta(400.0, $data['App do cliente']['total'], 0.01);
        $this->assertEqualsWithDelta(200.0, $data['App do cliente']['ticket_medio'], 0.01);
        $this->assertEqualsWithDelta(40.0, $data['App do cliente']['participacao_pct'], 0.01);
        $this->assertEqualsWithDelta(40.0, $data['Atendimento interno']['participacao_pct'], 0.01);

        // A fatia sem origem NÃO some: escondê-la faria os outros canais
        // somarem 100% de um total que não é o faturamento.
        $this->assertEqualsWithDelta(20.0, $data['Origem não registrada']['participacao_pct'], 0.01);
        $this->assertEqualsWithDelta(100.0, $data->sum('participacao_pct'), 0.2);
    }

    /** Pedido que não concretizou não é faturamento, venha de onde vier. */
    public function test_vendas_por_canal_ignora_pedido_nao_concretizado(): void
    {
        $this->venda(1, null, CanalVenda::CAMPO);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id, 'descricao' => 'P'.uniqid()]);
        app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'pedidosituacao_id' => $pendente->id, 'setor_id' => $this->setor->id,
            'datahora' => now(), 'canal' => CanalVenda::APP_CLIENTE,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 9, 'preco_unitario' => 100]]);

        $data = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/relatorios/vendas-canal'.$this->periodo())->assertOk()->json('data');

        $this->assertSame(['Venda em campo'], array_column($data, 'canal'));
    }

    /** Venda de outra empresa não entra na conta. */
    public function test_vendas_por_canal_e_da_empresa_ativa(): void
    {
        $this->venda(1, null, CanalVenda::CENTRAL);
        $outra = Empresa::factory()->create();
        $outroUser = User::factory()->create(['empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id]);

        $this->actingAs($outroUser, 'sanctum')
            ->getJson('/api/admin/relatorios/vendas-canal'.$this->periodo())->assertOk()->assertJsonCount(0, 'data');
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
