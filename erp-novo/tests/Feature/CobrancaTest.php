<?php

namespace Tests\Feature;

use App\Domain\Cobranca\PixService;
use App\Domain\Financeiro\FinanceiroService;
use App\Models\Empresa;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N7 — API de cobrança (boleto/pix) + webhook público.
 */
class CobrancaTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    private function parcela(Empresa $empresa, float $valor = 100): FinanceiroParcela
    {
        return app(FinanceiroService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'pagarreceber' => 'R', 'valor' => $valor,
        ])->parcelas->first();
    }

    public function test_gera_boleto_via_api(): void
    {
        [$user, $empresa] = $this->suporte();
        $parcela = $this->parcela($empresa, 200);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/boletos', ['parcela_id' => $parcela->id])
            ->assertCreated()
            ->assertJsonPath('data.valor', '200.00');

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/boletos')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cria_cobranca_pix_via_api(): void
    {
        [$user, $empresa] = $this->suporte();
        $parcela = $this->parcela($empresa, 90);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/pix/cobrancas', ['parcela_id' => $parcela->id])
            ->assertCreated()
            ->assertJsonPath('data.situacao', 'ATIVA');
    }

    public function test_webhook_publico_confirma_pagamento(): void
    {
        [, $empresa] = $this->suporte();
        $parcela = $this->parcela($empresa, 75);
        $cobranca = app(PixService::class)->criarCobranca($parcela);

        config()->set('services.pix.webhook_secret', 'segredo-de-teste');
        $this->postJson('/api/pix/webhook', ['txid' => $cobranca->txid, 'valor' => 75.00], ['X-Webhook-Token' => 'segredo-de-teste'])
            ->assertOk()
            ->assertJsonPath('situacao', 'CONCLUIDA');

        $this->assertTrue($parcela->refresh()->baixado);
    }

    public function test_webhook_rejeita_valor_divergente(): void
    {
        [, $empresa] = $this->suporte();
        $cobranca = app(PixService::class)->criarCobranca($this->parcela($empresa, 75));
        config()->set('services.pix.webhook_secret', 'segredo-de-teste');

        $this->postJson('/api/pix/webhook', ['txid' => $cobranca->txid, 'valor' => 10.00], ['X-Webhook-Token' => 'segredo-de-teste'])
            ->assertStatus(422);
    }

    public function test_webhook_com_segredo_invalido_401(): void
    {
        config()->set('services.pix.webhook_secret', 'segredo-correto');
        [, $empresa] = $this->suporte();
        $cobranca = app(PixService::class)->criarCobranca($this->parcela($empresa, 75));

        $this->postJson('/api/pix/webhook', ['txid' => $cobranca->txid, 'valor' => 75.00], ['X-Webhook-Token' => 'errado'])
            ->assertStatus(401);
    }

    // ── S-1 (auditoria): assinatura HMAC do corpo cru ──────────────────────────

    public function test_webhook_hmac_valido_confirma(): void
    {
        config()->set('services.pix.webhook_hmac_secret', 'hmac-super-secreto');
        [, $empresa] = $this->suporte();
        $parcela = $this->parcela($empresa, 75);
        $cobranca = app(PixService::class)->criarCobranca($parcela);

        $payload = ['txid' => $cobranca->txid, 'valor' => 75.00];
        $corpo = json_encode($payload);
        $assinatura = hash_hmac('sha256', $corpo, 'hmac-super-secreto');

        $this->call('POST', '/api/pix/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $assinatura,
        ], $corpo)->assertOk()->assertJsonPath('situacao', 'CONCLUIDA');

        $this->assertTrue($parcela->refresh()->baixado);
    }

    public function test_webhook_hmac_invalido_401(): void
    {
        config()->set('services.pix.webhook_hmac_secret', 'hmac-super-secreto');
        [, $empresa] = $this->suporte();
        $cobranca = app(PixService::class)->criarCobranca($this->parcela($empresa, 75));

        $corpo = json_encode(['txid' => $cobranca->txid, 'valor' => 75.00]);

        $this->call('POST', '/api/pix/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'assinatura-forjada',
        ], $corpo)->assertStatus(401);
    }

    public function test_webhook_sem_hmac_aceita_chamador_autenticado_na_camada1(): void
    {
        config()->set('services.pix.webhook_secret', 'segredo-de-teste');
        [, $empresa] = $this->suporte();
        $cobranca = app(PixService::class)->criarCobranca($this->parcela($empresa, 75));

        $this->postJson('/api/pix/webhook', ['txid' => $cobranca->txid, 'valor' => 75.00], ['X-Webhook-Token' => 'segredo-de-teste'])
            ->assertOk()->assertJsonPath('situacao', 'CONCLUIDA');
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/boletos')->assertStatus(403);
    }
}
