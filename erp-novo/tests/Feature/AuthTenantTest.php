<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke do N0: login emite token; rota protegida exige auth; tenant é resolvido
 * por requisição (substitui Session('empresa_padrao')).
 */
class AuthTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_emite_token_e_retorna_usuario(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'email' => 'op@teste.com',
            'password' => bcrypt('segredo123'),
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        $resp = $this->postJson('/api/login', [
            'email' => 'op@teste.com',
            'password' => 'segredo123',
        ]);

        $resp->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'empresa_id', 'grupo_id']]);
        $this->assertEquals($empresa->id, $resp->json('user.empresa_id'));
    }

    public function test_login_invalido_retorna_401(): void
    {
        User::factory()->create(['email' => 'x@teste.com', 'password' => bcrypt('certa')]);

        $this->postJson('/api/login', ['email' => 'x@teste.com', 'password' => 'errada'])
            ->assertStatus(401);
    }

    public function test_rota_protegida_exige_autenticacao(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_resolve_tenant_do_usuario_autenticado(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('tenant.empresa_id', $empresa->id)
            ->assertJsonPath('tenant.grupo_id', $empresa->grupo_id);
    }
}
