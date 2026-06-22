<?php

namespace Tests\Domain;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Satelite\ComodatoService;
use App\Domain\Satelite\ConvenioFechamentoService;
use App\Domain\Satelite\SituacaoValeGas;
use App\Domain\Satelite\ValeGasService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\Financeiro;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\Convenio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * N8 — satélites: fechamento de convênio gera 1 financeiro consolidado;
 * vale-gás (máquina de estados); comodato move estoque.
 */
class SateliteServiceTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private Setor $setor;
    private Produto $produto;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100]);
        $this->cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 1000, 10);
    }

    private function pedidoConcluido(float $qtd): void
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

    public function test_fechamento_de_convenio_consolida_pedidos_num_financeiro(): void
    {
        // 2 pedidos concluídos do conveniado (200 + 300 = 500).
        $this->pedidoConcluido(2); // 200
        $this->pedidoConcluido(3); // 300

        $convenio = Convenio::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'descricao' => 'Convênio Teste',
        ]);

        $fechamento = app(ConvenioFechamentoService::class)->fechar(
            $convenio, now()->subDay()->toDateString(), now()->addDay()->toDateString(),
        );

        $this->assertEquals(2, $fechamento->total_pedidos);
        $this->assertEqualsWithDelta(500, (float) $fechamento->valor_total, 0.001);
        // Gerou 1 financeiro consolidado de 500 (cada pedido já gerou o seu; este é o do convênio).
        $financeiro = Financeiro::query()->where('origem', 'convenio_fechamento')->where('origem_id', $fechamento->id)->first();
        $this->assertNotNull($financeiro);
        $this->assertEqualsWithDelta(500, (float) $financeiro->valor, 0.001);
        $this->assertEquals('FATURADO', $fechamento->situacao);
    }

    public function test_fechamento_sem_pedidos_bloqueia(): void
    {
        $convenio = Convenio::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'descricao' => 'Vazio',
        ]);

        $this->expectException(ValidationException::class);
        app(ConvenioFechamentoService::class)->fechar($convenio, now()->subDay()->toDateString(), now()->toDateString());
    }

    public function test_vale_gas_emite_com_financeiro_e_segue_maquina_de_estados(): void
    {
        $svc = app(ValeGasService::class);
        $vale = $svc->emitir([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'valor' => 110,
        ]);

        $this->assertEquals(SituacaoValeGas::EMITIDO, $vale->situacao);
        $this->assertNotNull($vale->financeiro_id);

        $pago = $svc->mudarSituacao($vale, SituacaoValeGas::PAGO);
        $usado = $svc->mudarSituacao($pago, SituacaoValeGas::UTILIZADO);
        $this->assertEquals(SituacaoValeGas::UTILIZADO, $usado->situacao);
        $this->assertNotNull($usado->utilizado_em);
    }

    public function test_vale_gas_transicao_invalida_bloqueia(): void
    {
        $vale = app(ValeGasService::class)->emitir([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'valor' => 50,
        ]);

        // EMITIDO não vai direto para UTILIZADO (precisa pagar antes).
        $this->expectException(ValidationException::class);
        app(ValeGasService::class)->mudarSituacao($vale, SituacaoValeGas::UTILIZADO);
    }

    public function test_comodato_emprestar_baixa_estoque_e_devolver_repoe(): void
    {
        $svc = app(ComodatoService::class);
        $estoque = app(EstoqueService::class);
        $saldoInicial = $estoque->saldoDerivado($this->setor->id, $this->produto->id);

        $comodato = $svc->emprestar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'produto_id' => $this->produto->id,
            'setor_id' => $this->setor->id, 'quantidade' => 5,
        ]);

        $this->assertEqualsWithDelta($saldoInicial - 5, $estoque->saldoDerivado($this->setor->id, $this->produto->id), 0.001);

        // devolução parcial
        $svc->devolver($comodato, 2);
        $this->assertEquals('PARCIAL', $comodato->refresh()->situacao);

        // devolução do restante
        $svc->devolver($comodato->refresh(), 3);
        $this->assertEquals('DEVOLVIDO', $comodato->refresh()->situacao);
        $this->assertEqualsWithDelta($saldoInicial, $estoque->saldoDerivado($this->setor->id, $this->produto->id), 0.001);
    }

    public function test_comodato_devolver_mais_que_pendente_bloqueia(): void
    {
        $comodato = Comodato::create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'produto_id' => $this->produto->id, 'setor_id' => $this->setor->id,
            'quantidade' => 4, 'quantidade_devolvida' => 0, 'situacao' => 'ATIVO', 'data_emprestimo' => now()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        app(ComodatoService::class)->devolver($comodato, 10);
    }
}
