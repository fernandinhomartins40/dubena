<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N8 — API dos satélites (convênio, vale-gás, comodato) + RBAC.
 */
class SateliteTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        return [$user, $empresa];
    }

    public function test_cria_convenio_e_vale_gas(): void
    {
        [$user, $empresa] = $this->suporte();
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/convenios', [
            'cliente_id' => $cliente->id, 'descricao' => 'Convênio X',
        ])->assertCreated();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/vale-gas', [
            'cliente_id' => $cliente->id, 'valor' => 90,
        ])->assertCreated()->assertJsonPath('data.situacao', 'EMITIDO');
    }

    public function test_comodato_emprestimo_baixa_estoque_via_api(): void
    {
        [$user, $empresa] = $this->suporte();
        $setor = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 20, 10);

        $id = $this->actingAs($user, 'sanctum')->postJson('/api/admin/comodatos', [
            'cliente_id' => $cliente->id, 'produto_id' => $produto->id, 'setor_id' => $setor->id, 'quantidade' => 8,
        ])->assertCreated()->json('data.id');

        $resp = $this->actingAs($user, 'sanctum')->getJson("/api/admin/estoque/saldos?setor_id={$setor->id}")->json('data');
        $this->assertEqualsWithDelta(12, collect($resp)->firstWhere('produto_id', $produto->id)['quantidade'], 0.001);

        // devolve tudo
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/comodatos/{$id}/devolver", ['quantidade' => 8])
            ->assertOk()->assertJsonPath('data.situacao', 'DEVOLVIDO');
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/convenios')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/vale-gas')->assertStatus(403);
    }
}
