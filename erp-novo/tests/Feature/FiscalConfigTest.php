<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** FASE C12 — operações fiscais e malha (config). Últimos endpoints da SPA. */
class FiscalConfigTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true,
        ]);

        return [$user, $empresa];
    }

    public function test_crud_operacao_fiscal(): void
    {
        [$user] = $this->suporte();

        $resp = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/fiscal/operacoes', [
                'descricao' => 'Venda de mercadoria', 'cfop' => '5102',
                'movimenta_estoque' => true, 'movimenta_financeiro' => true,
            ])->assertStatus(201)->assertJsonPath('data.cfop', '5102');

        $id = $resp->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/fiscal/operacoes/{$id}", ['descricao' => 'Venda ajustada', 'cfop' => '5102'])
            ->assertOk()->assertJsonPath('data.descricao', 'Venda ajustada');

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/fiscal/operacoes')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->deleteJson("/api/admin/fiscal/operacoes/{$id}")->assertOk();
    }

    public function test_malha_por_tipo(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/fiscal/malha/cfop', ['codigo' => '5102', 'descricao' => 'Venda dentro do estado'])
            ->assertStatus(201);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/fiscal/malha/cst', ['codigo' => '00', 'descricao' => 'Tributada integralmente'])
            ->assertStatus(201);

        // O índice por tipo só traz os do tipo pedido.
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/fiscal/malha/cfop')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.codigo', '5102');
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/fiscal/operacoes')->assertStatus(403);
    }
}
