<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DB-1 (auditoria) — papel GLOBAL (role_user.empresa_id NULL).
 *
 * A PK composta antiga forçava NOT NULL em empresa_id no PostgreSQL, então o
 * caminho global (wherePivotNull em User::temPermissao/papeisEfetivos) era
 * ilegível de persistir. Este teste fixa o contrato da estrutura nova:
 *  - atribuir papel global persiste (empresa_id NULL);
 *  - temPermissao() enxerga a permissão vinda do papel global em QUALQUER empresa;
 *  - unicidade parcial: repetir a MESMA atribuição global viola o índice.
 */
class PapelGlobalTest extends TestCase
{
    use RefreshDatabase;

    public function test_papel_global_persiste_e_concede_permissao(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'GlobalOps']);
        $role->permissions()->sync([Permission::firstOrCreate(['chave' => 'relatorio.view'])->id]);

        // Atribuição GLOBAL: sem empresa no pivot.
        $user->roles()->attach($role->id, ['empresa_id' => null]);

        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'empresa_id' => null,
        ]);

        $this->assertTrue($user->fresh()->temPermissao('relatorio.view'));
        // Papel global vale também com outra empresa ativa.
        $outra = Empresa::factory()->create(['grupo_id' => $empresa->grupo_id]);
        $this->assertTrue($user->fresh()->temPermissao('relatorio.view', $outra->id));
    }

    public function test_atribuicao_global_duplicada_viola_unicidade(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'GlobalDup']);

        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'empresa_id' => null]);

        $this->expectException(QueryException::class);
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'empresa_id' => null]);
    }
}
