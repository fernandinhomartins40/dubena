<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gás do Povo — o programa como o legado opera.
 *
 * A auditoria do `ctrl-web` mostrou que o programa não é um módulo com saldo e
 * saque: é um MODO DE VENDA definido por parâmetros na config da empresa, um
 * checkbox no cliente e um preço próprio no produto. A venda entra no programa
 * quando cliente beneficiário E condição de pagamento do programa coincidem.
 *
 * Ver `docs/02-auditoria-legado/GAS_DO_POVO_NO_LEGADO.md`.
 */
class GasDoPovoProgramaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa, Produto, CondicaoPagamento} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Botijão P13 — Gás do Povo',
            'preco_venda' => 120.00,
            'preco_gasdopovo' => 45.00,
        ]);

        $condicao = CondicaoPagamento::create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Cartão Gás do Povo',
            'num_parcelas' => 1, 'intervalo_dias' => 0, 'dias_primeira' => 0,
            'a_vista' => true, 'ativo' => true,
        ]);

        EmpresaConfig::create([
            'empresa_id' => $empresa->id,
            'dados' => [
                'gp_produto_id' => $produto->id,
                'gp_condicaopagamento_id' => $condicao->id,
                'valorfretegp' => 15.0,
            ],
        ]);

        return [$user, $empresa, $produto, $condicao];
    }

    private function venda(Empresa $empresa, Produto $produto, float $valor, bool $doPrograma): Pedido
    {
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'gasdopovo' => $doPrograma,
        ]);
        $situacao = PedidoSituacao::factory()->create(['grupo_id' => $empresa->grupo_id]);

        $pedido = Pedido::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'datahora' => now(), 'valor_venda' => $valor, 'valor_desconto' => 0,
            'gasdopovo' => $doPrograma,
        ]);

        $pedido->itens()->create([
            'empresa_id' => $empresa->id,
            'produto_id' => $produto->id,
            'quantidade' => 1, 'preco_unitario' => $valor,
            'desconto' => 0, 'valor_total' => $valor,
        ]);

        return $pedido;
    }

    public function test_parametros_vem_da_config_da_empresa(): void
    {
        [$user, , $produto, $condicao] = $this->cenario();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/gasdopovo/programa')
            ->assertOk()
            ->assertJsonPath('data.parametros.configurado', true)
            ->assertJsonPath('data.parametros.produto_id', $produto->id)
            ->assertJsonPath('data.parametros.produto', 'Botijão P13 — Gás do Povo')
            ->assertJsonPath('data.parametros.preco', fn ($v) => (float) $v === 45.0)
            ->assertJsonPath('data.parametros.preco_venda', fn ($v) => (float) $v === 120.0)
            ->assertJsonPath('data.parametros.condicaopagamento_id', $condicao->id)
            ->assertJsonPath('data.parametros.condicaopagamento', 'Cartão Gás do Povo')
            ->assertJsonPath('data.parametros.valor_frete', fn ($v) => (float) $v === 15.0);
    }

    /** Sem produto e condição definidos, a tela precisa AVISAR — não fingir que está pronto. */
    public function test_programa_sem_parametros_e_declarado_nao_configurado(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/gasdopovo/programa')
            ->assertOk()
            ->assertJsonPath('data.parametros.configurado', false);
    }

    /**
     * Volume, faturamento e preço MÉDIO PRATICADO.
     *
     * Não há cálculo de subsídio de propósito: a conferência com o dump mostrou
     * que `precogasdopovo` é igual ao preço normal (120,00 nos dois) e que as
     * vendas variam de R$ 96 a R$ 127. O programa é o canal de pagamento — o
     * cartão do benefício —, não um desconto de tabela. Um card de "subsídio"
     * pela diferença de cadastro mostraria R$ 0,00 e induziria a erro.
     */
    public function test_resumo_apura_volume_e_preco_medio(): void
    {
        [$user, $empresa, $produto] = $this->cenario();

        $this->venda($empresa, $produto, 45.00, doPrograma: true);
        $this->venda($empresa, $produto, 45.00, doPrograma: true);
        // Venda normal do mesmo produto: NÃO pode entrar na conta do programa.
        $this->venda($empresa, $produto, 120.00, doPrograma: false);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/gasdopovo/programa')
            ->assertOk()
            ->assertJsonPath('data.resumo.pedidos', 2)
            ->assertJsonPath('data.resumo.valor', fn ($v) => (float) $v === 90.0)
            ->assertJsonPath('data.resumo.botijoes', fn ($v) => (float) $v === 2.0)
            // 90,00 faturados / 2 botijões
            ->assertJsonPath('data.resumo.preco_medio', fn ($v) => (float) $v === 45.0)
            ->assertJsonPath('data.resumo.beneficiarios', 2);
    }

    public function test_vendas_lista_apenas_as_do_programa(): void
    {
        [$user, $empresa, $produto] = $this->cenario();

        $doPrograma = $this->venda($empresa, $produto, 45.00, doPrograma: true);
        $this->venda($empresa, $produto, 120.00, doPrograma: false);

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/gasdopovo/vendas')
            ->assertOk();

        $this->assertSame(1, $resp->json('meta.total'), 'a venda normal vazou para o relatório do programa');
        $this->assertSame($doPrograma->id, $resp->json('data.0.id'));
        $this->assertSame(45.0, (float) $resp->json('data.0.valorvenda'));
    }

    public function test_beneficiarios_lista_os_clientes_marcados(): void
    {
        [$user, $empresa] = $this->cenario();

        Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'Maria Beneficiária', 'gasdopovo' => true,
        ]);
        Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'João Comum', 'gasdopovo' => false,
        ]);

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/gasdopovo/beneficiarios')
            ->assertOk();

        $this->assertSame(1, $resp->json('meta.total'));
        $this->assertSame('Maria Beneficiária', $resp->json('data.0.nome'));
    }

    /** O período filtra: prestação de contas é sempre por competência. */
    public function test_periodo_filtra_o_resumo(): void
    {
        [$user, $empresa, $produto] = $this->cenario();

        $antiga = $this->venda($empresa, $produto, 45.00, doPrograma: true);
        $antiga->update(['datahora' => now()->subMonths(6)]);
        $this->venda($empresa, $produto, 45.00, doPrograma: true);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/gasdopovo/programa?de='.now()->startOfMonth()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.resumo.pedidos', 1);
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/gasdopovo/programa')
            ->assertForbidden();
    }
}
