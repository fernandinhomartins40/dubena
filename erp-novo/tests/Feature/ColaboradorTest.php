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
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
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

    /**
     * "Excluir" DESATIVA. Aqui o delete fisico era o mais destrutivo do sistema:
     * TODAS as sub-tabelas de RH sao cascadeOnDelete e nenhuma FK segurava a
     * operacao — apagar levava junto o historico trabalhista.
     */
    public function test_excluir_colaborador_desativa_e_preserva_historico(): void
    {
        [$user, $empresa] = $this->suporte();
        $colab = Colaborador::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true,
        ]);
        $colab->familias()->create(['nome' => 'Filho']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/colaboradores/{$colab->id}", ['motivo' => 'Desligado'])
            ->assertOk()
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('colaboradores', [
            'id' => $colab->id, 'ativo' => false, 'motivo_desativacao' => 'Desligado',
        ]);
        $this->assertDatabaseCount('colaborador_familias', 1); // nada de cascade
    }

    public function test_lista_colaboradores_filtra_por_situacao(): void
    {
        [$user, $empresa] = $this->suporte();
        $base = ['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id];
        $ativo = Colaborador::factory()->create($base + ['ativo' => true]);
        $inativo = Colaborador::factory()->create($base + ['ativo' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/colaboradores')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ativo->id);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/colaboradores?situacao=inativos')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inativo->id);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/colaboradores?situacao=todos')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_reativar_colaborador(): void
    {
        [$user, $empresa] = $this->suporte();
        $colab = Colaborador::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => false,
        ]);

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/colaboradores/{$colab->id}/reativar")
            ->assertOk()->assertJsonPath('data.ativo', true);

        $this->assertDatabaseHas('colaboradores', ['id' => $colab->id, 'ativo' => true, 'desativado_em' => null]);
    }
}
