<?php

namespace Tests\Feature;

use App\Domain\Gestao\BemService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Gestao\EmpresaBem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FASE C11 — cupom fiscal, MCMM, documentos, bens (depreciação). */
class GestaoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
        ]);
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
    }

    public function test_cupom_criar_e_emitir(): void
    {
        $c = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/cupons-fiscais', ['itens' => [['quantidade' => 2, 'valor_unitario' => 50]]])
            ->assertStatus(201)->assertJsonPath('data.valor_total', '100.00');
        $id = $c->json('data.id');

        $this->actingAs($this->user, 'sanctum')->postJson("/api/admin/cupons-fiscais/{$id}/emitir")
            ->assertOk()->assertJsonPath('data.situacao', 'emitido');
    }

    public function test_mcmm_crud(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/mcmm', ['descricao' => 'Transferência', 'tipo' => 'saida', 'quantidade' => 5])
            ->assertStatus(201)->assertJsonPath('data.tipo', 'saida');
    }

    public function test_documento_crud(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/documentos', ['descricao' => 'Alvará', 'validade' => '2027-01-01'])
            ->assertStatus(201)->assertJsonPath('data.descricao', 'Alvará');
    }

    public function test_bem_com_depreciacao(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/bens', [
                'descricao' => 'Caminhão', 'data_aquisicao' => '2024-06-23',
                'valor_aquisicao' => 100000, 'taxa_depreciacao_anual' => 10, 'valor_residual' => 0,
            ])->assertStatus(201);

        $resp = $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/bens')->assertOk();
        $this->assertArrayHasKey('depreciacao', $resp->json('data.0'));
    }

    public function test_depreciacao_calculo(): void
    {
        // 100k, 10% a.a., residual 0, 2 anos → acumulada 20k, contábil 80k.
        $bem = new EmpresaBem([
            'valor_aquisicao' => 100000, 'taxa_depreciacao_anual' => 10, 'valor_residual' => 0,
            'data_aquisicao' => now()->subYears(2)->toDateString(),
        ]);
        $d = app(BemService::class)->depreciacao($bem, now()->toDateString());

        $this->assertEqualsWithDelta(10000, $d['depreciacao_anual'], 0.01);
        $this->assertEqualsWithDelta(20000, $d['depreciacao_acumulada'], 50);
        $this->assertEqualsWithDelta(80000, $d['valor_contabil'], 50);
    }

    public function test_sem_permissao_403(): void
    {
        $u = User::factory()->semPapel()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->actingAs($u, 'sanctum')->getJson('/api/admin/bens')->assertStatus(403);
    }
}
