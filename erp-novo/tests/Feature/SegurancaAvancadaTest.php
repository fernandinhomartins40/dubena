<?php

namespace Tests\Feature;

use App\Domain\Seguranca\Totp;
use App\Models\Empresa;
use App\Models\PasswordPolicy;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\User2fa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * GATE da FASE A5 — segurança avançada: login_logs, lockout, 2FA (TOTP) e
 * política de senha.
 */
class SegurancaAvancadaTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $attrs = []): User
    {
        $empresa = Empresa::factory()->create();

        return User::factory()->create(array_merge([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'password' => Hash::make('senha-correta-123'),
            'ativo' => true,
        ], $attrs));
    }

    // ─────────────── TOTP (unidade) ───────────────

    public function test_totp_gera_e_verifica_codigo(): void
    {
        $totp = new Totp;
        $secret = $totp->gerarSecret();

        $codigo = $totp->em($secret, (int) floor(time() / 30));
        $this->assertTrue($totp->verificar($secret, $codigo));
        $this->assertFalse($totp->verificar($secret, '000000'));
    }

    // ─────────────── login_logs ───────────────

    public function test_login_registra_sucesso_e_falha(): void
    {
        $user = $this->usuario(['email' => 'log@teste.com']);

        $this->postJson('/api/login', ['email' => 'log@teste.com', 'password' => 'errada'])->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'log@teste.com', 'password' => 'senha-correta-123'])->assertOk();

        $this->assertDatabaseHas('login_logs', ['email' => 'log@teste.com', 'sucesso' => false, 'motivo' => 'credenciais']);
        $this->assertDatabaseHas('login_logs', ['email' => 'log@teste.com', 'sucesso' => true, 'motivo' => 'ok']);
    }

    // ─────────────── Lockout ───────────────

    public function test_lockout_apos_falhas_repetidas(): void
    {
        $this->usuario(['email' => 'alvo@teste.com']);

        // 5 falhas → na 6ª tentativa, lockout (429) mesmo com senha correta.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'alvo@teste.com', 'password' => 'errada'])->assertStatus(401);
        }

        $this->postJson('/api/login', ['email' => 'alvo@teste.com', 'password' => 'senha-correta-123'])
            ->assertStatus(429);

        $this->assertDatabaseHas('login_logs', ['email' => 'alvo@teste.com', 'motivo' => 'lockout']);
    }

    // ─────────────── 2FA no login ───────────────

    public function test_login_exige_otp_quando_2fa_habilitado(): void
    {
        $totp = new Totp;
        $secret = $totp->gerarSecret();
        $user = $this->usuario(['email' => '2fa@teste.com']);
        User2fa::create(['user_id' => $user->id, 'secret' => $secret, 'habilitado' => true, 'confirmado_em' => now()]);

        // Sem OTP → 423 (two_factor_required), sem token.
        $this->postJson('/api/login', ['email' => '2fa@teste.com', 'password' => 'senha-correta-123'])
            ->assertStatus(423)
            ->assertJsonPath('two_factor_required', true);

        // Com OTP válido → ok.
        $codigo = $totp->em($secret, (int) floor(time() / 30));
        $this->postJson('/api/login', ['email' => '2fa@teste.com', 'password' => 'senha-correta-123', 'otp' => $codigo])
            ->assertOk()->assertJsonStructure(['token', 'user']);
    }

    // ─────────────── 2FA setup/confirm ───────────────

    public function test_setup_e_confirmacao_de_2fa(): void
    {
        $user = $this->usuario();

        $setup = $this->actingAs($user, 'sanctum')->postJson('/api/admin/seguranca/2fa/setup')
            ->assertOk()->assertJsonStructure(['secret', 'otpauth_uri']);
        $secret = $setup->json('secret');

        $codigo = (new Totp)->em($secret, (int) floor(time() / 30));
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/seguranca/2fa/confirmar', ['otp' => $codigo])
            ->assertOk()->assertJsonStructure(['recovery_codes']);

        $this->assertDatabaseHas('user_2fa', ['user_id' => $user->id, 'habilitado' => true]);
    }

    public function test_confirmacao_com_codigo_errado_falha(): void
    {
        $user = $this->usuario();
        $this->actingAs($user, 'sanctum')->postJson('/api/admin/seguranca/2fa/setup')->assertOk();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/seguranca/2fa/confirmar', ['otp' => '000000'])
            ->assertStatus(422);
    }

    // ─────────────── Sessões ───────────────

    public function test_lista_e_revoga_sessoes(): void
    {
        $user = $this->usuario();
        $user->createToken('disp-antigo');

        $data = $this->actingAs($user, 'sanctum')->getJson('/api/admin/seguranca/sessoes')->assertOk()->json('data');
        $this->assertNotEmpty($data);

        $id = $data[0]['id'];
        $this->actingAs($user, 'sanctum')->deleteJson("/api/admin/seguranca/sessoes/{$id}")->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $id]);
    }

    // ─────────────── Política de senha ───────────────

    public function test_politica_de_senha_bloqueia_senha_fraca_na_criacao(): void
    {
        $empresa = Empresa::factory()->create();
        $admin = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Admin']);
        $role->permissions()->sync([Permission::firstOrCreate(['chave' => 'usuario.create'])->id, Permission::firstOrCreate(['chave' => 'usuario.edit'])->id]);
        $admin->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        // Define política exigente.
        PasswordPolicy::create(['empresa_id' => $empresa->id, 'min_len' => 12, 'exige_complexidade' => true]);

        // Senha fraca → 422.
        $this->actingAs($admin->fresh(), 'sanctum')->postJson('/api/admin/usuarios', [
            'name' => 'Novo', 'email' => 'novo@teste.com',
            'password' => 'fraca', 'password_confirmation' => 'fraca',
        ])->assertStatus(422)->assertJsonValidationErrorFor('password');

        // Senha forte → ok.
        $this->actingAs($admin->fresh(), 'sanctum')->postJson('/api/admin/usuarios', [
            'name' => 'Novo', 'email' => 'novo@teste.com',
            'password' => 'SenhaForte123', 'password_confirmation' => 'SenhaForte123',
        ])->assertCreated();
    }

    public function test_admin_define_politica_de_senha(): void
    {
        $empresa = Empresa::factory()->create();
        $admin = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Admin']);
        $role->permissions()->sync([Permission::firstOrCreate(['chave' => 'usuario.edit'])->id]);
        $admin->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        $this->actingAs($admin->fresh(), 'sanctum')->putJson('/api/admin/seguranca/politica-senha', [
            'min_len' => 10, 'exige_complexidade' => true, 'expira_dias' => 90,
        ])->assertOk()->assertJsonPath('data.min_len', 10);

        $this->assertDatabaseHas('password_policies', ['empresa_id' => $empresa->id, 'min_len' => 10]);
    }
}
