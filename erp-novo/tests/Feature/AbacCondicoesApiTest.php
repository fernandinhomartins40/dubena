<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Permission;
use App\Models\PermissionCondition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE da FASE A4 — API de condições ABAC (gestão por papel, via UI).
 */
class AbacCondicoesApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa,2:Role} admin com papel.* + um papel-alvo com cliente.view */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $adminRole = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Admin']);
        foreach (['papel.view', 'papel.edit'] as $c) {
            $adminRole->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['chave' => $c])->id]);
        }
        $user->roles()->attach($adminRole->id, ['empresa_id' => $empresa->id]);

        // Papel-alvo que receberá a condição, com a permissão cliente.view.
        $alvo = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Vendas']);
        $alvo->permissions()->sync([Permission::firstOrCreate(['chave' => 'cliente.view'])->id]);

        return [$user->fresh(), $empresa, $alvo];
    }

    public function test_cria_e_lista_condicao_do_papel(): void
    {
        [$user, , $alvo] = $this->cenario();
        $permId = Permission::where('chave', 'cliente.view')->value('id');

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/papeis/{$alvo->id}/condicoes", [
            'tipo' => 'limite',
            'permission_id' => $permId,
            'parametros' => ['campo' => 'valor', 'valor_max' => 1000],
        ])->assertCreated();

        $this->assertDatabaseHas('permission_conditions', [
            'role_id' => $alvo->id, 'permission_id' => $permId, 'tipo' => 'limite',
        ]);

        $data = $this->actingAs($user, 'sanctum')->getJson("/api/admin/papeis/{$alvo->id}/condicoes")
            ->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('cliente.view', $data[0]['permissao']);
    }

    public function test_rejeita_condicao_em_permissao_fora_do_papel(): void
    {
        [$user, , $alvo] = $this->cenario();
        // financeiro.delete NÃO pertence ao papel-alvo.
        $permId = Permission::firstOrCreate(['chave' => 'financeiro.delete'])->id;

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/papeis/{$alvo->id}/condicoes", [
            'tipo' => 'limite', 'permission_id' => $permId, 'parametros' => ['valor_max' => 1],
        ])->assertStatus(422)->assertJsonValidationErrorFor('permission_id');
    }

    public function test_rejeita_tipo_invalido(): void
    {
        [$user, , $alvo] = $this->cenario();
        $permId = Permission::where('chave', 'cliente.view')->value('id');

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/papeis/{$alvo->id}/condicoes", [
            'tipo' => 'magico', 'permission_id' => $permId,
        ])->assertStatus(422)->assertJsonValidationErrorFor('tipo');
    }

    public function test_remove_condicao(): void
    {
        [$user, $empresa, $alvo] = $this->cenario();
        $cond = PermissionCondition::create([
            'empresa_id' => $empresa->id, 'role_id' => $alvo->id,
            'permission_id' => Permission::where('chave', 'cliente.view')->value('id'),
            'tipo' => 'ownership', 'parametros' => [], 'ativo' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/papeis/{$alvo->id}/condicoes/{$cond->id}")
            ->assertOk();

        $this->assertDatabaseMissing('permission_conditions', ['id' => $cond->id]);
    }

    public function test_sem_permissao_papel_edit_nao_cria_condicao(): void
    {
        [, $empresa, $alvo] = $this->cenario();
        // Usuário só com papel.view (sem edit).
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'SoLeitura']);
        $role->permissions()->sync([Permission::firstOrCreate(['chave' => 'papel.view'])->id]);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        $this->actingAs($user->fresh(), 'sanctum')->postJson("/api/admin/papeis/{$alvo->id}/condicoes", [
            'tipo' => 'limite', 'permission_id' => Permission::where('chave', 'cliente.view')->value('id'),
        ])->assertStatus(403);
    }
}
