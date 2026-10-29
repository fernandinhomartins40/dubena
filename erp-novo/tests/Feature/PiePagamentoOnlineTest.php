<?php

namespace Tests\Feature;

use App\Domain\Cobranca\PagamentoOnlineDoPedido;
use App\Domain\Cobranca\PixService;
use App\Domain\Estoque\EstoqueService;
use App\Domain\Mobile\PagamentoOnlineService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Alerta;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Financeiro\Financeiro;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pagamento online do app × título do pedido (auditoria PIE, R3-08/R2-02),
 * com as regras definidas pelo dono em 29/10:
 *  - pago online → o título nasce baixado; PIX que chega depois da conclusão
 *    baixa o título existente;
 *  - PIX só para condição PIX;
 *  - sem pagamento duplo nem pagamento de pedido cancelado;
 *  - cancelar pedido pago é aceito e vira alerta de estorno.
 */
class PiePagamentoOnlineTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Setor $setor;

    private Produto $produto;

    private Cliente $cliente;

    private PedidoSituacao $pendente;

    private PedidoSituacao $concluido;

    private PedidoSituacao $cancelado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $g = $this->empresa->grupo_id;
        $this->setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $g]);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $g, 'preco_venda' => 100]);
        $this->cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $g]);
        app(EstoqueService::class)->entrada($this->setor->id, $this->produto->id, 50, 10);
        $this->pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $g]);
        $this->concluido = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $g]);
        $this->cancelado = PedidoSituacao::factory()->efeito(EfeitoPedido::CANCELADO)->create(['grupo_id' => $g]);
    }

    private function condicao(bool $pix): CondicaoPagamento
    {
        return CondicaoPagamento::create([
            'grupo_id' => $this->empresa->grupo_id, 'descricao' => ($pix ? 'Pix ' : 'Dinheiro ').uniqid(),
            'num_parcelas' => 1, 'intervalo_dias' => 30, 'dias_primeira' => 0, 'a_vista' => true,
            'pix_online' => $pix, 'ativo' => true,
        ]);
    }

    private function pedido(bool $pix = true): Pedido
    {
        return app(PedidoService::class)->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'pedidosituacao_id' => $this->pendente->id,
            'setor_id' => $this->setor->id, 'condicaopagamento_id' => $this->condicao($pix)->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 1, 'preco_unitario' => 100]]);
    }

    private function pagarPix(Pedido $pedido): void
    {
        $pix = app(PixService::class);
        $cobranca = $pix->criarCobrancaPedido($pedido);
        $pix->processarWebhook(['txid' => $cobranca->txid, 'valor' => (float) $cobranca->valor]);
    }

    private function titulo(Pedido $pedido): ?Financeiro
    {
        return Financeiro::withoutTenant()->where('origem', 'pedido')->where('origem_id', $pedido->id)
            ->where('cancelado', false)->first();
    }

    /** @param callable(): mixed $acao */
    private function assertRecusa(callable $acao): void
    {
        try {
            $acao();
            $this->fail('Era esperada recusa (ValidationException).');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_pix_pago_antes_da_entrega_faz_o_titulo_nascer_baixado(): void
    {
        $pedido = $this->pedido();
        $this->pagarPix($pedido);

        app(PedidoService::class)->mudarSituacao($pedido, $this->concluido->id);

        $titulo = $this->titulo($pedido);
        $this->assertNotNull($titulo);
        // Antes: título em aberto — cobrado como vencido de quem já pagou.
        $this->assertSame(0, $titulo->parcelas()->where('baixado', false)->count());
    }

    public function test_pix_que_chega_depois_da_entrega_baixa_o_titulo_existente(): void
    {
        $pedido = $this->pedido();
        $pix = app(PixService::class);
        $cobranca = $pix->criarCobrancaPedido($pedido);

        app(PedidoService::class)->mudarSituacao($pedido, $this->concluido->id);
        $this->assertSame(1, $this->titulo($pedido)->parcelas()->where('baixado', false)->count());

        $pix->processarWebhook(['txid' => $cobranca->txid, 'valor' => (float) $cobranca->valor]);

        $this->assertSame(0, $this->titulo($pedido)->parcelas()->where('baixado', false)->count());
    }

    public function test_cartao_aprovado_faz_o_titulo_nascer_baixado(): void
    {
        $pedido = $this->pedido(pix: false);
        app(PagamentoOnlineService::class)->cobrarPedido($pedido, ['token' => 'tok-ok']);

        app(PedidoService::class)->mudarSituacao($pedido, $this->concluido->id);

        $this->assertSame(0, $this->titulo($pedido)->parcelas()->where('baixado', false)->count());
    }

    public function test_pix_so_e_gerado_para_condicao_pix(): void
    {
        $dinheiro = $this->pedido(pix: false);

        // Antes: QR de PIX até para quem escolheu dinheiro.
        $this->assertRecusa(fn () => app(PixService::class)->criarCobrancaPedido($dinheiro));
        $this->assertNotNull(app(PixService::class)->criarCobrancaPedido($this->pedido()));
    }

    public function test_pedido_ja_pago_nao_aceita_novo_pagamento(): void
    {
        $pedido = $this->pedido();
        $this->pagarPix($pedido);

        $this->assertRecusa(fn () => app(PixService::class)->criarCobrancaPedido($pedido));
        $this->assertRecusa(fn () => app(PagamentoOnlineService::class)->cobrarPedido($pedido, ['token' => 'tok-ok']));
    }

    public function test_pedido_cancelado_nao_aceita_pagamento(): void
    {
        $pedido = $this->pedido();
        app(PedidoService::class)->mudarSituacao($pedido, $this->cancelado->id);

        $this->assertRecusa(fn () => app(PixService::class)->criarCobrancaPedido($pedido));
        $this->assertRecusa(fn () => app(PagamentoOnlineService::class)->cobrarPedido($pedido, ['token' => 'tok-ok']));
    }

    public function test_cancelar_pedido_pago_e_aceito_e_vira_alerta_de_estorno(): void
    {
        $pedido = $this->pedido();
        $this->pagarPix($pedido);

        app(PedidoService::class)->mudarSituacao($pedido, $this->cancelado->id);

        $this->assertSame($this->cancelado->id, (int) $pedido->refresh()->pedidosituacao_id);
        $alerta = Alerta::query()->withoutGlobalScopes()->where('origem', PagamentoOnlineDoPedido::ORIGEM_ALERTA)->first();
        $this->assertNotNull($alerta);
        $this->assertSame($pedido->id, $alerta->dados['pedido_id']);
        $this->assertEqualsWithDelta(100.0, $alerta->dados['valor'], 0.01);
    }

    public function test_cancelar_pedido_entregue_e_pago_reabre_a_baixa_cancela_o_titulo_e_alerta(): void
    {
        $pedido = $this->pedido();
        $this->pagarPix($pedido);
        app(PedidoService::class)->mudarSituacao($pedido, $this->concluido->id);
        $tituloId = $this->titulo($pedido)->id;

        // Sem a reabertura, o estorno do título recusaria ("parcela baixada").
        app(PedidoService::class)->mudarSituacao($pedido, $this->cancelado->id);

        $this->assertTrue((bool) Financeiro::withoutTenant()->find($tituloId)->cancelado);
        $this->assertSame(1, Alerta::query()->withoutGlobalScopes()->where('origem', PagamentoOnlineDoPedido::ORIGEM_ALERTA)->count());
    }

    public function test_condicao_de_pagamento_e_configuravel_e_nao_se_exclui(): void
    {
        $user = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $c = $this->condicao(false);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/cadastros/condicoes-pagamento/{$c->id}", ['descricao' => $c->descricao, 'pix_online' => true])
            ->assertOk();
        $this->assertTrue((bool) $c->refresh()->pix_online);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/cadastros/condicoes-pagamento/{$c->id}")
            ->assertStatus(422);
        $this->assertNotNull(CondicaoPagamento::query()->withoutGlobalScopes()->find($c->id));
    }
}
