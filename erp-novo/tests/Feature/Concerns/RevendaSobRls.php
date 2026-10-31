<?php

namespace Tests\Feature\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Revenda completa (grupo, empresa, tenant aprovado) e um usuário com as
 * permissões pedidas, gravados pela conexão de owner — que commita por conta
 * própria; por isso quem usa não usa `RefreshDatabase`. Devolve o token do
 * login real, para a requisição atravessar envelope e RLS como em produção.
 */
trait RevendaSobRls
{
    /**
     * @param  list<string>  $permissoes
     * @return array{token: string, empresa: int, grupo: int, tenant: int, user: int}
     */
    protected function revendaComUsuario(array $permissoes): array
    {
        $owner = DB::connection('pgsql_owner');
        $agora = now();
        $marca = fake()->uuid();

        $grupo = $owner->table('grupos')->insertGetId([
            'descricao' => "Rev {$marca}", 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $empresa = $owner->table('empresas')->insertGetId([
            'grupo_id' => $grupo, 'razao_social' => "Empresa {$marca}", 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $tenant = $owner->table('tenant_accounts')->insertGetId([
            'legal_name' => "Tenant {$marca}", 'status' => 'ACTIVE', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $company = $owner->table('tenant_companies')->insertGetId([
            'tenant_account_id' => $tenant, 'empresa_id' => $empresa, 'status' => 'APPROVED',
            'approved_at' => $agora, 'ownership_evidence_ref' => 'teste-rls',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $email = "usr.{$marca}@teste.invalid";
        $user = $owner->table('users')->insertGetId([
            'name' => 'Usuario RLS', 'email' => $email, 'password' => Hash::make('Senha-forte-2026!'),
            'empresa_id' => $empresa, 'grupo_id' => $grupo, 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $papel = $owner->table('roles')->insertGetId([
            'grupo_id' => $grupo, 'nome' => "Papel {$marca}", 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        foreach ($permissoes as $chave) {
            $id = $owner->table('permissions')->where('chave', $chave)->value('id')
                ?? $owner->table('permissions')->insertGetId(['chave' => $chave, 'created_at' => $agora, 'updated_at' => $agora]);
            $owner->table('permission_role')->insert(['role_id' => $papel, 'permission_id' => $id]);
        }
        $owner->table('role_user')->insert(['user_id' => $user, 'role_id' => $papel, 'empresa_id' => $empresa]);

        $membership = $owner->table('tenant_memberships')->insertGetId([
            'tenant_account_id' => $tenant, 'user_id' => $user, 'status' => 'ACTIVE',
            'membership_role' => 'MEMBER', 'approved_at' => $agora, 'approval_evidence_ref' => 'teste-rls',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $owner->table('tenant_company_grants')->insert([
            'tenant_membership_id' => $membership, 'empresa_id' => $empresa,
            'tenant_account_id' => $tenant, 'tenant_company_id' => $company,
            'can_read' => true, 'can_operate' => true, 'approved_at' => $agora,
            'grant_evidence_ref' => 'teste-rls', 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $token = $this->postJson('/api/login', ['email' => $email, 'password' => 'Senha-forte-2026!'])
            ->assertOk()->json('token');

        return ['token' => $token, 'empresa' => $empresa, 'grupo' => $grupo, 'tenant' => $tenant, 'user' => $user];
    }
}
