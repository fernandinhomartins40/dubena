<?php

namespace Tests\Feature;

use App\Models\Apoio\Banco;
use App\Models\Apoio\Segmento;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N1 — cadastros de apoio genéricos: CRUD, unicidade por grupo, escopo de grupo, RBAC.
 */
class CadastroApoioTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioSuporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        return [$user, $empresa];
    }

    public function test_lista_cadastros_do_grupo(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();
        Segmento::factory()->create(['grupo_id' => $empresa->grupo_id, 'descricao' => 'Residencial']);
        // Segmento de OUTRO grupo não deve aparecer.
        Segmento::factory()->create(['descricao' => 'Industrial']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/cadastros/segmentos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.descricao', 'Residencial');
    }

    public function test_cria_cadastro_no_grupo_ativo(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/segmentos', ['descricao' => 'Comercial'])
            ->assertCreated()
            ->assertJsonPath('data.descricao', 'Comercial')
            ->assertJsonPath('data.grupo_id', $empresa->grupo_id);

        $this->assertDatabaseHas('segmentos', [
            'descricao' => 'Comercial',
            'grupo_id' => $empresa->grupo_id,
        ]);
    }

    public function test_rejeita_descricao_duplicada_no_grupo(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();
        Segmento::factory()->create(['grupo_id' => $empresa->grupo_id, 'descricao' => 'Residencial']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/segmentos', ['descricao' => 'residencial'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('descricao');
    }

    public function test_valida_campo_extra_booleano(): void
    {
        [$user] = $this->usuarioSuporte();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/telefone-tipos', ['descricao' => 'Celular', 'celular' => true])
            ->assertCreated()
            ->assertJsonPath('data.celular', true);
    }

    public function test_atualiza_e_exclui(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();
        $seg = Segmento::factory()->create(['grupo_id' => $empresa->grupo_id, 'descricao' => 'Antigo']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/cadastros/segmentos/{$seg->id}", ['descricao' => 'Novo'])
            ->assertOk()
            ->assertJsonPath('data.descricao', 'Novo');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/cadastros/segmentos/{$seg->id}")
            ->assertOk();

        $this->assertDatabaseMissing('segmentos', ['id' => $seg->id]);
    }

    public function test_cadastros_restantes_c12(): void
    {
        [$user, $empresa] = $this->usuarioSuporte();

        // Agência com banco vinculado.
        $banco = Banco::create(['grupo_id' => $empresa->grupo_id, 'descricao' => 'Banco do Brasil', 'codigo' => '001']);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/agencias', ['descricao' => 'Centro', 'banco_id' => $banco->id, 'numero' => '1234'])
            ->assertCreated()->assertJsonPath('data.banco_id', $banco->id);

        // Feriado exige data.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/feriados', ['descricao' => 'Natal'])
            ->assertStatus(422)->assertJsonValidationErrors('data');
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/cadastros/feriados', ['descricao' => 'Natal', 'data' => '2026-12-25', 'recorrente' => true])
            ->assertCreated()->assertJsonPath('data.recorrente', true);

        // Profissão e transportadora.
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/cadastros/profissoes', ['descricao' => 'Entregador'])->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/cadastros/transportadoras', ['descricao' => 'Expressa', 'cnpj' => '12345678000199'])->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/cadastros/estados-civis', ['descricao' => 'Casado'])->assertCreated();
    }

    public function test_tipo_desconhecido_retorna_404(): void
    {
        [$user] = $this->usuarioSuporte();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/cadastros/inexistente')
            ->assertStatus(404);
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => false, // sem roles → sem permissão
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/cadastros/segmentos')
            ->assertStatus(403);
    }
}
