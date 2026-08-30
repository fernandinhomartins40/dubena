<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N3 — API de produtos e estoque (CRUD, origens aninhadas, movimentação, escopo, RBAC).
 */
class ProdutoEstoqueTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        return [$user, $empresa];
    }

    public function test_cria_produto_com_origens_aninhadas(): void
    {
        [$user] = $this->suporte();

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/produtos', [
            'descricao' => 'Botijão 13kg',
            'preco_venda' => 110.50,
            'nfe_permite' => true,
            'origens' => [
                ['uf' => 'SP', 'ind_import' => 0, 'p_orig' => 100],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.descricao', 'Botijão 13kg')
            ->assertJsonPath('data.preco_venda', 110.5);

        $this->assertDatabaseCount('produtoorigens', 1);
        $this->assertDatabaseHas('produtoorigens', ['produto_id' => $resp->json('data.id'), 'uf' => 'SP']);
    }

    public function test_lista_produtos_escopados_por_empresa(): void
    {
        [$user, $empresa] = $this->suporte();
        Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'P5kg']);
        Produto::factory()->create(['descricao' => 'De outra empresa']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/produtos')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_movimentacao_entrada_e_saida_via_api(): void
    {
        [$user, $empresa] = $this->suporte();
        $setor = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/estoque/entrada', [
            'setor_id' => $setor->id, 'produto_id' => $produto->id, 'quantidade' => 50, 'custo_unitario' => 10,
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/estoque/saida', [
            'setor_id' => $setor->id, 'produto_id' => $produto->id, 'quantidade' => 20,
        ])->assertCreated();

        $resp = $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/estoque/saldos?setor_id={$setor->id}")
            ->assertOk();
        $saldo = collect($resp->json('data'))->firstWhere('produto_id', $produto->id);
        $this->assertEqualsWithDelta(30, $saldo['quantidade'], 0.001);
    }

    public function test_saida_sem_saldo_retorna_422(): void
    {
        [$user, $empresa] = $this->suporte();
        $setor = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/estoque/saida', [
            'setor_id' => $setor->id, 'produto_id' => $produto->id, 'quantidade' => 5,
        ])->assertStatus(422);
    }

    public function test_transferencia_via_api(): void
    {
        [$user, $empresa] = $this->suporte();
        $origem = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $destino = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/estoque/entrada', [
            'setor_id' => $origem->id, 'produto_id' => $produto->id, 'quantidade' => 100, 'custo_unitario' => 9,
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/estoque/transferencias', [
            'setor_origem_id' => $origem->id, 'setor_destino_id' => $destino->id, 'produto_id' => $produto->id, 'quantidade' => 40,
        ])->assertCreated();

        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/admin/estoque/saldos')->assertOk();
        $dados = collect($resp->json('data'));
        $this->assertEqualsWithDelta(60, $dados->firstWhere('setor_id', $origem->id)['quantidade'], 0.001);
        $this->assertEqualsWithDelta(40, $dados->firstWhere('setor_id', $destino->id)['quantidade'], 0.001);
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/produtos')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/estoque/saldos')->assertStatus(403);
    }
}
