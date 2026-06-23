<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Rh\Colaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE C5 — endpoints de colaborador (RH). CRUD + sub-recursos escopados por
 * empresa, com RBAC (colaborador.*).
 */
class ColaboradorTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        return [$user, $empresa];
    }

    public function test_cria_e_lista_colaborador(): void
    {
        [$user, $empresa] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/colaboradores', ['nome' => 'João Entregador', 'cpf' => '12345678900'])
            ->assertStatus(201)
            ->assertJsonPath('data.nome', 'João Entregador');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/colaboradores')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_familia_adiciona_e_remove(): void
    {
        [$user, $empresa] = $this->suporte();
        $colab = Colaborador::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$colab->id}/familia", ['nome' => 'Maria', 'parentesco' => 'cônjuge'])
            ->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/colaboradores/{$colab->id}/familia")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $famId = $colab->familias()->first()->id;
        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/colaboradores/{$colab->id}/familia/{$famId}")
            ->assertOk();

        $this->assertSame(0, $colab->familias()->count());
    }

    public function test_escopo_por_empresa_nao_vaza(): void
    {
        [$user, $empresa] = $this->suporte();
        // Colaborador de OUTRA empresa não deve aparecer.
        Colaborador::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/colaboradores')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/colaboradores')
            ->assertStatus(403);
    }

    public function test_exame_turno_e_ponto(): void
    {
        [$user, $empresa] = $this->suporte();
        $colab = Colaborador::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        // Exame (ASO)
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$colab->id}/exames", [
                'tipo' => 'periodico', 'realizado_em' => '2026-06-01', 'vencimento' => '2027-06-01', 'resultado' => 'apto',
            ])->assertStatus(201);
        $this->actingAs($user, 'sanctum')->getJson("/api/admin/colaboradores/{$colab->id}/exames")
            ->assertOk()->assertJsonCount(1, 'data');

        // Turno (upsert por dia)
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$colab->id}/turnos", ['dia_semana' => 1, 'entrada' => '08:00', 'saida' => '17:00'])
            ->assertStatus(201);
        // Reenvio do mesmo dia → continua 1 (updateOrCreate).
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$colab->id}/turnos", ['dia_semana' => 1, 'entrada' => '09:00', 'saida' => '18:00'])
            ->assertStatus(201);
        $this->actingAs($user, 'sanctum')->getJson("/api/admin/colaboradores/{$colab->id}/turnos")
            ->assertOk()->assertJsonCount(1, 'data');

        // Ponto
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/colaboradores/{$colab->id}/pontos", ['data' => '2026-06-23', 'entrada' => '08:05'])
            ->assertStatus(201);
        $this->actingAs($user, 'sanctum')->getJson("/api/admin/colaboradores/{$colab->id}/pontos")
            ->assertOk()->assertJsonCount(1, 'data');
    }
}
