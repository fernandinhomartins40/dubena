<?php

namespace Tests\Feature;

use App\Domain\Cobranca\PixService;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Integracao\IntegracaoTenant;
use App\Domain\Tenant\TenantContext;
use App\Models\ConfigGlobal;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integrações multi-tenant (spec docs/01-vigente/INTEGRACOES_MULTITENANT.md):
 * cada empresa usa as PRÓPRIAS credenciais (PIX/cartão), Maps é por grupo, e o
 * webhook PIX valida o HMAC DA EMPRESA da cobrança — nunca vaza entre empresas.
 */
class IntegracaoTenantTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    // ── API write-only (segredos nunca voltam) ──────────────────────────────────

    public function test_salva_e_le_integracoes_sem_vazar_segredo(): void
    {
        [$user, $empresa] = $this->suporte();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/empresas/{$empresa->id}/integracoes", [
                'pix' => ['psp' => 'itau', 'client_id' => 'CID', 'client_secret' => 'SEGREDO', 'webhook_hmac_secret' => 'HMAC1', 'ambiente' => 'producao'],
                'cartao' => ['gateway' => 'erede', 'pv' => 'PV123', 'token' => 'TOKENX'],
            ])
            ->assertOk()
            // Campos públicos voltam; segredos só como "configurado".
            ->assertJsonPath('data.pix.client_id', 'CID')
            ->assertJsonPath('data.pix.client_secret_configurado', true)
            ->assertJsonPath('data.pix.webhook_hmac_configurado', true)
            ->assertJsonPath('data.cartao.pv', 'PV123')
            ->assertJsonPath('data.cartao.token_configurado', true);

        // O segredo NÃO aparece em lugar nenhum da resposta.
        $resp = $this->actingAs($user, 'sanctum')->getJson("/api/admin/empresas/{$empresa->id}/integracoes")->json();
        $this->assertStringNotContainsString('SEGREDO', json_encode($resp));
        $this->assertStringNotContainsString('TOKENX', json_encode($resp));

        // E também não vaza no /config achatado.
        $cfg = $this->actingAs($user, 'sanctum')->getJson("/api/admin/empresas/{$empresa->id}/config")->json();
        $this->assertStringNotContainsString('SEGREDO', json_encode($cfg));
    }

    public function test_segredo_e_cifrado_em_repouso(): void
    {
        [$user, $empresa] = $this->suporte();
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/empresas/{$empresa->id}/integracoes", [
            'pix' => ['client_secret' => 'CLARO'],
        ])->assertOk();

        $dados = EmpresaConfig::where('empresa_id', $empresa->id)->value('dados');
        $this->assertArrayHasKey('integracoes', $dados);
        // No banco o valor está cifrado (não é a string em claro).
        $this->assertNotSame('CLARO', $dados['integracoes']['pix']['client_secret']);
    }

    public function test_reeditar_sem_reenviar_preserva_segredo(): void
    {
        [$user, $empresa] = $this->suporte();
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/empresas/{$empresa->id}/integracoes", [
            'pix' => ['client_secret' => 'SEGREDO'],
        ])->assertOk();

        // Reedita só o psp, sem reenviar o secret (campo vazio/ausente).
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/empresas/{$empresa->id}/integracoes", [
            'pix' => ['psp' => 'bb', 'client_secret' => ''],
        ])->assertOk()->assertJsonPath('data.pix.psp', 'bb')
            ->assertJsonPath('data.pix.client_secret_configurado', true); // ainda configurado
    }

    // ── Resolver ────────────────────────────────────────────────────────────────

    public function test_resolver_devolve_credencial_da_empresa_ativa(): void
    {
        [, $empresa] = $this->suporte();
        app(EmpresaConfig::class)->newQuery()->create([
            'empresa_id' => $empresa->id,
            'dados' => ['integracoes' => ['cartao' => ['pv' => 'PV-EMP', 'token' => \Illuminate\Support\Facades\Crypt::encryptString('T-EMP')]]],
        ]);

        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        $cred = app(IntegracaoTenant::class)->cartao();

        $this->assertSame('PV-EMP', $cred['pv']);
        $this->assertSame('T-EMP', $cred['token']); // decifrado
    }

    public function test_maps_key_vem_do_grupo(): void
    {
        [, $empresa] = $this->suporte();
        ConfigGlobal::withoutGrupo()->create(['grupo_id' => $empresa->grupo_id, 'google_maps_key' => 'GRUPO-KEY']);

        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        $this->assertSame('GRUPO-KEY', app(IntegracaoTenant::class)->googleMapsKey());
    }

    // ── Webhook PIX valida o HMAC DA EMPRESA ────────────────────────────────────

    public function test_webhook_usa_hmac_da_empresa_da_cobranca(): void
    {
        [, $empresa] = $this->suporte();
        // HMAC próprio da empresa (cifrado no dados).
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        EmpresaConfig::query()->create([
            'empresa_id' => $empresa->id,
            'dados' => ['integracoes' => ['pix' => ['webhook_hmac_secret' => \Illuminate\Support\Facades\Crypt::encryptString('HMAC-EMP')]]],
        ]);

        $parcela = app(FinanceiroService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'pagarreceber' => 'R', 'valor' => 50,
        ])->parcelas->first();
        $cobranca = app(PixService::class)->criarCobranca($parcela);

        $corpo = json_encode(['txid' => $cobranca->txid, 'valor' => 50.0]);
        $assinaturaCerta = hash_hmac('sha256', $corpo, 'HMAC-EMP');

        // Assinatura com o HMAC DA EMPRESA → aceita.
        app(TenantContext::class)->clear();
        $this->call('POST', '/api/pix/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $assinaturaCerta,
        ], $corpo)->assertOk()->assertJsonPath('situacao', 'CONCLUIDA');
    }

    public function test_webhook_rejeita_hmac_de_outra_empresa(): void
    {
        [, $empresa] = $this->suporte();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        EmpresaConfig::query()->create([
            'empresa_id' => $empresa->id,
            'dados' => ['integracoes' => ['pix' => ['webhook_hmac_secret' => \Illuminate\Support\Facades\Crypt::encryptString('HMAC-EMP')]]],
        ]);

        $parcela = app(FinanceiroService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'pagarreceber' => 'R', 'valor' => 50,
        ])->parcelas->first();
        $cobranca = app(PixService::class)->criarCobranca($parcela);

        $corpo = json_encode(['txid' => $cobranca->txid, 'valor' => 50.0]);
        $assinaturaErrada = hash_hmac('sha256', $corpo, 'HMAC-DE-OUTRA-EMPRESA');

        app(TenantContext::class)->clear();
        $this->call('POST', '/api/pix/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $assinaturaErrada,
        ], $corpo)->assertStatus(401);
    }
}
