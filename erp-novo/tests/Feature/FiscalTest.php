<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Fiscal\SituacaoNota;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Fiscal\NfImposto;
use App\Models\Fiscal\OperacaoFiscal;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * N9 — emissão fiscal (gate SEFAZ fake): emite do pedido, numera sob lock,
 * cancela; API. O cálculo de imposto tem baseline próprio (CalculoImpostoTest).
 */
class FiscalTest extends TestCase
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
        ConfigFiscal::withoutTenant()->create([
            'empresa_id' => $this->empresa->id,
            'ambiente' => 2,
            'serie_nfe' => 1,
            'serie_nfce' => 1,
            'regime_tributario' => 1,
        ]);
        $operacao = OperacaoFiscal::withoutGrupo()->create([
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Venda tributada',
            'cfop' => '5102',
            'ativo' => true,
        ]);
        DB::table('produto_operacao_fiscal')->insert([
            'operacao_fiscal_id' => $operacao->id,
            'produto_id' => $this->produto->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        NfImposto::withoutTenant()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'operacao_fiscal_id' => $operacao->id,
            'grupo_fiscal_id' => null,
            'cst_icms' => '00',
            'aliq_icms' => 18,
            'perc_bc_icms' => 100,
            'cst_pis' => '01',
            'aliq_pis' => 1.65,
            'perc_bc_pis' => 100,
            'cst_cofins' => '01',
            'aliq_cofins' => 7.6,
            'perc_bc_cofins' => 100,
            'pf_cst_icms' => '00',
            'pf_aliq_icms' => 18,
            'pf_perc_bc_icms' => 100,
            'pf_cst_pis' => '01',
            'pf_aliq_pis' => 1.65,
            'pf_perc_bc_pis' => 100,
            'pf_cst_cofins' => '01',
            'pf_aliq_cofins' => 7.6,
            'pf_perc_bc_cofins' => 100,
        ]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 1000, 10);
    }

    private function pedido(float $qtd = 10): Pedido
    {
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Concluido '.uniqid(),
        ]);

        return app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'pedidosituacao_id' => $situacao->id, 'setor_id' => $this->setor->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => $qtd, 'preco_unitario' => 100]]);
    }

    public function test_emite_nota_do_pedido_autorizada_com_chave(): void
    {
        $nota = app(FiscalService::class)->emitirDoPedido($this->pedido(10), ModeloDocumento::NFE);

        $this->assertEquals(SituacaoNota::AUTORIZADA, $nota->situacao);
        $this->assertEquals(44, strlen($nota->chave));
        $this->assertNotEmpty($nota->protocolo);
        // Totais: produtos 1000; ICMS 18% = 180.
        $this->assertEqualsWithDelta(1000, (float) $nota->valor_produtos, 0.001);
        $this->assertEqualsWithDelta(180, (float) $nota->valor_icms, 0.001);
    }

    public function test_numeracao_sequencial_sem_duplicar(): void
    {
        $svc = app(FiscalService::class);
        $n1 = $svc->emitirDoPedido($this->pedido(1), ModeloDocumento::NFE);
        $n2 = $svc->emitirDoPedido($this->pedido(1), ModeloDocumento::NFE);

        $this->assertEquals(1, $n1->numero);
        $this->assertEquals(2, $n2->numero);
        $this->assertNotEquals($n1->chave, $n2->chave);
    }

    public function test_cancelar_nota_autorizada(): void
    {
        $nota = app(FiscalService::class)->emitirDoPedido($this->pedido(5), ModeloDocumento::NFE);

        $cancelada = app(FiscalService::class)->cancelar($nota, 'Cancelamento por erro de digitacao no pedido');
        $this->assertEquals(SituacaoNota::CANCELADA, $cancelada->situacao);
    }

    public function test_emite_via_api(): void
    {
        $user = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $pedido = $this->pedido(3);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/notas/emitir', [
            'pedido_id' => $pedido->id, 'modelo' => '55',
        ])->assertCreated()->assertJsonPath('data.situacao', 'AUTORIZADA');

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/notas')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $user = User::factory()->semPapel()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/notas')->assertStatus(403);
    }
}
