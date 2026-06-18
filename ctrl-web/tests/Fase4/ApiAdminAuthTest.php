<?php

namespace Tests\Fase4;

use Tests\TestCase;

/**
 * S1 (SPA React) — API admin (Sanctum). Garante o contrato consumido pelo SPA:
 * health público, login válido/ inválido, me/dashboard exigindo auth, e o payload
 * de /me trazendo permissões RBAC.
 */
class ApiAdminAuthTest extends TestCase
{
    private function admin()
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        return \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
    }

    public function test_health_publico()
    {
        $this->getJson('/api/admin/health')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_me_exige_autenticacao()
    {
        $this->getJson('/api/admin/me')->assertUnauthorized();
    }

    public function test_dashboard_exige_autenticacao()
    {
        $this->getJson('/api/admin/dashboard/resumo')->assertUnauthorized();
    }

    public function test_login_invalido_falha()
    {
        $this->admin();
        $this->postJson('/api/admin/login', ['email' => 'admin', 'password' => 'errado'])
            ->assertStatus(422);
    }

    public function test_login_valido_retorna_usuario_e_permissoes()
    {
        $this->admin();
        $resp = $this->postJson('/api/admin/login', [
            'email'    => env('ADMIN_SEED_EMAIL', 'admin'),
            'password' => env('ADMIN_SEED_PASSWORD', 'admin1234'),
        ]);
        $resp->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'is_support', 'roles', 'permissions']);
        // admin é support → permissões não-vazias
        $this->assertNotEmpty($resp->json('permissions'));
    }

    public function test_me_autenticado_via_actingAs()
    {
        $admin = $this->admin();
        $this->actingAs($admin)
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJson(['id' => $admin->id]);
    }
}
