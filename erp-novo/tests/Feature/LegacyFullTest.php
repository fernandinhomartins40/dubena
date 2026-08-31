<?php

namespace Tests\Feature;

use App\Domain\Saas\LicencaService;
use App\Domain\Saas\RecursoCatalogo;
use App\Domain\Saas\SuperAdminService;
use App\Models\Empresa;
use App\Models\Saas\Assinatura;
use App\Models\Saas\Plano;
use App\Models\Saas\TenantAccount;
use App\Models\Saas\TenantCompany;
use Database\Factories\Support\FronteiraTenant;
use Database\Seeders\PlanosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F2-04 — `Legacy Full` conserva o acesso de quem já operava, sem transformar
 * "ausência de assinatura" em regra do produto.
 *
 * A ordem é a coisa toda: `LicencaService` é fail-closed, então no instante em
 * que `SAAS_ENFORCE_LICENCA` for ligado, empresa sem assinatura perde TODOS os
 * módulos. Assinar quem já opera vem antes de ligar o enforcement.
 *
 * O risco oposto, e mais silencioso, é o plano de transição virar permanente:
 * um plano de R$ 0,00 com o catálogo inteiro é o melhor negócio da grade, e sem
 * barreiras ninguém migra. Por isso ele não é vendável, não se atribui pelo
 * painel e é contado por um relatório que cobra a migração.
 */
class LegacyFullTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{TenantAccount, Empresa} empresa dentro da fronteira, sem assinatura */
    private function empresaNaFronteira(): array
    {
        $empresa = Empresa::factory()->create();
        $tenant = TenantAccount::query()->create([
            'legal_name' => 'Revenda '.$empresa->id,
            'status' => 'active',
        ]);
        // updateOrCreate: `empresa_id` é unique — uma empresa pertence a um
        // tenant só, e o ambiente de teste pode já ter semeado o vínculo.
        TenantCompany::query()->updateOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'tenant_account_id' => $tenant->id,
                'status' => TenantCompany::STATUS_APPROVED,
                'approved_at' => now(),
                'ownership_evidence_ref' => 'teste:f2-04',
            ],
        );

        // A fixture assina toda empresa da fronteira (F2-04). Este arquivo
        // testa justamente o comando que CRIA essa assinatura, então o cenário
        // precisa partir do estado anterior a ele: dentro da fronteira, fora da
        // licença.
        FronteiraTenant::semLicenca($empresa);
        Assinatura::withoutTenant()->where('empresa_id', $empresa->id)->delete();

        return [$tenant, $empresa];
    }

    private function semearPlanos(): void
    {
        $this->seed(PlanosSeeder::class);
    }

    public function test_seeder_cria_o_plano_de_transicao_fora_da_grade_vendavel(): void
    {
        $this->semearPlanos();

        $legado = Plano::query()->where('slug', Plano::SLUG_LEGADO)->firstOrFail();

        $this->assertTrue($legado->transitorio, 'transição não é oferta');
        $this->assertTrue($legado->ativo, 'precisa estar ativo, senão a assinatura deixa de valer');
        $this->assertNotContains(
            Plano::SLUG_LEGADO,
            Plano::query()->vendaveis()->pluck('slug')->all(),
            'o plano de transição não aparece entre os vendáveis',
        );
    }

    /** "Full" tem de ser o catálogo inteiro: estreitar quem já roda quebra a operação. */
    public function test_plano_de_transicao_carrega_o_catalogo_inteiro_e_nenhum_teto(): void
    {
        $this->semearPlanos();

        $legado = Plano::query()->where('slug', Plano::SLUG_LEGADO)->firstOrFail();

        $this->assertEqualsCanonicalizing(
            RecursoCatalogo::chaves(),
            $legado->chavesDeRecurso(),
        );
        $this->assertSame(0, $legado->limites()->count(), 'sem teto: transição não estreita quem já opera');
    }

    public function test_comando_assina_quem_esta_na_fronteira_e_sem_assinatura(): void
    {
        $this->semearPlanos();
        [, $empresa] = $this->empresaNaFronteira();

        $this->artisan('saas:legacy-full --force')->assertSuccessful();

        $assinatura = Assinatura::withoutTenant()->where('empresa_id', $empresa->id)->firstOrFail();

        $this->assertSame(Assinatura::STATUS_ATIVA, $assinatura->status);
        $this->assertNull($assinatura->fim, 'prazo aqui desligaria a operação numa data que ninguém lembraria');
        $this->assertSame(
            Plano::SLUG_LEGADO,
            Plano::query()->find($assinatura->plano_id)?->slug,
        );
    }

    /** O ponto da tarefa: depois do comando, o enforcement não tira módulo de ninguém. */
    public function test_depois_da_transicao_a_empresa_mantem_os_modulos_com_enforcement_ligado(): void
    {
        $this->semearPlanos();
        [, $empresa] = $this->empresaNaFronteira();

        $licenca = app(LicencaService::class);

        $this->assertSame([], $licenca->recursosEfetivos($empresa->id), 'fail-closed antes de assinar');

        $this->artisan('saas:legacy-full --force')->assertSuccessful();
        $licenca->invalidar($empresa->id);

        $this->assertNotEmpty(
            $licenca->recursosEfetivos($empresa->id),
            'quem já operava não pode perder os módulos ao ligar o enforcement',
        );
    }

    /** Empresa já assinante não pode ser rebaixada por um comando de manutenção. */
    public function test_comando_nao_toca_em_quem_ja_tem_assinatura(): void
    {
        $this->semearPlanos();
        [, $empresa] = $this->empresaNaFronteira();

        $essencial = Plano::query()->where('slug', 'essencial')->firstOrFail();
        app(SuperAdminService::class)->definirAssinatura($empresa->id, $essencial->id);

        $this->artisan('saas:legacy-full --force')->assertSuccessful();

        $this->assertSame(
            $essencial->id,
            Assinatura::withoutTenant()->where('empresa_id', $empresa->id)
                ->whereIn('status', [Assinatura::STATUS_ATIVA, Assinatura::STATUS_TRIAL])
                ->value('plano_id'),
        );
    }

    /**
     * Empresa fora da fronteira o resolver nega de qualquer forma — assiná-la
     * criaria licença para algo inalcançável.
     *
     * O vínculo é removido à mão porque a `EmpresaFactory` já cria um aprovado:
     * não existe "empresa solta" por factory neste projeto.
     */
    public function test_comando_ignora_empresa_sem_vinculo_aprovado(): void
    {
        $this->semearPlanos();
        $solta = Empresa::factory()->create();
        TenantCompany::query()->where('empresa_id', $solta->id)->delete();
        Assinatura::withoutTenant()->where('empresa_id', $solta->id)->delete();

        $this->artisan('saas:legacy-full --force')->assertSuccessful();

        $this->assertSame(0, Assinatura::withoutTenant()->where('empresa_id', $solta->id)->count());
    }

    /** Vínculo PENDENTE também está fora: aprovação é o que define a fronteira. */
    public function test_comando_ignora_vinculo_nao_aprovado(): void
    {
        $this->semearPlanos();
        $empresa = Empresa::factory()->create();
        TenantCompany::query()->where('empresa_id', $empresa->id)
            ->update(['status' => TenantCompany::STATUS_PENDING_OWNERSHIP]);
        Assinatura::withoutTenant()->where('empresa_id', $empresa->id)->delete();

        $this->artisan('saas:legacy-full --force')->assertSuccessful();

        $this->assertSame(0, Assinatura::withoutTenant()->where('empresa_id', $empresa->id)->count());
    }

    public function test_dry_run_nao_grava(): void
    {
        $this->semearPlanos();
        [, $empresa] = $this->empresaNaFronteira();

        $this->artisan('saas:legacy-full --dry-run')->assertSuccessful();

        $this->assertSame(0, Assinatura::withoutTenant()->where('empresa_id', $empresa->id)->count());
    }

    /**
     * A porta pela qual "transição" viraria "plano gratuito com tudo incluso":
     * um clique no painel resolve a reclamação de hoje e ninguém migra.
     */
    public function test_painel_nao_atribui_plano_de_transicao(): void
    {
        $this->semearPlanos();
        [, $empresa] = $this->empresaNaFronteira();
        $legado = Plano::query()->where('slug', Plano::SLUG_LEGADO)->firstOrFail();

        $this->expectException(ValidationException::class);
        app(SuperAdminService::class)->definirAssinatura($empresa->id, $legado->id);
    }

    /** Quem já está no plano pode permanecer — a restrição é sobre ENTRAR. */
    public function test_quem_ja_esta_na_transicao_pode_permanecer(): void
    {
        $this->semearPlanos();
        [, $empresa] = $this->empresaNaFronteira();
        $legado = Plano::query()->where('slug', Plano::SLUG_LEGADO)->firstOrFail();

        $this->artisan('saas:legacy-full --force')->assertSuccessful();

        // Reafirmar o mesmo plano (ex.: mudar a vigência) não pode ser recusado.
        $assinatura = app(SuperAdminService::class)
            ->definirAssinatura($empresa->id, $legado->id, ['status' => Assinatura::STATUS_ATIVA]);

        $this->assertSame($legado->id, $assinatura->plano_id);
    }

    /**
     * O slug identifica o plano para o comando e para o relatório. Renomeá-lo
     * deixaria os dois cegos, sem erro visível.
     */
    public function test_slug_do_plano_de_transicao_nao_pode_ser_renomeado(): void
    {
        $this->semearPlanos();
        $legado = Plano::query()->where('slug', Plano::SLUG_LEGADO)->firstOrFail();

        $this->expectException(ValidationException::class);
        app(SuperAdminService::class)->salvarPlano(
            ['slug' => 'outro-nome', 'nome' => $legado->nome, 'preco_mensal' => 0],
            [],
            $legado->id,
        );
    }

    /** O relatório é o que impede o transitório de virar permanente. */
    public function test_status_reprova_enquanto_houver_empresa_descoberta(): void
    {
        $this->semearPlanos();
        $this->empresaNaFronteira();

        $this->artisan('saas:licenca:status')->assertFailed();

        $this->artisan('saas:legacy-full --force')->assertSuccessful();

        $this->artisan('saas:licenca:status')
            ->expectsOutputToContain('Ainda no plano de TRANSIÇÃO')
            ->assertSuccessful();
    }
}
