<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Permission;
use App\Models\Produto\Produto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustoEstoqueAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<string> $permissoes */
    private function cenario(array $permissoes): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Estoque seguro']);
        $ids = collect($permissoes)->map(
            fn (string $chave) => Permission::firstOrCreate(['chave' => $chave])->id,
        );
        $role->permissions()->sync($ids);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        $origem = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $destino = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        return [$user, $empresa, $origem, $destino, $produto];
    }

    public function test_historico_transferencia_e_models_ocultam_custo_sem_view(): void
    {
        [$user, $empresa, $origem, $destino, $produto] = $this->cenario(['estoque.view', 'estoque.edit']);
        app(EstoqueService::class)->entrada($origem->id, $produto->id, 10, 9876.5432, empresaEsperada: $empresa->id);

        $transferencia = $this->actingAs($user, 'sanctum')->postJson('/api/admin/estoque/transferencias', [
            'setor_origem_id' => $origem->id,
            'setor_destino_id' => $destino->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
        ])->assertCreated();

        $historico = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/estoque/historico')->assertOk();
        $transferencias = $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/estoque/transferencias')->assertOk();

        foreach ([$transferencia, $historico, $transferencias] as $resposta) {
            $json = json_encode($resposta->json(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('custo_unitario', $json);
            $this->assertStringNotContainsString('9876.5432', $json);
        }

        $this->assertArrayNotHasKey('custo_unitario', EstoqueHistorico::withoutTenant()->firstOrFail()->toArray());
        $this->assertArrayNotHasKey('custo_medio', EstoqueSaldo::withoutTenant()->firstOrFail()->toArray());
    }

    public function test_entrada_com_custo_sem_edit_e_negada_sem_efeito(): void
    {
        [$user, $empresa, $setor, , $produto] = $this->cenario(['estoque.edit']);
        $antes = EstoqueHistorico::withoutTenant()->count();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/estoque/entrada', [
            'setor_id' => $setor->id,
            'produto_id' => $produto->id,
            'quantidade' => 10,
            'custo_unitario' => 9876.5432,
        ])->assertForbidden();

        $this->assertSame($antes, EstoqueHistorico::withoutTenant()->count());
        $this->assertDatabaseMissing('estoquesaldos', [
            'empresa_id' => $empresa->id,
            'setor_id' => $setor->id,
            'produto_id' => $produto->id,
        ]);
    }

    public function test_edit_sem_view_grava_custo_mas_redige_a_resposta(): void
    {
        [$user, $empresa, $setor, , $produto] = $this->cenario([
            'estoque.edit', 'produto.campo.custo.edit',
        ]);

        $resposta = $this->actingAs($user, 'sanctum')->postJson('/api/admin/estoque/entrada', [
            'setor_id' => $setor->id,
            'produto_id' => $produto->id,
            'quantidade' => 4,
            'custo_unitario' => 25.4321,
        ])->assertCreated();

        $resposta->assertJsonMissingPath('data.custo_unitario');
        $this->assertSame(
            25.4321,
            (float) EstoqueSaldo::withoutTenant()
                ->where('empresa_id', $empresa->id)
                ->where('setor_id', $setor->id)
                ->where('produto_id', $produto->id)
                ->value('custo_medio'),
        );
    }

    public function test_view_autorizada_recebe_custo_do_movimento(): void
    {
        [$user, $empresa, $setor, , $produto] = $this->cenario([
            'estoque.view', 'produto.campo.custo.view',
        ]);
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 3, 44.321, empresaEsperada: $empresa->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/estoque/historico')
            ->assertOk()
            ->assertJsonPath('data.0.custo_unitario', 44.321);
    }
}
