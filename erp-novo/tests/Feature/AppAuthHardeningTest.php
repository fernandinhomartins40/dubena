<?php

namespace Tests\Feature;

use App\Domain\Seguranca\Totp;
use App\Models\Empresa;
use App\Models\User;
use App\Models\User2fa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * P1 — Hardening de auth dos apps. O login por e-mail/senha do `app/v1` ganha
 * paridade com o web: trilha em login_logs, LOCKOUT por falhas recentes, 2FA
 * (TOTP) e rotação de token (refresh). Espelha SegurancaAvancadaTest (web).
 */
class AppAuthHardeningTest extends TestCase
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

    public function test_app_login_registra_sucesso_e_falha_em_login_logs(): void
    {
        $this->usuario(['email' => 'colab@teste.com']);

        $this->postJson('/api/app/v1/login', ['email' => 'colab@teste.com', 'password' => 'errada'])->assertStatus(422);
        $this->postJson('/api/app/v1/login', ['email' => 'colab@teste.com', 'password' => 'senha-correta-123'])->assertOk();

        $this->assertDatabaseHas('login_logs', ['email' => 'colab@teste.com', 'sucesso' => false, 'motivo' => 'credenciais']);
        $this->assertDatabaseHas('login_logs', ['email' => 'colab@teste.com', 'sucesso' => true, 'motivo' => 'ok']);
    }

    public function test_app_login_faz_lockout_apos_falhas_repetidas(): void
    {
        $this->usuario(['email' => 'lock@teste.com']);

        // 5 falhas dentro da janela → a 6ª tentativa é bloqueada (429), mesmo com senha certa.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/app/v1/login', ['email' => 'lock@teste.com', 'password' => 'errada'])->assertStatus(422);
        }

        $this->postJson('/api/app/v1/login', ['email' => 'lock@teste.com', 'password' => 'senha-correta-123'])
            ->assertStatus(429);

        $this->assertDatabaseHas('login_logs', ['email' => 'lock@teste.com', 'motivo' => 'lockout']);
    }

    public function test_app_login_exige_otp_quando_2fa_habilitado(): void
    {
        $user = $this->usuario(['email' => '2fa@teste.com']);
        $totp = new Totp;
        $secret = $totp->gerarSecret();
        User2fa::query()->create([
            'user_id' => $user->id, 'secret' => $secret, 'habilitado' => true, 'confirmado_em' => now(),
        ]);

        // Sem OTP → 423 (two_factor_required), sem emitir token.
        $this->postJson('/api/app/v1/login', ['email' => '2fa@teste.com', 'password' => 'senha-correta-123'])
            ->assertStatus(423)
            ->assertJson(['two_factor_required' => true]);

        // Com OTP válido → 200 com token.
        $codigo = $totp->em($secret, (int) floor(time() / 30));
        $this->postJson('/api/app/v1/login', ['email' => '2fa@teste.com', 'password' => 'senha-correta-123', 'otp' => $codigo])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'empresa_id']]);
    }

    public function test_refresh_rotaciona_o_token_e_revoga_o_anterior(): void
    {
        $user = $this->usuario();
        $token = $user->createToken('app-dev-x');
        $idAnterior = $token->accessToken->id;

        // Autentica com o token REAL (Bearer) para o currentAccessToken ser o id=1.
        $resp = $this->withToken($token->plainTextToken)
            ->postJson('/api/app/v1/token/refresh')
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);

        // O token novo é diferente e o antigo foi revogado.
        $this->assertNotSame($token->plainTextToken, $resp->json('token'));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $idAnterior]);
        $this->assertDatabaseCount('personal_access_tokens', 1); // só o novo permanece
    }

    public function test_config_de_expiracao_de_token_esta_ativa(): void
    {
        // P1: a config passa a expirar tokens (default 30 dias via env). O Sanctum
        // calcula a expiração a partir de created_at + expiration; aqui garantimos
        // que o valor NÃO é mais null (tokens de app deixaram de ser eternos).
        $this->assertNotNull(config('sanctum.expiration'), 'Tokens Sanctum devem ter expiração configurada (P1).');
    }
}
