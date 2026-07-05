<?php

namespace Tests\Feature;

use App\Domain\Cobranca\PixService;
use App\Domain\Cobranca\SituacaoPix;
use App\Domain\Financeiro\FinanceiroService;
use App\Domain\Tenant\TenantContext;
use App\Models\Cobranca\PixCobranca;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * FASE 1 do PLANO_SEGURANCA_MULTITENANT_APPS — webhook PIX FAIL-CLOSED em produção.
 *
 * Ameaça coberta: o cliente gera o PIX do próprio pedido (conhece txid e valor) e
 * chama o webhook público ele mesmo → pedido "pago" sem pagamento. Em produção,
 * NENHUM segredo verificável = 401 sempre; empresa com PIX próprio não herda o
 * HMAC do env. Em dev/homolog/CI o comportamento permissivo é preservado.
 */
class PixWebhookFailClosedTest extends TestCase
{
    use RefreshDatabase;

    /** Cria empresa + cobrança PIX ativa e devolve [empresa, cobranca]. */
    private function cobranca(): array
    {
        $empresa = Empresa::factory()->create();
        User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);
        $parcela = app(FinanceiroService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'pagarreceber' => 'R', 'valor' => 80,
        ])->parcelas->first();
        $cobranca = app(PixService::class)->criarCobranca($parcela);
        app(TenantContext::class)->clear();

        return [$empresa, $cobranca];
    }

    /** POST cru no webhook (corpo controlado byte a byte p/ HMAC). */
    private function postWebhook(string $corpo, array $headers = [])
    {
        return $this->call('POST', '/api/pix/webhook', [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $headers), $corpo);
    }

    private function simularProducao(): void
    {
        $this->app['env'] = 'production';
    }

    // ── Produção: fail-closed ───────────────────────────────────────────────────

    public function test_producao_sem_nenhum_segredo_rejeita_auto_confirmacao(): void
    {
        [, $cobranca] = $this->cobranca();
        config(['services.pix.webhook_secret' => null, 'services.pix.webhook_hmac_secret' => null]);
        $this->simularProducao();

        // O "atacante" conhece txid e valor da PRÓPRIA cobrança — sem segredo nenhum
        // configurado, isso bastaria para marcar como paga. Fail-closed: 401.
        $this->postWebhook(json_encode(['txid' => $cobranca->txid, 'valor' => 80.0]))
            ->assertStatus(401);

        $this->assertSame(
            SituacaoPix::ATIVA,
            PixCobranca::withoutTenant()->find($cobranca->id)->situacao,
        );
    }

    public function test_producao_txid_desconhecido_rejeitado_antes_de_processar(): void
    {
        config(['services.pix.webhook_secret' => null, 'services.pix.webhook_hmac_secret' => 'HMAC-PLATAFORMA']);
        $this->simularProducao();

        $corpo = json_encode(['txid' => 'txid-inexistente', 'valor' => 10.0]);
        $this->postWebhook($corpo, [
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $corpo, 'HMAC-PLATAFORMA'),
        ])->assertStatus(401);
    }

    public function test_producao_empresa_com_pix_proprio_nao_herda_hmac_do_env(): void
    {
        [$empresa, $cobranca] = $this->cobranca();
        // Empresa TEM PIX próprio (client_id+secret) mas NÃO configurou o HMAC.
        EmpresaConfig::query()->create([
            'empresa_id' => $empresa->id,
            'dados' => ['integracoes' => ['pix' => [
                'client_id' => 'CID',
                'client_secret' => Crypt::encryptString('CSECRET'),
            ]]],
        ]);
        config(['services.pix.webhook_secret' => null, 'services.pix.webhook_hmac_secret' => 'HMAC-PLATAFORMA']);
        $this->simularProducao();

        // Assinatura VÁLIDA com o HMAC da plataforma (env) — mesmo assim rejeita:
        // o PSP da empresa não assina com o segredo da plataforma.
        $corpo = json_encode(['txid' => $cobranca->txid, 'valor' => 80.0]);
        $this->postWebhook($corpo, [
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $corpo, 'HMAC-PLATAFORMA'),
        ])->assertStatus(401);

        $this->assertSame(
            SituacaoPix::ATIVA,
            PixCobranca::withoutTenant()->find($cobranca->id)->situacao,
        );
    }

    public function test_producao_hmac_da_empresa_confirma(): void
    {
        [$empresa, $cobranca] = $this->cobranca();
        EmpresaConfig::query()->create([
            'empresa_id' => $empresa->id,
            'dados' => ['integracoes' => ['pix' => [
                'client_id' => 'CID',
                'client_secret' => Crypt::encryptString('CSECRET'),
                'webhook_hmac_secret' => Crypt::encryptString('HMAC-EMP'),
            ]]],
        ]);
        config(['services.pix.webhook_secret' => null, 'services.pix.webhook_hmac_secret' => null]);
        $this->simularProducao();

        $corpo = json_encode(['txid' => $cobranca->txid, 'valor' => 80.0]);
        $this->postWebhook($corpo, [
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $corpo, 'HMAC-EMP'),
        ])->assertOk()->assertJsonPath('situacao', 'CONCLUIDA');
    }

    public function test_producao_camada1_correta_basta_quando_empresa_nao_tem_pix_proprio(): void
    {
        [, $cobranca] = $this->cobranca();
        config(['services.pix.webhook_secret' => 'TOKEN-URL', 'services.pix.webhook_hmac_secret' => null]);
        $this->simularProducao();

        // Sem HMAC em lugar nenhum, mas o chamador autenticou pela camada 1.
        $this->postWebhook(json_encode(['txid' => $cobranca->txid, 'valor' => 80.0]), [
            'HTTP_X_WEBHOOK_TOKEN' => 'TOKEN-URL',
        ])->assertOk()->assertJsonPath('situacao', 'CONCLUIDA');
    }

    public function test_producao_camada1_errada_rejeita(): void
    {
        [, $cobranca] = $this->cobranca();
        config(['services.pix.webhook_secret' => 'TOKEN-URL', 'services.pix.webhook_hmac_secret' => null]);
        $this->simularProducao();

        $this->postWebhook(json_encode(['txid' => $cobranca->txid, 'valor' => 80.0]), [
            'HTTP_X_WEBHOOK_TOKEN' => 'ERRADO',
        ])->assertStatus(401);
    }

    // ── Dev/CI: comportamento permissivo preservado (regressão) ────────────────

    public function test_fora_de_producao_sem_segredo_continua_processando(): void
    {
        [, $cobranca] = $this->cobranca();
        config(['services.pix.webhook_secret' => null, 'services.pix.webhook_hmac_secret' => null]);

        $this->postWebhook(json_encode(['txid' => $cobranca->txid, 'valor' => 80.0]))
            ->assertOk()->assertJsonPath('situacao', 'CONCLUIDA');
    }
}
