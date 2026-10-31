<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PIE P2-D-02 — usuário criado pelo painel passa pela fronteira SaaS.
 *
 * Antes, o funcionário novo logava e levava 403 "Empresa sem vinculo de
 * titularidade SaaS aprovado." em toda chamada: o cadastro não criava
 * membership nem grant. O teste não dá papel nenhum ao usuário novo de
 * propósito — a recusa que sobra tem de ser a do RBAC ("Sem permissão."), não a
 * da fronteira; é isso que isola o que se quer medir.
 *
 * Sem `RefreshDatabase`: cenário gravado pelo owner, como em `AppLoginSobRlsTest`.
 */
class UsuarioNovoSobRlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é específico do PostgreSQL; pulado em '.DB::connection()->getDriverName().'.');
        }

        config(['saas_transformation.enforcement.tenant_envelope' => true]);
    }

    public function test_usuario_criado_pelo_painel_tem_acesso_a_empresa_ativa(): void
    {
        $owner = DB::connection('pgsql_owner');
        $agora = now();
        $marca = fake()->uuid();

        $grupo = $owner->table('grupos')->insertGetId([
            'descricao' => "Usr {$marca}", 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $empresa = $owner->table('empresas')->insertGetId([
            'grupo_id' => $grupo, 'razao_social' => "Empresa Usr {$marca}", 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $tenant = $owner->table('tenant_accounts')->insertGetId([
            'legal_name' => "Tenant {$marca}", 'status' => 'ACTIVE', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $company = $owner->table('tenant_companies')->insertGetId([
            'tenant_account_id' => $tenant, 'empresa_id' => $empresa, 'status' => 'APPROVED',
            'approved_at' => $agora, 'ownership_evidence_ref' => 'usuario-novo-rls',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        // Ator: gerente com `usuario.create` e fronteira aprovada.
        $emailAtor = "ator.{$marca}@teste.invalid";
        $ator = $owner->table('users')->insertGetId([
            'name' => 'Ator', 'email' => $emailAtor, 'password' => Hash::make('Senha-forte-2026!'),
            'empresa_id' => $empresa, 'grupo_id' => $grupo, 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $permissao = $owner->table('permissions')->where('chave', 'usuario.create')->value('id')
            ?? $owner->table('permissions')->insertGetId(['chave' => 'usuario.create', 'created_at' => $agora, 'updated_at' => $agora]);
        $papel = $owner->table('roles')->insertGetId([
            'grupo_id' => $grupo, 'nome' => "Gerente {$marca}", 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $owner->table('permission_role')->insert(['role_id' => $papel, 'permission_id' => $permissao]);
        $owner->table('role_user')->insert(['user_id' => $ator, 'role_id' => $papel, 'empresa_id' => $empresa]);
        $membership = $owner->table('tenant_memberships')->insertGetId([
            'tenant_account_id' => $tenant, 'user_id' => $ator, 'status' => 'ACTIVE',
            'membership_role' => 'OWNER', 'approved_at' => $agora, 'approval_evidence_ref' => 'usuario-novo-rls',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $owner->table('tenant_company_grants')->insert([
            'tenant_membership_id' => $membership, 'empresa_id' => $empresa,
            'tenant_account_id' => $tenant, 'tenant_company_id' => $company,
            'can_read' => true, 'can_operate' => true, 'approved_at' => $agora,
            'grant_evidence_ref' => 'usuario-novo-rls', 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $tokenAtor = $this->postJson('/api/login', ['email' => $emailAtor, 'password' => 'Senha-forte-2026!'])
            ->assertOk()->json('token');

        $emailNovo = "novo.{$marca}@teste.invalid";
        $criado = $this->withToken($tokenAtor)->postJson('/api/admin/usuarios', [
            'name' => 'Atendente Novo', 'email' => $emailNovo,
            'password' => 'Outra-senha-2026!', 'password_confirmation' => 'Outra-senha-2026!',
        ]);
        $criado->assertCreated();
        // No mesmo processo, o `auth:sanctum` do request anterior deixou o guard
        // padrão em sanctum; o login usa o `web` (em produção cada request é novo).
        $this->app['auth']->shouldUse('web');

        $tokenNovo = $this->postJson('/api/login', ['email' => $emailNovo, 'password' => 'Outra-senha-2026!'])
            ->assertOk()->json('token');
        $this->app['auth']->forgetGuards();

        $r = $this->withToken($tokenNovo)->getJson('/api/admin/pedidos');
        $this->assertNotSame('Empresa sem vinculo de titularidade SaaS aprovado.', $r->json('message'));
        $r->assertForbidden()->assertJson(['message' => 'Sem permissão.']);
    }
}
