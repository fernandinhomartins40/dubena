<?php

namespace Tests\Feature;

use App\Domain\Tenant\AutomacaoPorEmpresa;
use App\Domain\Tenant\IdentidadeDeAutomacao;
use App\Domain\Tenant\TenantContext;
use App\Domain\Tenant\TenantEnvelopeRuntime;
use App\Models\Empresa;
use App\Models\Saas\TenantCompany;
use App\Models\Saas\TenantCompanyGrant;
use App\Models\Saas\TenantMembership;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A identidade com que as rotinas agendadas operam.
 *
 * O agendador não tem usuário, e as policies canônicas só liberam uma linha
 * para um membership com grant na empresa. Medido na homologação em
 * 10/10/2026: como `erp_app` sem contexto, `empresas` devolvia zero linhas, e
 * GPS, missões, PIX, comodato e alertas terminavam com DONE sem ter feito nada.
 *
 * Em sqlite não há RLS: estes testes provam o CONTRATO (quem é processado, com
 * que envelope, o que acontece quando falta identidade). A prova de que o banco
 * de fato entrega as linhas é `AutomacaoSobRlsTest`, que só roda em PostgreSQL.
 */
class AutomacaoPorEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private function automacao(): AutomacaoPorEmpresa
    {
        return app(AutomacaoPorEmpresa::class);
    }

    public function test_provisionar_cria_usuario_que_nao_entra_membership_e_grants(): void
    {
        $empresa = Empresa::factory()->create();
        $filial = Empresa::factory()->create(['grupo_id' => $empresa->grupo_id]);
        $tenant = FronteiraTenant::paraEmpresa($empresa);

        $r = app(IdentidadeDeAutomacao::class)->provisionar($tenant);

        $this->assertTrue($r['usuario_criado']);
        $this->assertTrue($r['membership_criada']);
        $this->assertSame(2, $r['grants_criados']);
        $this->assertEqualsCanonicalizing([$empresa->id, $filial->id], $r['empresas']);

        $user = User::query()->where('email', IdentidadeDeAutomacao::email($tenant->id))->firstOrFail();
        // Não entra: o login exige `ativo`. E não pertence a uma unidade, então
        // não aparece na lista de usuários de nenhuma.
        $this->assertFalse((bool) $user->ativo);
        $this->assertNull($user->empresa_id);
        $this->assertFalse((bool) $user->support);

        $membership = TenantMembership::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(IdentidadeDeAutomacao::PAPEL, $membership->membership_role);
        $this->assertSame(TenantMembership::STATUS_ACTIVE, $membership->status);
    }

    /**
     * O usuário de serviço não entra NEM com a senha certa. Senha errada não
     * provaria nada (qualquer usuário toma 401): o que barra é ele ser inativo,
     * então o teste dá a ele uma senha conhecida e confere que ainda é recusado.
     */
    public function test_usuario_de_servico_nao_faz_login_nem_com_a_senha_certa(): void
    {
        $empresa = Empresa::factory()->create();
        FronteiraTenant::automacao($empresa);
        $email = IdentidadeDeAutomacao::email(FronteiraTenant::paraEmpresa($empresa)->id);
        User::query()->where('email', $email)->update(['password' => Hash::make('senha-conhecida-123')]);

        $this->postJson('/api/login', ['email' => $email, 'password' => 'senha-conhecida-123'])
            ->assertForbidden();

        // Contraprova: o mesmo par e-mail/senha ENTRA se o usuário for ativo —
        // senão o 403 acima poderia vir de outra coisa (rota, payload).
        User::query()->where('email', $email)->update(['ativo' => true]);
        $this->postJson('/api/login', ['email' => $email, 'password' => 'senha-conhecida-123'])
            ->assertSuccessful();
    }

    public function test_provisionar_de_novo_nao_duplica_nada(): void
    {
        $empresa = Empresa::factory()->create();
        $tenant = FronteiraTenant::paraEmpresa($empresa);
        $identidade = app(IdentidadeDeAutomacao::class);

        $identidade->provisionar($tenant);
        $segunda = $identidade->provisionar($tenant);

        $this->assertFalse($segunda['usuario_criado']);
        $this->assertFalse($segunda['membership_criada']);
        $this->assertSame(0, $segunda['grants_criados']);
        $this->assertSame(1, User::query()->where('email', IdentidadeDeAutomacao::email($tenant->id))->count());
        $this->assertSame(1, TenantMembership::query()->where('membership_role', IdentidadeDeAutomacao::PAPEL)->count());
    }

    /** A identidade alcança exatamente a fronteira vigente: nem menos, nem mais. */
    public function test_provisionar_acompanha_a_fronteira(): void
    {
        $empresa = Empresa::factory()->create();
        $tenant = FronteiraTenant::paraEmpresa($empresa);
        $identidade = app(IdentidadeDeAutomacao::class);
        $identidade->provisionar($tenant);

        // Empresa nova no tenant: só é alcançada depois de provisionar de novo.
        $nova = Empresa::factory()->create(['grupo_id' => $empresa->grupo_id]);
        $this->assertArrayHasKey($nova->id, $identidade->empresasDescobertas());
        $this->assertSame(1, $identidade->provisionar($tenant)['grants_criados']);
        $this->assertSame([], $identidade->empresasDescobertas());

        // Empresa que deixou de estar aprovada perde o grant.
        TenantCompany::query()->where('empresa_id', $nova->id)->update(['status' => 'REVOKED']);
        $this->assertSame(1, $identidade->provisionar($tenant)['grants_removidos']);
        $this->assertSame(0, TenantCompanyGrant::query()->where('empresa_id', $nova->id)
            ->whereIn('tenant_membership_id', TenantMembership::query()->where('membership_role', IdentidadeDeAutomacao::PAPEL)->select('id'))
            ->count());
    }

    /** Reativar o usuário à mão abriria login numa conta com grant na rede inteira. */
    public function test_provisionar_devolve_o_usuario_a_inativo(): void
    {
        $empresa = Empresa::factory()->create();
        $tenant = FronteiraTenant::paraEmpresa($empresa);
        $identidade = app(IdentidadeDeAutomacao::class);
        $identidade->provisionar($tenant);

        User::query()->where('email', IdentidadeDeAutomacao::email($tenant->id))->update(['ativo' => true]);
        $identidade->provisionar($tenant);

        $this->assertFalse((bool) User::query()->where('email', IdentidadeDeAutomacao::email($tenant->id))->value('ativo'));
    }

    /**
     * O ponto do defeito: o trabalho roda uma vez por empresa, DENTRO do
     * envelope da identidade de automação — é o que o banco exige para entregar
     * as linhas.
     */
    public function test_trabalho_roda_por_empresa_dentro_do_envelope_da_automacao(): void
    {
        $a = Empresa::factory()->create();
        $b = Empresa::factory()->create(); // outro grupo = outro tenant
        FronteiraTenant::automacao($a);
        FronteiraTenant::automacao($b);

        $visto = [];
        $resultado = $this->automacao()->paraCada(function (int $empresaId) use (&$visto) {
            $envelope = app(TenantEnvelopeRuntime::class)->current();
            $visto[$empresaId] = [
                'tenant' => $envelope?->tenantAccountId,
                'membership' => $envelope?->tenantMembershipId,
                'ativa' => $envelope?->activeEmpresaId,
                'operaveis' => $envelope?->operableEmpresaIds,
                'contexto' => app(TenantContext::class)->empresaId(),
            ];

            return 3;
        });

        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_keys($visto));
        $this->assertSame(2, $resultado->processadas());
        $this->assertSame(6, $resultado->total());
        $this->assertTrue($resultado->limpo());

        foreach ([$a, $b] as $empresa) {
            $esperado = TenantMembership::query()
                ->where('membership_role', IdentidadeDeAutomacao::PAPEL)
                ->where('tenant_account_id', FronteiraTenant::paraEmpresa($empresa)->id)->firstOrFail();

            $this->assertSame($esperado->id, $visto[$empresa->id]['membership']);
            $this->assertSame($esperado->tenant_account_id, $visto[$empresa->id]['tenant']);
            $this->assertSame($empresa->id, $visto[$empresa->id]['ativa']);
            // Só a empresa da vez: o envelope não carrega a rede inteira.
            $this->assertSame([$empresa->id], $visto[$empresa->id]['operaveis']);
            $this->assertSame($empresa->id, $visto[$empresa->id]['contexto']);
        }

        // O tenant de A não é o de B: cada volta usa a identidade do SEU tenant.
        $this->assertNotSame($visto[$a->id]['membership'], $visto[$b->id]['membership']);
    }

    /** Fail-closed: sem identidade a empresa é pulada, nunca processada sem fronteira. */
    public function test_empresa_sem_identidade_e_pulada_e_reportada(): void
    {
        $com = Empresa::factory()->create();
        $sem = Empresa::factory()->create();
        FronteiraTenant::automacao($com);

        $processadas = [];
        $resultado = $this->automacao()->paraCada(function (int $empresaId) use (&$processadas) {
            $processadas[] = $empresaId;
        });

        $this->assertSame([$com->id], $processadas);
        $this->assertSame([$sem->id], $resultado->semIdentidade);
        $this->assertFalse($resultado->limpo());
    }

    /** Empresa fora da fronteira SaaS não é alcançada pela automação. */
    public function test_empresa_sem_vinculo_de_tenant_aprovado_nao_entra(): void
    {
        $dentro = Empresa::factory()->create();
        FronteiraTenant::automacao($dentro);
        $fora = Empresa::factory()->semFronteiraSaas()->create();

        $processadas = [];
        $resultado = $this->automacao()->paraCada(function (int $empresaId) use (&$processadas) {
            $processadas[] = $empresaId;
        });

        $this->assertSame([$dentro->id], $processadas);
        // Nem como "sem identidade": ela não é assunto da automação.
        $this->assertNotContains($fora->id, $resultado->semIdentidade);
    }

    public function test_empresa_inativa_fica_de_fora_a_menos_que_se_peca(): void
    {
        $ativa = Empresa::factory()->create();
        $inativa = Empresa::factory()->create(['ativo' => false]);
        FronteiraTenant::automacao($ativa);
        FronteiraTenant::automacao($inativa);

        $padrao = $this->automacao()->paraCada(fn () => 1);
        $todas = $this->automacao()->paraCada(fn () => 1, soAtivas: false);

        $this->assertSame([$ativa->id], array_keys($padrao->retornos));
        $this->assertEqualsCanonicalizing([$ativa->id, $inativa->id], array_keys($todas->retornos));
    }

    /**
     * A falha de uma revenda não pode parar a rotina das outras — e o contexto
     * dela não pode vazar para a seguinte.
     */
    public function test_falha_em_uma_empresa_nao_para_as_outras_nem_vaza_contexto(): void
    {
        $a = Empresa::factory()->create();
        $b = Empresa::factory()->create();
        FronteiraTenant::automacao($a);
        FronteiraTenant::automacao($b);

        $contextoDeB = null;
        $resultado = $this->automacao()->paraCada(function (int $empresaId) use ($a, &$contextoDeB) {
            if ($empresaId === $a->id) {
                throw new \RuntimeException('cadastro quebrado');
            }
            $contextoDeB = app(TenantEnvelopeRuntime::class)->current()?->activeEmpresaId;

            return 1;
        });

        $this->assertSame([$a->id => 'cadastro quebrado'], $resultado->falhas);
        $this->assertSame([$b->id], array_keys($resultado->retornos));
        $this->assertSame($b->id, $contextoDeB);

        // Depois da passada não sobra envelope nem contexto.
        $this->assertNull(app(TenantEnvelopeRuntime::class)->current());
        $this->assertNull(app(TenantContext::class)->empresaId());
    }

    public function test_comando_de_provisionamento_e_idempotente_e_deixa_rastro(): void
    {
        $empresa = Empresa::factory()->create();
        $tenant = FronteiraTenant::paraEmpresa($empresa);

        $this->artisan('saas:automacao:provisionar', ['--dry-run' => true])
            ->expectsOutputToContain('dry-run: nada foi gravado')->assertSuccessful();
        $this->assertSame(0, TenantMembership::query()->where('membership_role', IdentidadeDeAutomacao::PAPEL)->count());

        $this->artisan('saas:automacao:provisionar')->expectsOutputToContain('identidade CRIADA')->assertSuccessful();
        $this->artisan('saas:automacao:provisionar')->expectsOutputToContain('identidade já existia')->assertSuccessful();

        // Uma linha de trilha: a segunda execução não mudou nada.
        $this->assertSame(1, DB::table('platform_audit_logs')
            ->where('acao', 'automacao.provisionada')->where('entidade_id', $tenant->id)->count());
    }

    /** O `golive:check` é onde a empresa sem identidade aparece. */
    public function test_golive_check_acusa_empresa_sem_identidade(): void
    {
        $empresa = Empresa::factory()->create();

        $this->artisan('golive:check')->expectsOutputToContain('sem identidade');

        FronteiraTenant::automacao($empresa);

        $this->artisan('golive:check')->doesntExpectOutputToContain('sem identidade');
    }
}
