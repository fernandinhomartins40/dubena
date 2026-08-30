<?php

namespace Tests\Feature;

use App\Domain\Seguranca\Totp;
use App\Domain\Seguranca\VerificadorDoisFatores;
use App\Models\Empresa;
use App\Models\Saas\BreakGlassGrant;
use App\Models\Saas\PlatformAdmin;
use App\Models\User;
use App\Models\User2fa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * F2-05 — `support` deixa de ser bypass permanente.
 *
 * Antes disto o flag respondia "pode tudo" em quatro camadas independentes
 * (`Gate::before`, `PolicyEvaluator`, `podeAcessarEmpresa`, `empresasVisiveis`),
 * para sempre e sem trilha. Medido na cópia real: 12 usuários ativos assim, e
 * `platform_audit_logs` zerado.
 *
 * Numa revenda só isso é "o pessoal do suporte". Num SaaS com revendas
 * concorrentes é acesso irrestrito e invisível ao dado de todas elas.
 */
class BreakGlassTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(Empresa $empresa): User
    {
        // `semPapel`: sem isto o usuário teria o papel administrador da factory
        // e o teste mediria o RBAC comum, não o acesso elevado.
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        // `support` não é fillable por decisão de segurança (T1.8).
        $user->forceFill(['support' => true])->save();

        return $user->fresh();
    }

    /**
     * Concessão já com 2FA conferido e (quando OPERACAO) aprovada.
     *
     * Os testes de autorização medem o acesso, não o rito de concessão — este
     * último tem testes próprios abaixo.
     */
    private function concessao(User $user, Empresa $empresa, string $escopo = BreakGlassGrant::ESCOPO_LEITURA): BreakGlassGrant
    {
        return BreakGlassGrant::create([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'escopo' => $escopo,
            'motivo' => 'chamado 4321 — investigar divergência',
            'twofa_verificado_em' => now(),
            'aprovado_em' => $escopo === BreakGlassGrant::ESCOPO_OPERACAO ? now() : null,
            'inicia_em' => now()->subMinute(),
            'expira_em' => now()->addHour(),
        ]);
    }

    /** Habilita TOTP no usuário e devolve o código válido do momento. */
    private function otp(User $user): string
    {
        $totp = app(Totp::class);
        $secret = $totp->gerarSecret();
        User2fa::create([
            'user_id' => $user->id,
            'secret' => $secret,
            'habilitado' => true,
            'confirmado_em' => now(),
            'recovery_codes' => [],
        ]);

        return $totp->em($secret, (int) floor(time() / 30));
    }

    public function test_support_sozinho_nao_autoriza_mais_nada(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/usuarios')
            ->assertForbidden();
    }

    public function test_concessao_vigente_autoriza_e_expiracao_encerra(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);

        $grant = $this->concessao($user, $empresa);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/usuarios')
            ->assertOk();

        // O acesso termina sozinho: é isso que o flag permanente nunca fez.
        $grant->update(['expira_em' => now()->subMinute()]);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/usuarios')
            ->assertForbidden();
    }

    public function test_concessao_revogada_encerra_antes_do_prazo(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);

        $grant = $this->concessao($user, $empresa);
        $grant->update(['revogado_em' => now(), 'revogado_motivo' => 'encerrado']);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/usuarios')
            ->assertForbidden();
    }

    public function test_concessao_vale_somente_na_empresa_declarada(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);
        $empresa = Empresa::factory()->create();
        $outra = Empresa::factory()->create(['grupo_id' => $empresa->grupo_id]);
        $user = $this->suporte($empresa);

        $this->concessao($user, $outra); // concessão para OUTRA empresa

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/usuarios')
            ->assertForbidden();
    }

    public function test_uso_de_acesso_elevado_deixa_trilha_de_plataforma(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);

        $this->concessao($user, $empresa);

        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/admin/usuarios');

        $trilha = DB::table('platform_audit_logs')->where('acao', 'break_glass.usado')->get();

        // Uma entrada por par usuário/empresa no ciclo: as quatro camadas
        // perguntam a mesma coisa várias vezes, e a trilha não pode virar ruído.
        $this->assertCount(1, $trilha);
        $this->assertSame($empresa->id, (int) $trilha->first()->empresa_id);
        $this->assertSame($user->id, (int) $trilha->first()->entidade_id);
    }

    public function test_modo_legado_preserva_o_bypass(): void
    {
        // Sem o enforcement, a operação atual não pode ser derrubada: o flag
        // continua valendo por si até o cutover.
        config()->set('saas_transformation.enforcement.tenant_envelope', false);
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/usuarios')
            ->assertOk();
    }

    public function test_comando_exige_motivo_e_flag_de_suporte(): void
    {
        $empresa = Empresa::factory()->create();
        $comum = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        $this->artisan("saas:break-glass:conceder {$comum->id} {$empresa->id} --motivo=teste")
            ->expectsOutputToContain('nao tem o flag de suporte')
            ->assertExitCode(1);

        $suporte = $this->suporte($empresa);
        $this->artisan("saas:break-glass:conceder {$suporte->id} {$empresa->id}")
            ->expectsOutputToContain('motivo e obrigatorio')
            ->assertExitCode(1);
    }

    public function test_comando_exige_2fa_para_conceder(): void
    {
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);

        // Sem `--otp`: recusa antes de criar qualquer coisa.
        $this->artisan("saas:break-glass:conceder {$user->id} {$empresa->id} --motivo=chamado-1")
            ->expectsOutputToContain('2FA (--otp) e obrigatorio')
            ->assertExitCode(1);

        // Com código inválido: idem.
        $this->otp($user);
        $this->artisan("saas:break-glass:conceder {$user->id} {$empresa->id} --motivo=chamado-1 --otp=000000")
            ->expectsOutputToContain('2FA invalido')
            ->assertExitCode(1);

        $this->assertSame(0, BreakGlassGrant::query()->count());
    }

    public function test_comando_concede_com_prazo_e_registra_trilha(): void
    {
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);
        $otp = $this->otp($user);

        $this->artisan("saas:break-glass:conceder {$user->id} {$empresa->id} --motivo=chamado-7 --minutos=30 --otp={$otp}")
            ->assertExitCode(0);

        $grant = BreakGlassGrant::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('chamado-7', $grant->motivo);
        $this->assertSame(BreakGlassGrant::ESCOPO_LEITURA, $grant->escopo);
        $this->assertNotNull($grant->twofa_verificado_em);
        $this->assertTrue($grant->vigente());

        $this->assertDatabaseHas('platform_audit_logs', ['acao' => 'break_glass.concedido']);

        $this->artisan("saas:break-glass:conceder {$user->id} {$empresa->id} --revogar")
            ->assertExitCode(0);

        $this->assertFalse($grant->fresh()->vigente());
        $this->assertDatabaseHas('platform_audit_logs', ['acao' => 'break_glass.revogado']);
    }

    /**
     * Olhar um cadastro e mexer em dinheiro não podem custar o mesmo: escopo
     * OPERACAO nasce inerte e só passa a valer com a segunda assinatura.
     */
    public function test_escopo_operacao_so_vale_apos_aprovacao(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);
        $otp = $this->otp($user);

        $this->artisan("saas:break-glass:conceder {$user->id} {$empresa->id} --motivo=chamado-9 --escopo=OPERACAO --otp={$otp}")
            ->expectsOutputToContain('PENDENTE DE APROVACAO')
            ->assertExitCode(0);

        $grant = BreakGlassGrant::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertFalse($grant->vigente(), 'OPERACAO sem aprovação não pode autorizar');

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/usuarios')
            ->assertForbidden();

        $admin = PlatformAdmin::create([
            'nome' => 'Aprovador',
            'email' => 'aprovador@plataforma.test',
            'password' => Hash::make('segredo123'),
            'ativo' => true,
        ]);

        $this->artisan("saas:break-glass:aprovar {$grant->id} --admin={$admin->id}")->assertExitCode(0);

        $this->assertTrue($grant->fresh()->vigente());
        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/usuarios')
            ->assertOk();

        $this->assertDatabaseHas('platform_audit_logs', ['acao' => 'break_glass.aprovado']);
    }

    public function test_quem_concedeu_nao_aprova(): void
    {
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);
        $admin = PlatformAdmin::create([
            'nome' => 'Pediu',
            'email' => 'pediu@plataforma.test',
            'password' => Hash::make('segredo123'),
            'ativo' => true,
        ]);

        $grant = $this->concessao($user, $empresa, BreakGlassGrant::ESCOPO_OPERACAO);
        $grant->update(['aprovado_em' => null, 'concedido_por_platform_admin_id' => $admin->id]);

        $this->artisan("saas:break-glass:aprovar {$grant->id} --admin={$admin->id}")
            ->expectsOutputToContain('diferente de quem concedeu')
            ->assertExitCode(1);

        $this->assertNull($grant->fresh()->aprovado_em);
    }

    /**
     * F2-07: `Totp::verificar` aceita janela de ±1 passo, então o mesmo código
     * de 6 dígitos vale por ~90 segundos. Sem consumo, era reapresentável.
     */
    public function test_mesmo_otp_nao_pode_ser_reapresentado(): void
    {
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);
        $otp = $this->otp($user);

        $verificador = app(VerificadorDoisFatores::class);
        $this->assertTrue($verificador->verificar($user->twoFactor, $otp), 'primeiro uso vale');
        $this->assertFalse($verificador->verificar($user->twoFactor->fresh(), $otp), 'replay tem de ser negado');
    }
}
