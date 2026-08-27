<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE C11 — requisições + inventário/estoque físico. Efetivação passa pelo
 * EstoqueService (acerto/transferência) → saldo auditável.
 */
class EstoqueOperacoesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Produto $produto;

    private Setor $deposito;

    private Setor $loja;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'support' => true,
        ]);
        $this->produto = Produto::create(['grupo_id' => $this->empresa->grupo_id, 'descricao' => 'P13', 'preco_venda' => 110, 'custo_medio' => 90, 'ativo' => true]);
        $this->deposito = Setor::create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Depósito', 'ativo' => true]);
        $this->loja = Setor::create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'descricao' => 'Loja', 'ativo' => true]);
        app(EstoqueService::class)->entrada($this->deposito->id, $this->produto->id, 100, 90.0);
    }

    public function test_requisicao_com_atendimento_transfere_estoque(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/requisicoes', [
                'setor_origem_id' => $this->deposito->id,
                'setor_destino_id' => $this->loja->id,
                'produto_id' => $this->produto->id,
                'quantidade' => 30,
                'atender' => true,
            ])->assertStatus(201)->assertJsonPath('data.situacao', 'atendida');

        $dep = EstoqueSaldo::withoutGlobalScopes()->where('setor_id', $this->deposito->id)->where('produto_id', $this->produto->id)->value('quantidade');
        $loja = EstoqueSaldo::withoutGlobalScopes()->where('setor_id', $this->loja->id)->where('produto_id', $this->produto->id)->value('quantidade');
        $this->assertEqualsWithDelta(70.0, (float) $dep, 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $loja, 0.001);
    }

    public function test_inventario_efetivar_ajusta_saldo_para_o_contado(): void
    {
        // Conta 80 (sistema tem 100) → após efetivar, saldo = 80.
        $resp = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/inventarios', [
                'setor_id' => $this->deposito->id,
                'itens' => [['produto_id' => $this->produto->id, 'quantidade_contada' => 80]],
            ])->assertStatus(201);

        $invId = $resp->json('data.id');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/estoque/fisico/{$invId}/efetivar")
            ->assertOk()->assertJsonPath('data.situacao', 'efetivado');

        $saldo = EstoqueSaldo::withoutGlobalScopes()->where('setor_id', $this->deposito->id)->where('produto_id', $this->produto->id)->value('quantidade');
        $this->assertEqualsWithDelta(80.0, (float) $saldo, 0.001);
        // Invariante preservada: Σ histórico = saldo.
        $this->assertEqualsWithDelta(80.0, app(EstoqueService::class)->saldoDerivado($this->deposito->id, $this->produto->id), 0.001);
    }

    public function test_abrir_fechamento_registra(): void
    {
        $resp = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/fechamentos/abrir', [
                'setor_id' => $this->deposito->id,
                'produto_id' => $this->produto->id,
            ])->assertStatus(201);

        $this->assertEqualsWithDelta(100.0, (float) $resp->json('data.saldo_final'), 0.001);
    }

    public function test_entrada_recusa_setor_e_produto_de_outra_empresa(): void
    {
        $outra = Empresa::factory()->create();
        $setorAlheio = Setor::factory()->create(['empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id]);
        $produtoAlheio = Produto::factory()->create(['empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/estoque/entrada', [
                'setor_id' => $setorAlheio->id,
                'produto_id' => $produtoAlheio->id,
                'quantidade' => 10,
            ])->assertUnprocessable();

        $this->assertSame(0, EstoqueSaldo::withoutGlobalScopes()->where('empresa_id', $outra->id)->count());
    }
}
