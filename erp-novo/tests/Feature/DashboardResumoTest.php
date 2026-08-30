<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard (home da SPA) — GET /dashboard/resumo. Corrige o 404 que quebrava a
 * home (a SPA chamava o endpoint inexistente e o front estourava no .map).
 * A resposta é um objeto PLANO {clientes,produtos,pedidos,financeiro} (a SPA lê
 * data.clientes direto, sem envelope `data`).
 */
class DashboardResumoTest extends TestCase
{
    use RefreshDatabase;

    public function test_resumo_retorna_contadores_planos(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        Cliente::factory()->count(3)->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        Produto::create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'P13', 'preco_venda' => 100, 'custo_medio' => 80, 'ativo' => true]);

        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/admin/dashboard/resumo')->assertOk();

        // Objeto plano (sem envelope) — o que o DashboardPage espera.
        $resp->assertJsonStructure(['clientes', 'produtos', 'pedidos', 'financeiro']);
        $this->assertSame(3, $resp->json('clientes'));
        $this->assertSame(1, $resp->json('produtos'));
        $this->assertIsInt($resp->json('pedidos'));
        $this->assertIsInt($resp->json('financeiro'));
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/admin/dashboard/resumo')->assertStatus(401);
    }
}
