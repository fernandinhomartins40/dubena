<?php

namespace Tests\Domain;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\PedidoService;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Financeiro\Financeiro;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BASELINE C4 — o financeiro do pedido passa a respeitar a CONDIÇÃO DE PAGAMENTO
 * (a auditoria mostrou que gerava sempre 1 parcela). À vista → 1; a prazo → N.
 */
class PedidoCondicaoPagamentoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private PedidoService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
        $this->svc = app(PedidoService::class);
    }

    private function cliente(): Cliente
    {
        return Cliente::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
        ]);
    }

    /** @return array{0: PedidoSituacao, 1: Setor, 2: Produto} */
    private function cenario(): array
    {
        $concluido = PedidoSituacao::create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Concluído', 'efeito' => 'CONCLUIDO', 'ativo' => true,
        ]);
        $setor = Setor::create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Loja', 'ativo' => true]);
        $produto = Produto::create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'P13', 'preco_venda' => 120.00, 'custo_medio' => 90, 'ativo' => true,
        ]);
        // saldo para baixar
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 100, 90.0);

        return [$concluido, $setor, $produto];
    }

    public function test_a_prazo_em_3x_gera_tres_parcelas(): void
    {
        [$concluido, $setor, $produto] = $this->cenario();

        $cond = CondicaoPagamento::create([
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => '3x',
            'num_parcelas' => 3,
            'intervalo_dias' => 30,
            'dias_primeira' => 30,
            'a_vista' => false,
        ]);

        $pedido = $this->svc->criar([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente()->id,
            'setor_id' => $setor->id,
            'condicaopagamento_id' => $cond->id,
            'pedidosituacao_id' => $concluido->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 1]]);

        $financeiro = Financeiro::query()
            ->where('origem', 'pedido')->where('origem_id', $pedido->id)->first();
        $this->assertNotNull($financeiro);
        $this->assertSame(3, $financeiro->parcelas()->count());
        // Σ parcelas = valor do título (invariante).
        $this->assertEqualsWithDelta(120.00, (float) $financeiro->parcelas()->sum('valor'), 0.001);
    }

    public function test_sem_condicao_gera_uma_parcela(): void
    {
        [$concluido, $setor, $produto] = $this->cenario();

        $pedido = $this->svc->criar([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente()->id,
            'setor_id' => $setor->id,
            'pedidosituacao_id' => $concluido->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 1]]);

        $financeiro = Financeiro::query()
            ->where('origem', 'pedido')->where('origem_id', $pedido->id)->first();
        $this->assertNotNull($financeiro);
        $this->assertSame(1, $financeiro->parcelas()->count());
    }
}
