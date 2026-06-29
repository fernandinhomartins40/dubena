<?php

namespace Tests\Feature;

use App\Domain\Seguranca\Totp;
use App\Models\Empresa;
use App\Models\Saas\Assinatura;
use App\Models\Saas\Plano;
use App\Models\Saas\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * P4 — Painel SuperAdmin (cross-tenant). Valida o que mais importa para o sigilo:
 *  - login com 2FA obrigatório (quando habilitado);
 *  - SuperAdmin enxerga TODAS as empresas (cross-tenant), mas só via o service;
 *  - usuário de tenant NÃO acessa /superadmin/* (guard separado);
 *  - toda ação cross-tenant é AUDITADA;
 *  - suspender empresa bloqueia o tenant.
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attrs = []): PlatformAdmin
    {
        return PlatformAdmin::factory()->create(array_merge([
            'password' => Hash::make('super-123'),
        ], $attrs));
    }

    private function logar(PlatformAdmin $admin): string
    {
        return $admin->createToken('teste')->plainTextToken;
    }

    public function test_login_sem_2fa_emite_token(): void
    {
        $this->admin(['email' => 'sa@plat.com']);

        $this->postJson('/api/superadmin/login', ['email' => 'sa@plat.com', 'password' => 'super-123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'admin' => ['id', 'nome', 'email']]);

        $this->assertDatabaseHas('platform_audit_logs', ['acao' => 'login.ok']);
    }

    public function test_login_com_2fa_habilitado_exige_otp(): void
    {
        $totp = new Totp;
        $secret = $totp->gerarSecret();
        $admin = $this->admin([
            'email' => '2fa@plat.com', 'twofa_habilitado' => true, 'twofa_secret' => $secret,
        ]);

        // Sem OTP → 423.
        $this->postJson('/api/superadmin/login', ['email' => '2fa@plat.com', 'password' => 'super-123'])
            ->assertStatus(423)->assertJson(['two_factor_required' => true]);

        // Com OTP → 200.
        $codigo = $totp->em($secret, (int) floor(time() / 30));
        $this->postJson('/api/superadmin/login', ['email' => '2fa@plat.com', 'password' => 'super-123', 'otp' => $codigo])
            ->assertOk();
    }

    public function test_credenciais_invalidas_401_e_auditadas(): void
    {
        $this->admin(['email' => 'sa@plat.com']);

        $this->postJson('/api/superadmin/login', ['email' => 'sa@plat.com', 'password' => 'errada'])
            ->assertStatus(401);

        $this->assertDatabaseHas('platform_audit_logs', ['acao' => 'login.falha']);
    }

    public function test_superadmin_lista_empresas_de_todos_os_tenants(): void
    {
        $token = $this->logar($this->admin());
        $e1 = Empresa::factory()->create(['razao_social' => 'Alfa Gás']);
        $e2 = Empresa::factory()->create(['razao_social' => 'Beta Gás']);

        $resp = $this->withToken($token)->getJson('/api/superadmin/empresas')->assertOk();
        $ids = collect($resp->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($e1->id) && $ids->contains($e2->id));
    }

    public function test_usuario_de_tenant_nao_acessa_superadmin(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        // Token de tenant (guard sanctum) não vale no guard 'platform'.
        $this->actingAs($user, 'sanctum')->getJson('/api/superadmin/empresas')->assertStatus(401);
    }

    public function test_definir_assinatura_aplica_plano_e_audita_e_invalida_licenca(): void
    {
        $token = $this->logar($this->admin());
        $empresa = Empresa::factory()->create();
        $plano = Plano::query()->create(['slug' => 'pro', 'nome' => 'Pro', 'preco_mensal' => 200, 'ativo' => true]);
        $plano->recursos()->create(['recurso_chave' => 'marketplace']);

        $this->withToken($token)->putJson("/api/superadmin/empresas/{$empresa->id}/assinatura", [
            'plano_id' => $plano->id, 'status' => 'ativa',
        ])->assertCreated();

        $this->assertDatabaseHas('assinaturas', ['empresa_id' => $empresa->id, 'plano_id' => $plano->id, 'status' => 'ativa']);
        $this->assertDatabaseHas('platform_audit_logs', ['acao' => 'assinatura.definida', 'empresa_id' => $empresa->id]);

        // Recursos efetivos da empresa passam a ser os do plano.
        $this->withToken($token)->getJson("/api/superadmin/empresas/{$empresa->id}/recursos")
            ->assertOk()->assertJsonFragment(['marketplace']);
    }

    public function test_override_liga_recurso_fora_do_plano(): void
    {
        $token = $this->logar($this->admin());
        $empresa = Empresa::factory()->create();
        $plano = Plano::query()->create(['slug' => 'basico', 'nome' => 'Básico', 'preco_mensal' => 100, 'ativo' => true]);
        Assinatura::query()->forceCreate([
            'empresa_id' => $empresa->id, 'plano_id' => $plano->id, 'status' => 'ativa', 'inicio' => now()->subDay(),
        ]);

        $this->withToken($token)->putJson("/api/superadmin/empresas/{$empresa->id}/override", [
            'recurso_chave' => 'app_entregador', 'habilitado' => true,
        ])->assertCreated();

        $this->withToken($token)->getJson("/api/superadmin/empresas/{$empresa->id}/recursos")
            ->assertOk()->assertJsonFragment(['app_entregador']);
        $this->assertDatabaseHas('platform_audit_logs', ['acao' => 'recurso.override', 'empresa_id' => $empresa->id]);
    }

    public function test_suspender_empresa_bloqueia_e_audita(): void
    {
        $token = $this->logar($this->admin());
        $empresa = Empresa::factory()->create(['ativo' => true]);

        $this->withToken($token)->postJson("/api/superadmin/empresas/{$empresa->id}/suspender")->assertOk();

        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'ativo' => false]);
        $this->assertDatabaseHas('platform_audit_logs', ['acao' => 'empresa.suspensa', 'empresa_id' => $empresa->id]);
    }

    public function test_dashboard_agrega_a_plataforma(): void
    {
        $token = $this->logar($this->admin());
        Empresa::factory()->count(3)->create();

        $this->withToken($token)->getJson('/api/superadmin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.empresas_total', fn ($v) => $v >= 3);
    }

    public function test_salvar_plano_cria_catalogo_com_recursos(): void
    {
        $token = $this->logar($this->admin());

        $this->withToken($token)->postJson('/api/superadmin/planos', [
            'slug' => 'starter', 'nome' => 'Starter', 'preco_mensal' => 49.9,
            'recursos' => ['app_consumidor', 'inexistente_xyz'], // a inválida é descartada
        ])->assertCreated();

        $plano = Plano::query()->where('slug', 'starter')->with('recursos')->first();
        $this->assertNotNull($plano);
        $this->assertEqualsCanonicalizing(['app_consumidor'], $plano->recursos->pluck('recurso_chave')->all());
    }
}
