<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Relatorio\NotificarEstoqueBaixoJob;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * N12 — relatórios (Query Services), cutover:check e jobs/cron.
 */
class RelatorioTest extends TestCase
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
        $this->user = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'support' => true]);
        $this->setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100]);
        $this->cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 1000, 10);
    }

    private function vendaConcluida(float $qtd): void
    {
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Concluido '.uniqid(),
        ]);
        app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'pedidosituacao_id' => $situacao->id, 'setor_id' => $this->setor->id,
            'datahora' => now(),
        ], [['produto_id' => $this->produto->id, 'quantidade' => $qtd, 'preco_unitario' => 100]]);
    }

    public function test_relatorio_de_vendas_agrega_por_periodo(): void
    {
        $this->vendaConcluida(2); // 200
        $this->vendaConcluida(3); // 300

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/relatorios/vendas?inicio='.now()->subDay()->toDateString().'&fim='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.total', 500)
            ->assertJsonPath('data.quantidade', 2);
    }

    public function test_relatorio_financeiro_e_dre(): void
    {
        $this->vendaConcluida(5); // 500 → gera financeiro a receber (N5)

        $resp = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/relatorios/financeiro?inicio='.now()->subDay()->toDateString().'&fim='.now()->addMonth()->toDateString())
            ->assertOk();
        $this->assertEqualsWithDelta(500, $resp->json('data.a_receber'), 0.01);

        // DRE no shape consumido pela SPA: receitas[]/despesas[] + totais + resultado.
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/relatorios/dre?inicio='.now()->subDay()->toDateString().'&fim='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonStructure(['data' => ['receitas', 'despesas', 'total_receitas', 'total_despesas', 'resultado']]);
    }

    public function test_relatorio_estoque_baixo(): void
    {
        // Define mínimo acima do saldo atual de um saldo existente.
        EstoqueSaldo::query()->where('setor_id', $this->setor->id)->where('produto_id', $this->produto->id)
            ->update(['quantidade' => 5, 'quantidade_minima' => 100]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/relatorios/estoque-baixo')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_relatorios_adicionais_respondem(): void
    {
        $p = '?inicio='.now()->subMonth()->toDateString().'&fim='.now()->addDay()->toDateString();
        foreach ([
            '/api/admin/relatorios/aniversariantes?mes=6',
            '/api/admin/relatorios/vale-gas'.$p,
            '/api/admin/relatorios/comodatos',
            '/api/admin/relatorios/comissoes'.$p,
            '/api/admin/relatorios/movimentacao-caixa'.$p,
        ] as $url) {
            $this->actingAs($this->user, 'sanctum')->getJson($url)
                ->assertOk()->assertJsonStructure(['data']);
        }
    }

    public function test_export_csv_e_pdf(): void
    {
        // CSV
        $csv = $this->actingAs($this->user, 'sanctum')
            ->get('/api/admin/relatorios/comodatos?formato=csv');
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));

        // PDF (dompdf gera %PDF-)
        $pdf = $this->actingAs($this->user, 'sanctum')
            ->get('/api/admin/relatorios/comodatos?formato=pdf');
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_cutover_check_apos_etl_parcial_permanece_fechado(): void
    {
        config()->set('saas_transformation.freeze.migration_writes', false);

        // Sem dados, o portão FECHA (ex.: estados=0). Este teste exercita apenas
        // a semente autônoma de estados. A origem legado está deliberadamente
        // ausente no ambiente de teste, portanto o bypass precisa ser explícito;
        // a carga geral sem origem continua falhando fechado.
        $this->artisan('etl:run estados --eu-sei-o-que-estou-fazendo')->assertExitCode(0);
        $this->artisan('cutover:check')->assertExitCode(1);
    }

    public function test_cutover_check_fecha_sem_dados(): void
    {
        // Sem rodar o ETL, a invariante de estados (semente=27 vs 0) fecha o portão.
        $this->artisan('cutover:check')->assertExitCode(1);
    }

    public function test_notify_alertas_enfileira_job_por_empresa(): void
    {
        // Comportamento do modo LEGADO. Com o enforcement ligado o cron falha
        // fechado de proposito (ver o teste seguinte), entao o modo precisa ser
        // fixado aqui — senao o resultado depende da variavel de ambiente.
        config()->set('saas_transformation.enforcement.tenant_envelope', false);
        Queue::fake();

        $this->artisan('notify:alertas')->assertExitCode(0);

        Queue::assertPushed(NotificarEstoqueBaixoJob::class);
    }

    public function test_notify_alertas_nega_cron_sem_identidade_saas_explicita(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);

        $this->artisan('notify:alertas')->assertExitCode(1);
    }

    public function test_relatorio_sem_permissao_403(): void
    {
        $user = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'support' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/relatorios/vendas?inicio=2026-01-01&fim=2026-01-31')
            ->assertStatus(403);
    }
}
