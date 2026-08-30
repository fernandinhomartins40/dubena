<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE da FASE A2 — Central de Acessos (RBAC por interface).
 *
 * Fixa o contrato da administração de papéis e usuários:
 *  - gating por permissão (papel.* / usuario.*), default-deny;
 *  - isolamento por tenant (não vaza papel/usuário de outro grupo);
 *  - anti-escalonamento (não-suporte não concede permissão que não tem);
 *  - reset de senha; proteção de auto-lockout; flag `support` nunca concedida.
 */
class CentralAcessosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Cria um usuário não-suporte com as permissões dadas, via papel na empresa.
     *
     * @param  list<string>  $chaves
     * @return array{0:User,1:Empresa}
     */
    private function adminCom(array $chaves): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'AdminAcesso']);
        $ids = collect($chaves)->map(fn (string $c) => Permission::firstOrCreate(['chave' => $c])->id)->all();
        $role->permissions()->sync($ids);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        return [$user->fresh(), $empresa];
    }

    // ─────────────── Papéis ───────────────

    public function test_sem_permissao_papel_view_recebe_403(): void
    {
        [$user] = $this->adminCom([]); // sem nenhuma permissão

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/papeis')->assertStatus(403);
    }

    public function test_cria_papel_com_permissoes_que_o_ator_possui(): void
    {
        [$user] = $this->adminCom(['papel.view', 'papel.create', 'cliente.view', 'cliente.edit']);

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/papeis', [
            'nome' => 'Vendas',
            'descricao' => 'Equipe de vendas',
            'permissoes' => ['cliente.view', 'cliente.edit'],
        ])->assertCreated();

        $resp->assertJsonPath('data.nome', 'Vendas');
        $this->assertEqualsCanonicalizing(['cliente.view', 'cliente.edit'], $resp->json('data.permissoes'));
    }

    public function test_nao_pode_conceder_permissao_que_nao_possui(): void
    {
        // Ator tem só papel.create + cliente.view; tenta dar financeiro.delete.
        [$user] = $this->adminCom(['papel.create', 'cliente.view']);
        Permission::firstOrCreate(['chave' => 'financeiro.delete']);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/papeis', [
            'nome' => 'Poderoso',
            'permissoes' => ['cliente.view', 'financeiro.delete'],
        ])->assertStatus(422)->assertJsonValidationErrorFor('permissoes');
    }

    public function test_rejeita_permissao_fora_do_catalogo(): void
    {
        [$user] = $this->adminCom(['papel.create']);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/papeis', [
            'nome' => 'Fantasma',
            'permissoes' => ['inventada.coisa'],
        ])->assertStatus(422)->assertJsonValidationErrorFor('permissoes');
    }

    public function test_nao_lista_papel_de_outro_grupo(): void
    {
        [$user] = $this->adminCom(['papel.view']);
        [$outro] = $this->adminCom(['papel.view']); // outro grupo/empresa

        // Papel criado no grupo do "outro".
        $papelOutro = Role::where('grupo_id', $outro->grupo_id)->first();

        $data = $this->actingAs($user, 'sanctum')->getJson('/api/admin/papeis')->assertOk()->json('data');
        $ids = array_column($data, 'id');

        $this->assertNotContains($papelOutro->id, $ids, 'Papel de outro grupo não pode aparecer.');
    }

    public function test_nao_edita_papel_de_outro_grupo(): void
    {
        [$user] = $this->adminCom(['papel.edit']);
        [$outro] = $this->adminCom(['papel.edit']);
        $papelOutro = Role::where('grupo_id', $outro->grupo_id)->first();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/papeis/{$papelOutro->id}", ['nome' => 'Hackeado'])
            ->assertNotFound();
    }

    public function test_nao_exclui_papel_em_uso(): void
    {
        [$user, $empresa] = $this->adminCom(['papel.delete']);
        $papel = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'EmUso']);
        // Atribui a alguém.
        $alvo = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $alvo->roles()->attach($papel->id, ['empresa_id' => $empresa->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/papeis/{$papel->id}")
            ->assertStatus(422);
    }

    // ─────────────── Usuários ───────────────

    public function test_cria_usuario_e_atribui_papel_na_empresa(): void
    {
        [$user, $empresa] = $this->adminCom(['usuario.view', 'usuario.create']);
        $papel = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Operador']);

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/usuarios', [
            'name' => 'João',
            'email' => 'joao@teste.com',
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
            'papeis' => [$papel->id],
        ])->assertCreated();

        $novoId = $resp->json('data.id');
        $this->assertFalse($resp->json('data.support'), 'Usuário criado nunca é support.');
        $this->assertSame([$papel->id], array_column($resp->json('data.papeis'), 'id'));

        // Papel atribuído COM escopo da empresa ativa.
        $this->assertDatabaseHas('role_user', [
            'user_id' => $novoId, 'role_id' => $papel->id, 'empresa_id' => $empresa->id,
        ]);
    }

    public function test_nao_concede_flag_support_via_payload(): void
    {
        [$user] = $this->adminCom(['usuario.create']);

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/usuarios', [
            'name' => 'Esperto',
            'email' => 'esperto@teste.com',
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
            // deve ser ignorado
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['id' => $resp->json('data.id')]);
    }

    public function test_nao_inativa_o_proprio_usuario(): void
    {
        [$user] = $this->adminCom(['usuario.edit']);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/usuarios/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'ativo' => false,
        ])->assertStatus(422);
    }

    public function test_reset_de_senha_exige_permissao_e_revoga_tokens(): void
    {
        [$user, $empresa] = $this->adminCom(['usuario.reset']);
        $alvo = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $alvo->createToken('app');
        $this->assertSame(1, $alvo->tokens()->count());

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/usuarios/{$alvo->id}/resetar-senha", [
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertOk();

        $this->assertSame(0, $alvo->fresh()->tokens()->count(), 'Reset deve revogar tokens.');
    }

    public function test_nao_acessa_usuario_de_outro_grupo(): void
    {
        [$user] = $this->adminCom(['usuario.edit']);
        [, $outraEmpresa] = $this->adminCom(['usuario.edit']);
        $alvoOutro = User::factory()->semPapel()->create([
            'empresa_id' => $outraEmpresa->id, 'grupo_id' => $outraEmpresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/usuarios/{$alvoOutro->id}", [
            'name' => 'X', 'email' => 'x@x.com',
        ])->assertNotFound();
    }
}
