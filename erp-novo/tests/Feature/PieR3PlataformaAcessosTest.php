<?php

namespace Tests\Feature;

use App\Domain\Seguranca\Totp;
use App\Models\Empresa;
use App\Models\Saas\PlatformAdmin;
use App\Models\User;
use Database\Seeders\DeployAdminSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PIE Rodada 3 — Agente C (plataforma e acessos). Cada teste amarra um achado
 * reproduzido no runtime ao comportamento que o corrige.
 */
class PieR3PlataformaAcessosTest extends TestCase
{
    use RefreshDatabase;

    // ───────────── Admin do deploy sem papel ─────────────

    /**
     * Instalação nova: DeployAdminSeeder + RbacSeeder, com o enforcement SaaS
     * ligado (F2-05: `support` não é mais bypass). O admin tem de conseguir
     * administrar acessos — senão ninguém cria o primeiro usuário.
     */
    public function test_admin_do_deploy_nasce_com_papel_administrador(): void
    {
        
        putenv('ADMIN_SEED_PASSWORD=Senha-Forte-Do-Deploy-2026');
        try {
            (new DeployAdminSeeder)->run();
            (new RbacSeeder)->run();
        } finally {
            
            putenv('ADMIN_SEED_PASSWORD');
        }
        config(['saas_transformation.enforcement.tenant_envelope' => true]);

        $admin = User::query()->where('email', 'admin@gasemcasa.com')->firstOrFail();

        $this->assertFalse($admin->acessoElevado(), 'pré-condição: support sem break-glass não é bypass');
        $this->assertSame(['Administrador'], $admin->papeisEfetivos($admin->empresa_id)->pluck('nome')->all());
        $this->assertTrue($admin->temPermissao('usuario.create', $admin->empresa_id));
        $this->assertTrue($admin->temPermissao('papel.edit', $admin->empresa_id));
    }

    /** O deploy seguinte não desfaz o papel que o dono escolheu para o admin. */
    public function test_rbac_seeder_nao_sobrescreve_papeis_ajustados_do_admin(): void
    {
        
        putenv('ADMIN_SEED_PASSWORD=Senha-Forte-Do-Deploy-2026');
        try {
            (new DeployAdminSeeder)->run();
            (new RbacSeeder)->run();
            $admin = User::query()->where('email', 'admin@gasemcasa.com')->firstOrFail();
            $gerente = \App\Models\Role::query()->where('grupo_id', $admin->grupo_id)->where('nome', 'Gerente')->firstOrFail();
            $admin->roles()->detach();
            $admin->roles()->attach($gerente->id, ['empresa_id' => $admin->empresa_id, 'herda_filhos' => true]);

            (new RbacSeeder)->run();
        } finally {
            
            putenv('ADMIN_SEED_PASSWORD');
        }

        $this->assertSame(['Gerente'], $admin->fresh()->roles->pluck('nome')->all());
    }

    // ───────────── P2-E-06: superadmin ─────────────

    private function sa(array $attrs = []): PlatformAdmin
    {
        return PlatformAdmin::factory()->create(array_merge([
            'email' => 'sa@plat.test', 'password' => Hash::make('Senha-Super-123'),
        ], $attrs));
    }

    public function test_token_de_cadastro_do_2fa_nao_abre_o_painel(): void
    {
        $this->sa();
        $setup = $this->postJson('/api/superadmin/login', ['email' => 'sa@plat.test', 'password' => 'Senha-Super-123'])
            ->assertStatus(423)->json('setup_token');
        $this->assertNotEmpty($setup);

        $this->withToken($setup)->getJson('/api/superadmin/empresas')
            ->assertStatus(423)->assertJson(['two_factor_setup_required' => true]);
        $this->withToken($setup)->getJson('/api/superadmin/dashboard')->assertStatus(423);
    }

    public function test_cadastro_do_2fa_habilita_e_troca_o_token(): void
    {
        $admin = $this->sa();
        $setup = $this->postJson('/api/superadmin/login', ['email' => 'sa@plat.test', 'password' => 'Senha-Super-123'])
            ->json('setup_token');

        $secret = $this->withToken($setup)->postJson('/api/superadmin/2fa/iniciar')->assertOk()->json('secret');

        // Código errado não habilita.
        $this->withToken($setup)->postJson('/api/superadmin/2fa/confirmar', ['otp' => '000000'])->assertStatus(422);
        $this->assertFalse((bool) $admin->fresh()->twofa_habilitado);

        $codigo = (new Totp)->em($secret, (int) floor(time() / 30));
        $resp = $this->withToken($setup)->postJson('/api/superadmin/2fa/confirmar', ['otp' => $codigo])->assertOk();
        $this->assertCount(8, $resp->json('recovery_codes'));
        $this->assertTrue((bool) $admin->fresh()->twofa_habilitado);

        // O token de cadastro morreu; o de operação abre o painel.
        $this->app['auth']->forgetGuards();
        $this->withToken($setup)->getJson('/api/superadmin/me')->assertStatus(401);
        $this->app['auth']->forgetGuards();
        $this->withToken($resp->json('token'))->getJson('/api/superadmin/empresas')->assertOk();

        // Daí em diante, o login exige o código.
        $this->postJson('/api/superadmin/login', ['email' => 'sa@plat.test', 'password' => 'Senha-Super-123'])
            ->assertStatus(423)->assertJson(['two_factor_required' => true])->assertJsonMissingPath('setup_token');
    }

    public function test_iniciar_2fa_recusa_quem_ja_tem_2fa(): void
    {
        $admin = $this->sa(['twofa_habilitado' => true, 'twofa_secret' => (new Totp)->gerarSecret()]);
        $token = $admin->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/superadmin/2fa/iniciar')->assertStatus(409);
    }

    public function test_lockout_por_conta_trava_ate_a_senha_certa(): void
    {
        config(['superadmin.dois_fatores_obrigatorio' => false]);
        $this->sa();

        for ($i = 0; $i < 5; $i++) {
            // IPs diferentes: o throttle por IP não é o que segura aqui.
            $this->withServerVariables(['REMOTE_ADDR' => "10.9.0.{$i}"])
                ->postJson('/api/superadmin/login', ['email' => 'sa@plat.test', 'password' => 'errada'])
                ->assertStatus(401);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.1.1'])
            ->postJson('/api/superadmin/login', ['email' => 'sa@plat.test', 'password' => 'Senha-Super-123'])
            ->assertStatus(429)->assertJsonMissingPath('token');

        // Passada a janela, a senha certa volta a entrar.
        $this->travel(16)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.1.1'])
            ->postJson('/api/superadmin/login', ['email' => 'sa@plat.test', 'password' => 'Senha-Super-123'])
            ->assertOk();
    }

    public function test_admin_da_plataforma_desativado_perde_o_token_na_hora(): void
    {
        $admin = $this->sa();
        $token = $admin->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/superadmin/me')->assertOk();

        $admin->forceFill(['ativo' => false])->save();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/superadmin/empresas')->assertStatus(401);
    }

    // ───────────── PIE-34: validação em português ─────────────

    /**
     * O painel e os apps recebiam a CHAVE ("validation.required"). Vale para as
     * três superfícies, porque o tradutor é um só: painel, plataforma e app.
     */
    public function test_mensagens_de_validacao_saem_em_portugues_no_painel_e_nos_apps(): void
    {
        $this->assertSame('pt_BR', app()->getLocale());

        $painel = $this->postJson('/api/login', ['email' => 'nao-e-email'])->assertStatus(422);
        $this->assertSame('O campo senha é obrigatório.', $painel->json('errors.password.0'));
        $this->assertStringNotContainsString('validation.', $painel->getContent());
        $this->assertStringContainsString('(e mais', $painel->json('message'));

        $plataforma = $this->postJson('/api/superadmin/login', [])->assertStatus(422);
        $this->assertSame('O campo e-mail é obrigatório.', $plataforma->json('errors.email.0'));

        $app = $this->postJson('/api/app/v1/login', [])->assertStatus(422);
        $this->assertStringNotContainsString('validation.', $app->getContent());
        $this->assertStringContainsString('obrigatório', (string) $app->json('message'));
    }

    // ───────────── Teto de quantidade por item (repassado pelo Agente A) ─────────────

    public function test_dono_configura_o_teto_de_quantidade_por_item(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $url = "/api/admin/empresas/{$empresa->id}/config";

        $this->actingAs($user, 'sanctum')->putJson($url, ['pedido_quantidade_maxima_item' => 'abc'])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->putJson($url, ['pedido_quantidade_maxima_item' => 0])->assertStatus(422);

        $this->actingAs($user, 'sanctum')->putJson($url, ['pedido_quantidade_maxima_item' => 50])->assertOk();
        $this->assertSame(50.0, \App\Domain\Pedido\QuantidadeDoItem::teto($empresa->id));
    }

    // ───────────── R3-G-01 / R3-D-01: entregador criado no painel entra no app ─────────────

    /**
     * Jornada inteira pela API: o dono cria o usuário com papel Entregador; o
     * app recusa com a mensagem certa (não "use o app do cliente"); o dono liga
     * o usuário ao colaborador na ficha; o app entra.
     */
    public function test_entregador_criado_no_painel_entra_no_app_depois_de_ligado_ao_colaborador(): void
    {
        $empresa = Empresa::factory()->create();
        $dono = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $papel = \App\Models\Role::query()->create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Entregador']);
        $colab = \App\Models\Rh\Colaborador::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true, 'user_id' => null,
        ]);

        $novo = $this->actingAs($dono, 'sanctum')->postJson('/api/admin/usuarios', [
            'name' => 'João Entregador', 'email' => 'joao@revenda.test',
            'password' => 'Senha-Forte-2026!', 'password_confirmation' => 'Senha-Forte-2026!',
            'papeis' => [$papel->id],
        ])->assertCreated()->assertJsonPath('data.colaborador', null)->json('data.id');
        $this->app['auth']->forgetGuards();

        $recusa = $this->postJson('/api/app/v1/login', ['email' => 'joao@revenda.test', 'password' => 'Senha-Forte-2026!'])
            ->assertStatus(403)->assertJsonPath('motivo', 'sem_colaborador');
        $this->assertStringContainsString('administrador', $recusa->json('message'));
        $this->assertStringNotContainsString('aplicativo do cliente', $recusa->json('message'));

        $this->actingAs($dono, 'sanctum')->putJson("/api/admin/colaboradores/{$colab->id}", [
            'nome' => $colab->nome, 'user_id' => $novo,
        ])->assertOk();
        $this->assertSame($novo, (int) $colab->fresh()->user_id);
        $this->actingAs($dono, 'sanctum')->getJson('/api/admin/usuarios')->assertJsonFragment(['colaborador' => $colab->nome]);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/app/v1/login', ['email' => 'joao@revenda.test', 'password' => 'Senha-Forte-2026!'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_usuario_do_app_nao_liga_a_dois_colaboradores_nem_a_outra_rede(): void
    {
        $empresa = Empresa::factory()->create();
        $dono = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $alvo = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $a = \App\Models\Rh\Colaborador::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true, 'user_id' => $alvo->id]);
        $b = \App\Models\Rh\Colaborador::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true, 'user_id' => null]);
        $outraRede = Empresa::factory()->create();
        $estranho = User::factory()->create(['empresa_id' => $outraRede->id, 'grupo_id' => $outraRede->grupo_id]);

        $this->actingAs($dono, 'sanctum')->putJson("/api/admin/colaboradores/{$b->id}", ['nome' => $b->nome, 'user_id' => $alvo->id])
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->actingAs($dono, 'sanctum')->putJson("/api/admin/colaboradores/{$b->id}", ['nome' => $b->nome, 'user_id' => $estranho->id])
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
        // O próprio colaborador pode ser salvo de novo com o mesmo usuário.
        $this->actingAs($dono, 'sanctum')->putJson("/api/admin/colaboradores/{$a->id}", ['nome' => $a->nome, 'user_id' => $alvo->id])->assertOk();
    }

    // ───────────── R3-F-01 / R3-F-05 (repassados pelo F) ─────────────

    /** Ler a configuração não grava: a linha só nasce no PUT (com a chave de tenant). */
    public function test_ler_configuracao_da_empresa_nao_grava_linha(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        foreach (['config', 'certificado', 'integracoes'] as $rota) {
            $this->actingAs($user, 'sanctum')->getJson("/api/admin/empresas/{$empresa->id}/{$rota}")->assertOk();
        }
        $this->assertDatabaseMissing('empresa_configs', ['empresa_id' => $empresa->id]);

        $this->actingAs($user, 'sanctum')->putJson("/api/admin/empresas/{$empresa->id}/config", ['tempoentrega' => 40])->assertOk();
        $this->assertDatabaseHas('empresa_configs', ['empresa_id' => $empresa->id, 'tempoentrega' => 40]);
    }

    /** Sem permissão, 403 antes de revelar o formato do recurso em 422. */
    public function test_criar_empresa_sem_permissao_e_403_antes_da_validacao(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/empresas', [])
            ->assertStatus(403)->assertJsonMissingPath('errors');
    }

    // ───────────── Token de usuário de revenda desativado ─────────────

    public function test_usuario_da_revenda_desativado_perde_o_token_na_hora(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'ativo' => true]);
        $token = $user->createToken('spa')->plainTextToken;
        $this->withToken($token)->getJson('/api/me')->assertOk();

        // Desativado pelo dono (Central de Acessos grava `ativo = false`).
        $user->forceFill(['ativo' => false])->save();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }
}
