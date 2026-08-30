<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Saas\BreakGlassGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $grant = BreakGlassGrant::create([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'motivo' => 'chamado 4321 — investigar divergência de saldo',
            'inicia_em' => now()->subMinute(),
            'expira_em' => now()->addHour(),
        ]);

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

        $grant = BreakGlassGrant::create([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'motivo' => 'chamado 99',
            'inicia_em' => now()->subMinute(),
            'expira_em' => now()->addHour(),
        ]);
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

        BreakGlassGrant::create([
            'user_id' => $user->id,
            'empresa_id' => $outra->id, // concessão para OUTRA empresa
            'motivo' => 'chamado da outra unidade',
            'inicia_em' => now()->subMinute(),
            'expira_em' => now()->addHour(),
        ]);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/usuarios')
            ->assertForbidden();
    }

    public function test_uso_de_acesso_elevado_deixa_trilha_de_plataforma(): void
    {
        config()->set('saas_transformation.enforcement.tenant_envelope', true);
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);

        BreakGlassGrant::create([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'motivo' => 'chamado 4321',
            'inicia_em' => now()->subMinute(),
            'expira_em' => now()->addHour(),
        ]);

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

    public function test_comando_concede_com_prazo_e_registra_trilha(): void
    {
        $empresa = Empresa::factory()->create();
        $user = $this->suporte($empresa);

        $this->artisan("saas:break-glass:conceder {$user->id} {$empresa->id} --motivo=chamado-7 --minutos=30")
            ->assertExitCode(0);

        $grant = BreakGlassGrant::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('chamado-7', $grant->motivo);
        $this->assertTrue($grant->vigente());

        $this->assertDatabaseHas('platform_audit_logs', ['acao' => 'break_glass.concedido']);

        $this->artisan("saas:break-glass:conceder {$user->id} {$empresa->id} --revogar")
            ->assertExitCode(0);

        $this->assertFalse($grant->fresh()->vigente());
        $this->assertDatabaseHas('platform_audit_logs', ['acao' => 'break_glass.revogado']);
    }
}
