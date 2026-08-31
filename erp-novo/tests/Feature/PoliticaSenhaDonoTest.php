<?php

namespace Tests\Feature;

use App\Domain\Seguranca\PasswordPolicyService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\PasswordPolicy;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * F2-07 — a política de senha tem de ter o dono certo.
 *
 * Hoje ela é por EMPRESA (`password_policies.empresa_id`), mas a senha pertence
 * ao USUÁRIO — e um usuário pode operar várias empresas do mesmo tenant.
 *
 * O defeito que isso produz é concreto: um gerente que atende as filiais A
 * (mínimo 12, com complexidade) e B (mínimo 8) troca a senha com a filial B
 * ativa e passa com 8 caracteres. A senha que ele acabou de enfraquecer é a
 * mesma que abre a filial A. A política mais rígida do tenant foi contornada
 * escolhendo por qual porta entrar.
 *
 * A correção não é mudar o dono da tabela — a empresa continua podendo declarar
 * a sua exigência, e isso tem valor. É fazer a regra aplicada ao usuário ser a
 * MAIS RÍGIDA entre as empresas que ele alcança: uma credencial só é tão forte
 * quanto a porta mais exigente que ela abre.
 */
class PoliticaSenhaDonoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Empresa, Empresa} usuário com acesso às duas filiais */
    private function gerenteDeDuasFiliais(): array
    {
        $rigida = Empresa::factory()->create();
        // Mesmo grupo = mesmo tenant: é o cenário da rede de filiais.
        $frouxa = Empresa::factory()->create(['grupo_id' => $rigida->grupo_id]);

        PasswordPolicy::query()->create([
            'empresa_id' => $rigida->id, 'min_len' => 12, 'exige_complexidade' => true, 'expira_dias' => 0,
        ]);
        PasswordPolicy::query()->create([
            'empresa_id' => $frouxa->id, 'min_len' => 8, 'exige_complexidade' => false, 'expira_dias' => 0,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $frouxa->id, 'grupo_id' => $rigida->grupo_id,
        ]);
        $user->empresas()->syncWithoutDetaching([$rigida->id, $frouxa->id]);
        FronteiraTenant::sincronizarVinculosLegados($user);

        return [$user, $rigida, $frouxa];
    }

    private function senhaPassa(string $senha): bool
    {
        return ! Validator::make(
            ['password' => $senha],
            ['password' => app(PasswordPolicyService::class)->regra()],
        )->fails();
    }

    /**
     * O buraco: com a filial frouxa ativa, a senha fraca passaria — e ela abre
     * também a filial que exige 12 com complexidade.
     */
    public function test_politica_frouxa_da_empresa_ativa_nao_enfraquece_a_credencial(): void
    {
        [$user, , $frouxa] = $this->gerenteDeDuasFiliais();

        $this->actingAs($user, 'sanctum');
        app(TenantContext::class)->set($frouxa->id, $frouxa->grupo_id);

        $this->assertFalse(
            $this->senhaPassa('senha123'),
            'senha de 8 sem complexidade abriria a filial que exige 12 com complexidade',
        );
    }

    /** A regra mais rígida do tenant é a que vale, esteja qual filial estiver ativa. */
    public function test_regra_aplicada_e_a_mais_rigida_entre_as_empresas_do_usuario(): void
    {
        [$user, , $frouxa] = $this->gerenteDeDuasFiliais();

        $this->actingAs($user, 'sanctum');
        app(TenantContext::class)->set($frouxa->id, $frouxa->grupo_id);

        $this->assertTrue(
            $this->senhaPassa('SenhaForte123'),
            'senha que satisfaz a política mais rígida tem de passar',
        );
    }

    /** Quem alcança uma empresa só continua sujeito à política dela — sem inflação. */
    public function test_usuario_de_uma_empresa_so_segue_a_politica_dela(): void
    {
        $empresa = Empresa::factory()->create();
        PasswordPolicy::query()->create([
            'empresa_id' => $empresa->id, 'min_len' => 8, 'exige_complexidade' => false, 'expira_dias' => 0,
        ]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum');
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $this->assertTrue($this->senhaPassa('senha123'));
    }

    /**
     * Empresa sem política declarada não pode BAIXAR a régua do tenant: o
     * default é um piso, não um teto.
     */
    public function test_empresa_sem_politica_nao_derruba_a_regra_do_tenant(): void
    {
        $rigida = Empresa::factory()->create();
        $semPolitica = Empresa::factory()->create(['grupo_id' => $rigida->grupo_id]);

        PasswordPolicy::query()->create([
            'empresa_id' => $rigida->id, 'min_len' => 14, 'exige_complexidade' => true, 'expira_dias' => 0,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $semPolitica->id, 'grupo_id' => $rigida->grupo_id,
        ]);
        $user->empresas()->syncWithoutDetaching([$rigida->id, $semPolitica->id]);
        FronteiraTenant::sincronizarVinculosLegados($user);

        $this->actingAs($user, 'sanctum');
        app(TenantContext::class)->set($semPolitica->id, $semPolitica->grupo_id);

        $this->assertFalse($this->senhaPassa('SenhaForte123'), 'a exigência de 14 continua valendo');
        $this->assertTrue($this->senhaPassa('SenhaMuitoForte123'));
    }

    /**
     * O contraponto: a leitura sem escopo de tenant não pode virar uma janela
     * para o banco inteiro. Política de empresa de OUTRO tenant é irrelevante
     * para esta pessoa e não pode endurecer nem afrouxar a regra dela.
     */
    public function test_politica_de_outro_tenant_nao_influencia(): void
    {
        $empresa = Empresa::factory()->create();
        PasswordPolicy::query()->create([
            'empresa_id' => $empresa->id, 'min_len' => 8, 'exige_complexidade' => false, 'expira_dias' => 0,
        ]);

        // Outro tenant, exigência altíssima: não pode alcançar quem não é dele.
        $alheia = Empresa::factory()->create();
        PasswordPolicy::withoutTenant()->create([
            'empresa_id' => $alheia->id, 'min_len' => 32, 'exige_complexidade' => true, 'expira_dias' => 1,
        ]);

        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $this->actingAs($user, 'sanctum');
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        $this->assertTrue($this->senhaPassa('senha123'), 'a régua de outro tenant não alcança este usuário');
        $this->assertSame(0, app(PasswordPolicyService::class)->politicaAtiva()['expira_dias']);
    }

    /** `expira_dias` também é do usuário: vale o prazo mais curto que o alcança. */
    public function test_expiracao_efetiva_e_a_mais_curta_entre_as_empresas(): void
    {
        [$user, $rigida, $frouxa] = $this->gerenteDeDuasFiliais();
        PasswordPolicy::query()->where('empresa_id', $rigida->id)->update(['expira_dias' => 30]);
        PasswordPolicy::query()->where('empresa_id', $frouxa->id)->update(['expira_dias' => 90]);

        $this->actingAs($user, 'sanctum');
        app(TenantContext::class)->set($frouxa->id, $frouxa->grupo_id);

        $this->assertSame(30, app(PasswordPolicyService::class)->politicaAtiva()['expira_dias']);
    }

    /** Prazo 0 significa "nunca expira" e não pode vencer um prazo real. */
    public function test_expiracao_zero_nao_vence_um_prazo_declarado(): void
    {
        [$user, $rigida, $frouxa] = $this->gerenteDeDuasFiliais();
        PasswordPolicy::query()->where('empresa_id', $rigida->id)->update(['expira_dias' => 45]);
        PasswordPolicy::query()->where('empresa_id', $frouxa->id)->update(['expira_dias' => 0]);

        $this->actingAs($user, 'sanctum');
        app(TenantContext::class)->set($frouxa->id, $frouxa->grupo_id);

        $this->assertSame(
            45,
            app(PasswordPolicyService::class)->politicaAtiva()['expira_dias'],
            '0 é "nunca", não "menor que tudo"',
        );
    }
}
