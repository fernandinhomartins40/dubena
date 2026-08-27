<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Fiscal\NfImposto;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Fiscal\OperacaoFiscal;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F03 — emissão fiscal a partir do pedido (venda → conclusão → NFC-e). Driver SEFAZ
 * Fake (CI). Cobre o gatilho, a idempotência e o bloqueio de pedido não concluído.
 */
class PedidoNfceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    private function pedido(Empresa $empresa, EfeitoPedido $efeito): Pedido
    {
        $situacao = PedidoSituacao::factory()->create(['grupo_id' => $empresa->grupo_id, 'efeito' => $efeito]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 80, 'ncm' => '27111910']);
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        ConfigFiscal::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'ambiente' => 2,
            'serie_nfe' => 1,
            'serie_nfce' => 1,
            'regime_tributario' => 1,
        ]);
        $operacao = OperacaoFiscal::withoutGrupo()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Venda NFC-e',
            'cfop' => '5102',
            'ativo' => true,
        ]);
        DB::table('produto_operacao_fiscal')->insert([
            'operacao_fiscal_id' => $operacao->id,
            'produto_id' => $produto->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        NfImposto::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'operacao_fiscal_id' => $operacao->id,
            'cst_icms' => '00', 'aliq_icms' => 18, 'perc_bc_icms' => 100,
            'cst_pis' => '01', 'aliq_pis' => 1.65, 'perc_bc_pis' => 100,
            'cst_cofins' => '01', 'aliq_cofins' => 7.6, 'perc_bc_cofins' => 100,
            'pf_cst_icms' => '00', 'pf_aliq_icms' => 18, 'pf_perc_bc_icms' => 100,
            'pf_cst_pis' => '01', 'pf_aliq_pis' => 1.65, 'pf_perc_bc_pis' => 100,
            'pf_cst_cofins' => '01', 'pf_aliq_cofins' => 7.6, 'pf_perc_bc_cofins' => 100,
        ]);

        return app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'setor_id' => Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id])->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 2]]);
    }

    public function test_emite_nfce_de_pedido_concluido(): void
    {
        [$user, $empresa] = $this->suporte();
        // Estoque para o pedido concluído poder baixar.
        $pedido = $this->pedido($empresa, EfeitoPedido::PENDENTE);

        // Conclui o pedido (efeito CONCLUIDO) — precisa de saldo de estoque.
        $situacaoConcl = PedidoSituacao::factory()->create(['grupo_id' => $empresa->grupo_id, 'efeito' => EfeitoPedido::CONCLUIDO]);
        app(EstoqueService::class)->entrada($pedido->setor_id, $pedido->itens->first()->produto_id, 10, 50, 'ajuste-teste', null);
        app(PedidoService::class)->mudarSituacao($pedido, $situacaoConcl->id, $user->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/pedidos/{$pedido->id}/emitir-nfce", ['modelo' => '65'])
            ->assertCreated()
            ->assertJsonPath('data.situacao', 'AUTORIZADA');

        $this->assertSame(1, NotaFiscal::query()->where('pedido_id', $pedido->id)->count());
    }

    public function test_emissao_e_idempotente(): void
    {
        [$user, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa, EfeitoPedido::PENDENTE);
        $situacaoConcl = PedidoSituacao::factory()->create(['grupo_id' => $empresa->grupo_id, 'efeito' => EfeitoPedido::CONCLUIDO]);
        app(EstoqueService::class)->entrada($pedido->setor_id, $pedido->itens->first()->produto_id, 10, 50, 'ajuste-teste', null);
        app(PedidoService::class)->mudarSituacao($pedido, $situacaoConcl->id, $user->id);

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/pedidos/{$pedido->id}/emitir-nfce")->assertCreated();
        // Segunda chamada não cria outra nota (devolve a existente, 200).
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/pedidos/{$pedido->id}/emitir-nfce")
            ->assertOk()->assertJsonPath('message', 'Documento já emitido para este pedido.');

        $this->assertSame(1, NotaFiscal::query()->where('pedido_id', $pedido->id)->count());
    }

    public function test_nao_emite_de_pedido_pendente(): void
    {
        [$user, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa, EfeitoPedido::PENDENTE);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/pedidos/{$pedido->id}/emitir-nfce")
            ->assertStatus(422);

        $this->assertSame(0, NotaFiscal::query()->where('pedido_id', $pedido->id)->count());
    }

    public function test_resource_expoe_fechadoconcluido_e_tem_nf(): void
    {
        [$user, $empresa] = $this->suporte();
        $pedido = $this->pedido($empresa, EfeitoPedido::PENDENTE);

        $this->actingAs($user, 'sanctum')->getJson("/api/admin/pedidos/{$pedido->id}")
            ->assertOk()
            ->assertJsonPath('data.fechadoconcluido', 0)
            ->assertJsonPath('data.tem_nf', false);
    }
}
