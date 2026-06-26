<?php

namespace Tests\Feature;

use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Tenant\TenantContext;
use App\Models\Caixa\ContaMovimento;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Mobile\AppDevice;
use App\Models\Pedido\PedidoItem;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F02 — Isolamento multi-tenant (defense-in-depth na aplicação).
 *
 * Garante que um usuário da empresa B NÃO enxerga/altera dados da empresa A —
 * inclusive em TABELAS-FILHAS (parcelas, itens, movimentos, telefones), que antes
 * herdavam só o escopo do pai (origem do IDOR da auditoria §5/§8).
 *
 * O RLS do Postgres é a 2ª barreira (testado em pgsql; em sqlite o global scope
 * é a barreira exercida aqui).
 */
class FaseF02CrossTenantTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function tenant(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        return [$user, $empresa];
    }

    public function test_listagem_de_clientes_nao_vaza_entre_empresas(): void
    {
        [$userA, $empresaA] = $this->tenant();
        [$userB, $empresaB] = $this->tenant();

        Cliente::factory()->create(['empresa_id' => $empresaA->id, 'grupo_id' => $empresaA->grupo_id, 'nome' => 'Cliente A']);
        Cliente::factory()->create(['empresa_id' => $empresaB->id, 'grupo_id' => $empresaB->grupo_id, 'nome' => 'Cliente B']);

        $dataA = $this->actingAs($userA, 'sanctum')->getJson('/api/admin/clientes')->assertOk()->json('data');
        $dataB = $this->actingAs($userB, 'sanctum')->getJson('/api/admin/clientes')->assertOk()->json('data');

        $this->assertCount(1, $dataA);
        $this->assertCount(1, $dataB);
        $this->assertSame('Cliente A', $dataA[0]['nome']);
        $this->assertSame('Cliente B', $dataB[0]['nome']);
    }

    public function test_show_de_cliente_de_outra_empresa_da_404(): void
    {
        [$userA, $empresaA] = $this->tenant();
        [$userB] = $this->tenant();

        $clienteA = Cliente::factory()->create(['empresa_id' => $empresaA->id, 'grupo_id' => $empresaA->grupo_id]);

        $this->actingAs($userB, 'sanctum')
            ->getJson("/api/admin/clientes/{$clienteA->id}")
            ->assertNotFound();
    }

    public function test_tabela_filha_parcela_e_invisivel_para_outra_empresa(): void
    {
        [, $empresaA] = $this->tenant();
        [$userB, $empresaB] = $this->tenant();

        // Cria título+parcela na empresa A (sem tenant ativo: empresa_id explícito
        // no pai, e a filha HERDA via tenantParent).
        $fA = app(FinanceiroService::class)->criar([
            'empresa_id' => $empresaA->id, 'grupo_id' => $empresaA->grupo_id, 'pagarreceber' => 'R', 'valor' => 100,
        ]);
        $parcelaA = $fA->parcelas->first();
        $this->assertSame($empresaA->id, (int) $parcelaA->empresa_id, 'Parcela deve herdar empresa_id do pai.');

        // Com o tenant B ativo, a parcela de A não existe no escopo.
        $this->actingAs($userB, 'sanctum')->withHeader('X-Empresa-Id', (string) $empresaB->id);
        app(TenantContext::class)->set($empresaB->id, $empresaB->grupo_id);

        $this->assertNull(FinanceiroParcela::query()->find($parcelaA->id));
        $this->assertNotNull(FinanceiroParcela::withoutTenant()->find($parcelaA->id));
    }

    public function test_movimento_de_caixa_nao_vaza(): void
    {
        [, $empresaA] = $this->tenant();
        [, $empresaB] = $this->tenant();

        $contaA = app(CaixaService::class)->criarConta([
            'empresa_id' => $empresaA->id, 'grupo_id' => $empresaA->grupo_id, 'descricao' => 'Caixa A', 'saldo_inicial' => 80,
        ]);
        $movA = ContaMovimento::withoutTenant()->where('conta_id', $contaA->id)->firstOrFail();
        $this->assertSame($empresaA->id, (int) $movA->empresa_id);

        // Tenant B ativo não enxerga o movimento de A.
        app(TenantContext::class)->set($empresaB->id, $empresaB->grupo_id);
        $this->assertNull(ContaMovimento::query()->find($movA->id));
    }

    public function test_pedido_item_herda_empresa_do_pedido(): void
    {
        [, $empresa] = $this->tenant();
        $situacao = PedidoSituacao::factory()->create([
            'grupo_id' => $empresa->grupo_id, 'efeito' => EfeitoPedido::PENDENTE,
        ]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 50]);

        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id])->id,
            'pedidosituacao_id' => $situacao->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 2]]);

        $item = PedidoItem::withoutTenant()->where('pedido_id', $pedido->id)->first();
        $this->assertNotNull($item);
        $this->assertSame($empresa->id, (int) $item->empresa_id, 'PedidoItem deve herdar empresa_id do pedido.');
    }

    public function test_config_fiscal_csc_nao_vaza_entre_empresas(): void
    {
        [, $empresaA] = $this->tenant();
        [$userB, $empresaB] = $this->tenant();

        // Config fiscal (com CSC, segredo NFC-e) da empresa A.
        ConfigFiscal::withoutTenant()->create([
            'empresa_id' => $empresaA->id, 'ambiente' => 2, 'csc_id' => '001', 'csc_token' => 'SEGREDO-A',
        ]);

        // Tenant B ativo: a config de A é invisível; firstOrCreate cria a DELE, não a de A.
        app(TenantContext::class)->set($empresaB->id, $empresaB->grupo_id);
        $this->actingAs($userB, 'sanctum')->withHeader('X-Empresa-Id', (string) $empresaB->id);

        $this->assertNull(
            ConfigFiscal::query()->where('csc_token', 'SEGREDO-A')->first(),
            'CSC fiscal da empresa A vazou para o tenant B.'
        );

        $config = ConfigFiscal::firstOrCreate(['empresa_id' => $empresaB->id]);
        $this->assertSame($empresaB->id, (int) $config->empresa_id);
        $this->assertNotSame('SEGREDO-A', $config->csc_token);
    }

    public function test_app_device_nao_vaza_entre_empresas(): void
    {
        [, $empresaA] = $this->tenant();
        [, $empresaB] = $this->tenant();

        $userMobile = User::factory()->create(['empresa_id' => $empresaA->id, 'grupo_id' => $empresaA->grupo_id]);
        AppDevice::withoutTenant()->create([
            'user_id' => $userMobile->id, 'empresa_id' => $empresaA->id, 'device_id' => 'dev-A', 'ativo' => true,
        ]);

        // Tenant B ativo não enxerga o device registrado sob a empresa A.
        app(TenantContext::class)->set($empresaB->id, $empresaB->grupo_id);
        $this->assertNull(AppDevice::query()->where('device_id', 'dev-A')->first());
        $this->assertNotNull(AppDevice::withoutTenant()->where('device_id', 'dev-A')->first());
    }

    public function test_cadastros_de_apoio_isolam_por_grupo(): void
    {
        [$userA, $empresaA] = $this->tenant();
        [$userB] = $this->tenant();

        // Cria um segmento no grupo de A.
        $this->actingAs($userA, 'sanctum')
            ->postJson('/api/admin/cadastros/segmentos', ['descricao' => 'Residencial A'])
            ->assertCreated();

        // B (outro grupo) não enxerga o segmento de A.
        $dataB = $this->actingAs($userB, 'sanctum')->getJson('/api/admin/cadastros/segmentos')->assertOk()->json('data');
        $nomes = collect($dataB)->pluck('descricao');
        $this->assertFalse($nomes->contains('Residencial A'), 'Cadastro de apoio vazou entre grupos.');
    }
}
