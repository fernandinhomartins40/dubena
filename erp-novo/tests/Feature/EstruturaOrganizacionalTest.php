<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Organizacao\Departamento;
use App\Models\Organizacao\SetorOrg;
use App\Models\Organizacao\Unidade;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE da FASE A3 — Estrutura organizacional (hierarquia + escopo).
 *
 * Cobre: CRUD da árvore unidade→departamento→setor com gating por permissão;
 * isolamento por tenant (404 cross-empresa); anti-ciclo na árvore de unidades;
 * e a gravação do ESCOPO hierárquico na atribuição de papel ao usuário.
 */
class EstruturaOrganizacionalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Usuário não-suporte com as permissões dadas + tenant ativo na empresa dele.
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

        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'AdminEstrutura']);
        $ids = collect($chaves)->map(fn (string $c) => Permission::firstOrCreate(['chave' => $c])->id)->all();
        $role->permissions()->sync($ids);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        return [$user->fresh(), $empresa];
    }

    public function test_sem_permissao_unidade_view_recebe_403(): void
    {
        [$user] = $this->adminCom([]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/unidades')->assertStatus(403);
    }

    public function test_cria_arvore_unidade_departamento_setor(): void
    {
        [$user] = $this->adminCom([
            'unidade.view', 'unidade.create',
            'departamento.create', 'setor.create',
        ]);

        $uni = $this->actingAs($user, 'sanctum')->postJson('/api/admin/unidades', [
            'nome' => 'Matriz', 'tipo' => 'matriz',
        ])->assertCreated()->json('data.id');

        $dep = $this->actingAs($user, 'sanctum')->postJson('/api/admin/departamentos', [
            'unidade_id' => $uni, 'nome' => 'Financeiro',
        ])->assertCreated()->json('data.id');

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/setores-org', [
            'departamento_id' => $dep, 'nome' => 'Contas a Pagar',
        ])->assertCreated();

        $this->assertDatabaseHas('setores_org', ['nome' => 'Contas a Pagar', 'departamento_id' => $dep]);
    }

    public function test_anti_ciclo_na_arvore_de_unidades(): void
    {
        [$user, $empresa] = $this->adminCom(['unidade.edit']);

        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        $a = Unidade::create(['nome' => 'A', 'tipo' => 'matriz']);
        $b = Unidade::create(['nome' => 'B', 'parent_id' => $a->id]);
        app(TenantContext::class)->clear();

        // Tentar tornar A filha de B (B já é filha de A) → ciclo.
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/unidades/{$a->id}", [
            'nome' => 'A', 'parent_id' => $b->id,
        ])->assertStatus(422)->assertJsonValidationErrorFor('parent_id');
    }

    public function test_nao_acessa_unidade_de_outra_empresa(): void
    {
        [$user] = $this->adminCom(['unidade.edit']);
        [, $outra] = $this->adminCom(['unidade.edit']);

        app(TenantContext::class)->set($outra->id, $outra->grupo_id);
        $uniOutra = Unidade::create(['nome' => 'Alheia', 'tipo' => 'matriz']);
        app(TenantContext::class)->clear();

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/unidades/{$uniOutra->id}", [
            'nome' => 'Invadida',
        ])->assertNotFound();
    }

    public function test_nao_exclui_unidade_com_departamentos(): void
    {
        [$user, $empresa] = $this->adminCom(['unidade.delete']);

        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        $uni = Unidade::create(['nome' => 'Matriz', 'tipo' => 'matriz']);
        Departamento::create(['unidade_id' => $uni->id, 'nome' => 'RH']);
        app(TenantContext::class)->clear();

        $this->actingAs($user, 'sanctum')->deleteJson("/api/admin/unidades/{$uni->id}")->assertStatus(422);
    }

    public function test_atribuicao_de_papel_grava_escopo_hierarquico(): void
    {
        [$admin, $empresa] = $this->adminCom(['usuario.create']);

        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        $uni = Unidade::create(['nome' => 'Filial Centro', 'tipo' => 'filial']);
        $dep = Departamento::create(['unidade_id' => $uni->id, 'nome' => 'Vendas']);
        $setor = SetorOrg::create(['departamento_id' => $dep->id, 'nome' => 'Equipe 1']);
        app(TenantContext::class)->clear();

        $papel = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Operador']);

        $resp = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/usuarios', [
            'name' => 'Maria',
            'email' => 'maria@teste.com',
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
            'papeis' => [[
                'id' => $papel->id,
                'unidade_id' => $uni->id,
                'departamento_id' => $dep->id,
                'setor_id' => $setor->id,
                'herda_filhos' => false,
            ]],
        ])->assertCreated();

        $novoId = $resp->json('data.id');
        $this->assertDatabaseHas('role_user', [
            'user_id' => $novoId,
            'role_id' => $papel->id,
            'empresa_id' => $empresa->id,
            'unidade_id' => $uni->id,
            'departamento_id' => $dep->id,
            'setor_id' => $setor->id,
            'herda_filhos' => false,
        ]);

        // O payload do usuário reflete o escopo gravado.
        $papelSerializado = $resp->json('data.papeis.0');
        $this->assertSame($uni->id, $papelSerializado['unidade_id']);
        $this->assertFalse($papelSerializado['herda_filhos']);
    }

    public function test_escopo_de_outra_empresa_e_ignorado_na_atribuicao(): void
    {
        [$admin, $empresa] = $this->adminCom(['usuario.create']);
        [, $outra] = $this->adminCom(['usuario.create']);

        // Unidade pertence a OUTRA empresa.
        app(TenantContext::class)->set($outra->id, $outra->grupo_id);
        $uniOutra = Unidade::create(['nome' => 'Alheia', 'tipo' => 'matriz']);
        app(TenantContext::class)->clear();

        $papel = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Operador']);

        $resp = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/usuarios', [
            'name' => 'Carlos',
            'email' => 'carlos@teste.com',
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
            'papeis' => [['id' => $papel->id, 'unidade_id' => $uniOutra->id]],
        ])->assertCreated();

        // O escopo cross-tenant é descartado (vira null = empresa inteira).
        $this->assertDatabaseHas('role_user', [
            'user_id' => $resp->json('data.id'),
            'role_id' => $papel->id,
            'empresa_id' => $empresa->id,
            'unidade_id' => null,
        ]);
    }
}
