<?php

namespace Tests\Feature;

use App\Domain\Saas\LicencaService;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Saas\Assinatura;
use App\Models\Saas\Plano;
use App\Models\Saas\RecursoOverride;
use App\Models\User;
use Database\Factories\Support\FronteiraTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P2 — Camada SaaS: planos, assinaturas, feature-flags e o middleware `recurso:`.
 * Cobre a resolução fail-closed de recursos efetivos (plano + overrides), o
 * enforcement 402 por rota e o /me com features.
 */
class LicenciamentoSaasTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
        ]);
        // Resolve o tenant ativo para os Services que dependem dele.
        app(TenantContext::class)->set($this->empresa->id, (int) $this->empresa->grupo_id);
    }

    private function planoCom(string $slug, array $recursos): Plano
    {
        $plano = Plano::query()->create([
            'slug' => $slug, 'nome' => ucfirst($slug), 'preco_mensal' => 100, 'ativo' => true,
        ]);
        foreach ($recursos as $chave) {
            $plano->recursos()->create(['recurso_chave' => $chave]);
        }

        return $plano;
    }

    public function test_empresa_sem_assinatura_nao_tem_recursos(): void
    {
        // A fixture assina toda empresa da fronteira (F2-04); aqui o assunto e
        // justamente a ausencia de contrato.
        FronteiraTenant::semLicenca($this->empresa);
        $licenca = app(LicencaService::class);

        $this->assertFalse($licenca->recursoHabilitado('marketplace'));
        $this->assertSame([], $licenca->recursosEfetivos());
        $this->assertFalse($licenca->assinaturaAtiva());
    }

    public function test_sem_tenant_nao_libera_catalogo_inteiro(): void
    {
        app(TenantContext::class)->clear();

        $this->assertSame([], app(LicencaService::class)->recursosEfetivos());
    }

    public function test_assinatura_vigente_libera_apenas_os_recursos_do_plano(): void
    {
        $plano = $this->planoCom('basico', ['app_consumidor', 'cobranca']);
        Assinatura::query()->create([
            'empresa_id' => $this->empresa->id, 'plano_id' => $plano->id,
            'status' => Assinatura::STATUS_ATIVA, 'inicio' => now()->subDay(),
        ]);

        $licenca = app(LicencaService::class);
        $licenca->invalidar($this->empresa->id);

        $this->assertTrue($licenca->assinaturaAtiva());
        $this->assertTrue($licenca->recursoHabilitado('app_consumidor'));
        $this->assertFalse($licenca->recursoHabilitado('marketplace')); // não está no plano
        $this->assertEqualsCanonicalizing(['app_consumidor', 'cobranca'], $licenca->recursosEfetivos());
    }

    public function test_assinatura_cancelada_nao_libera_recursos(): void
    {
        FronteiraTenant::semLicenca($this->empresa);
        $plano = $this->planoCom('pro', ['marketplace']);
        Assinatura::query()->create([
            'empresa_id' => $this->empresa->id, 'plano_id' => $plano->id,
            'status' => Assinatura::STATUS_CANCELADA,
        ]);

        $licenca = app(LicencaService::class);
        $licenca->invalidar($this->empresa->id);

        // Há assinatura (não é fail-open), mas não vigente → nada liberado.
        $this->assertFalse($licenca->assinaturaAtiva());
        $this->assertFalse($licenca->recursoHabilitado('marketplace'));
        $this->assertSame([], $licenca->recursosEfetivos());
    }

    public function test_override_positivo_pode_liberar_recurso_sem_assinatura(): void
    {
        FronteiraTenant::semLicenca($this->empresa);
        RecursoOverride::query()->create([
            'empresa_id' => $this->empresa->id,
            'recurso_chave' => 'marketplace',
            'habilitado' => true,
        ]);

        $licenca = app(LicencaService::class);
        $licenca->invalidar($this->empresa->id);

        $this->assertSame(['marketplace'], $licenca->recursosEfetivos());
    }

    public function test_override_liga_e_desliga_recurso_sobre_o_plano(): void
    {
        $plano = $this->planoCom('basico', ['app_consumidor', 'cobranca']);
        Assinatura::query()->create([
            'empresa_id' => $this->empresa->id, 'plano_id' => $plano->id,
            'status' => Assinatura::STATUS_ATIVA, 'inicio' => now()->subDay(),
        ]);
        // Cortesia: liga 'marketplace' (fora do plano). Bloqueio: desliga 'cobranca'.
        RecursoOverride::query()->create(['empresa_id' => $this->empresa->id, 'recurso_chave' => 'marketplace', 'habilitado' => true]);
        RecursoOverride::query()->create(['empresa_id' => $this->empresa->id, 'recurso_chave' => 'cobranca', 'habilitado' => false]);

        $licenca = app(LicencaService::class);
        $licenca->invalidar($this->empresa->id);

        $this->assertTrue($licenca->recursoHabilitado('marketplace'));     // override on
        $this->assertTrue($licenca->recursoHabilitado('app_consumidor'));  // do plano
        $this->assertFalse($licenca->recursoHabilitado('cobranca'));       // override off vence o plano
    }

    public function test_middleware_recurso_barra_com_402_quando_nao_licenciado(): void
    {
        // Rota de teste protegida por recurso:marketplace.
        Route::middleware(['auth:sanctum', 'tenant', 'recurso:marketplace'])
            ->get('/api/_teste/marketplace', fn () => response()->json(['ok' => true]));

        $plano = $this->planoCom('basico', ['app_consumidor']); // SEM marketplace
        Assinatura::query()->create([
            'empresa_id' => $this->empresa->id, 'plano_id' => $plano->id,
            'status' => Assinatura::STATUS_ATIVA, 'inicio' => now()->subDay(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/_teste/marketplace')
            ->assertStatus(402);
    }

    public function test_middleware_recurso_passa_quando_licenciado(): void
    {
        Route::middleware(['auth:sanctum', 'tenant', 'recurso:marketplace'])
            ->get('/api/_teste/marketplace-ok', fn () => response()->json(['ok' => true]));

        $plano = $this->planoCom('pro', ['marketplace']);
        Assinatura::query()->create([
            'empresa_id' => $this->empresa->id, 'plano_id' => $plano->id,
            'status' => Assinatura::STATUS_ATIVA, 'inicio' => now()->subDay(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/_teste/marketplace-ok')
            ->assertOk()->assertJson(['ok' => true]);
    }

    public function test_me_inclui_features_da_empresa(): void
    {
        $plano = $this->planoCom('basico', ['app_consumidor', 'nfce']);
        Assinatura::query()->create([
            'empresa_id' => $this->empresa->id, 'plano_id' => $plano->id,
            'status' => Assinatura::STATUS_ATIVA, 'inicio' => now()->subDay(),
        ]);

        $resp = $this->actingAs($this->user, 'sanctum')->getJson('/api/me')->assertOk();
        $features = $resp->json('user.features');
        $this->assertContains('app_consumidor', $features);
        $this->assertContains('nfce', $features);
        $this->assertNotContains('marketplace', $features);
    }

    public function test_endpoint_assinatura_mostra_plano_e_recursos(): void
    {
        $plano = $this->planoCom('pro', ['marketplace', 'crm']);
        Assinatura::query()->create([
            'empresa_id' => $this->empresa->id, 'plano_id' => $plano->id,
            'status' => Assinatura::STATUS_ATIVA, 'inicio' => now()->subDay(),
        ]);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/assinatura')
            ->assertOk()
            ->assertJsonPath('data.assinatura.plano.slug', 'pro')
            ->assertJsonPath('data.assinatura.vigente', true)
            ->assertJsonFragment(['chave' => 'marketplace', 'habilitado' => true]);
    }
}
