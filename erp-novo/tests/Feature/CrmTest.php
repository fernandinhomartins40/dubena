<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE C10 — CRM/satélites: pós-venda, promoção, sorteio, metas, checklist.
 */
class CrmTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'support' => true,
        ]);
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
    }

    public function test_posvenda_crud(): void
    {
        $r = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/pos-vendas', ['nota' => 9, 'canal' => 'whatsapp', 'situacao' => 'realizado'])
            ->assertStatus(201)->assertJsonPath('data.nota', 9);
        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/pos-vendas')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->user, 'sanctum')->deleteJson('/api/admin/pos-vendas/'.$r->json('data.id'))->assertOk();
    }

    public function test_promocao_crud(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/promocoes', ['descricao' => 'Black Friday', 'inicio' => '2026-11-01', 'fim' => '2026-11-30', 'desconto_percentual' => 15])
            ->assertStatus(201)->assertJsonPath('data.descricao', 'Black Friday');
    }

    public function test_sorteio_com_numeros_e_sortear(): void
    {
        $s = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/sorteios', ['descricao' => 'Natal'])->assertStatus(201);
        $id = $s->json('data.id');
        $this->actingAs($this->user, 'sanctum')->postJson("/api/admin/sorteios/{$id}/numeros", ['numero' => '0001'])->assertStatus(201);
        $this->actingAs($this->user, 'sanctum')->postJson("/api/admin/sorteios/{$id}/numeros", ['numero' => '0002'])->assertStatus(201);

        $this->actingAs($this->user, 'sanctum')->postJson("/api/admin/sorteios/{$id}/sortear")
            ->assertOk()->assertJsonStructure(['data' => ['numero']]);
    }

    public function test_meta_crud(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/metas', ['competencia' => '2026-06', 'meta_valor' => 50000])
            ->assertStatus(201)->assertJsonPath('data.competencia', '2026-06');
    }

    public function test_checklist_com_perguntas_e_execucao(): void
    {
        $c = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/checklists', ['descricao' => 'Abertura de loja', 'perguntas' => ['Caixa conferido?', 'Estoque ok?']])
            ->assertStatus(201)->assertJsonCount(2, 'data.perguntas');
        $id = $c->json('data.id');
        $perg = $c->json('data.perguntas.0.id');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/checklists/{$id}/executar", [
                'respostas' => [['pergunta_id' => $perg, 'conforme' => true]],
            ])->assertStatus(201)->assertJsonCount(1, 'data.respostas');
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $u = User::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'support' => false]);
        $this->actingAs($u, 'sanctum')->getJson('/api/admin/promocoes')->assertStatus(403);
    }
}
