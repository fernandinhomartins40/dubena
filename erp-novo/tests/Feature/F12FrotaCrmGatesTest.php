<?php

namespace Tests\Feature;

use App\Domain\Mobile\Contracts\PagamentoDriver;
use App\Domain\Mobile\Drivers\EredeDriver;
use App\Domain\Mobile\Drivers\FakePagamentoDriver;
use App\Domain\Monitora\Contracts\SgcasaDriver;
use App\Domain\Monitora\Drivers\FakeSgcasaDriver;
use App\Domain\Monitora\Drivers\SgcasaHttpDriver;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Frota\Veiculo;
use App\Models\Frota\VeiculoDocumento;
use App\Models\Frota\VeiculoEntradaSaida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F12 — frota (entrada-saída/documento), mala direta e seleção de drivers por gate.
 */
class F12FrotaCrmGatesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    private function veiculo(Empresa $e): Veiculo
    {
        return Veiculo::withoutTenant()->create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'placa' => 'ABC1D23', 'descricao' => 'Caminhão', 'km_atual' => 1000, 'ativo' => true,
        ]);
    }

    // ── Frota: entrada/saída ──
    public function test_registra_entrada_saida_e_atualiza_km(): void
    {
        [$user, $empresa] = $this->suporte();
        $v = $this->veiculo($empresa);

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/veiculos/{$v->id}/entradas-saidas", [
            'tipo' => 'SAIDA', 'km' => 1500,
        ])->assertCreated()->assertJsonPath('data.tipo', 'SAIDA');

        $this->assertSame(1, VeiculoEntradaSaida::withoutTenant()->where('veiculo_id', $v->id)->count());
        $this->assertSame(1500, (int) $v->refresh()->km_atual); // km avançou
        // empresa_id herdado do veículo (F02).
        $this->assertSame($empresa->id, (int) VeiculoEntradaSaida::withoutTenant()->first()->empresa_id);
    }

    // ── Frota: documento ──
    public function test_registra_documento_do_veiculo(): void
    {
        [$user, $empresa] = $this->suporte();
        $v = $this->veiculo($empresa);

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/veiculos/{$v->id}/documentos", [
            'tipo' => 'CRLV', 'numero' => '2026', 'vencimento' => '2026-12-31',
        ])->assertCreated()->assertJsonPath('data.tipo', 'CRLV');

        $doc = VeiculoDocumento::withoutTenant()->where('veiculo_id', $v->id)->first();
        $this->assertNotNull($doc);
        $this->assertSame($empresa->id, (int) $doc->empresa_id);
    }

    // ── Mala direta ──
    public function test_mala_direta_segmenta_e_exporta_csv(): void
    {
        [$user, $empresa] = $this->suporte();
        Cliente::withoutTenant()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'nome' => 'Com Email', 'email' => 'a@b.com', 'ativo' => true]);
        Cliente::withoutTenant()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'nome' => 'Sem Email', 'email' => null, 'ativo' => true]);

        // Filtro com_email → só 1.
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/crm/mala-direta?com_email=1')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.nome', 'Com Email');

        // Export CSV.
        $resp = $this->actingAs($user, 'sanctum')->get('/api/admin/crm/mala-direta?com_email=1&formato=csv');
        $resp->assertOk();
        $this->assertStringContainsString('text/csv', $resp->headers->get('Content-Type'));
    }

    // ── Seleção de drivers por gate ──
    public function test_gate_pagamento_seleciona_driver(): void
    {
        config(['services.pagamento.driver' => 'fake']);
        $this->assertInstanceOf(FakePagamentoDriver::class, app(PagamentoDriver::class));

        config(['services.pagamento.driver' => 'erede']);
        $this->assertInstanceOf(EredeDriver::class, app(PagamentoDriver::class));
    }

    public function test_gate_monitora_seleciona_driver(): void
    {
        config(['services.monitora.driver' => 'fake']);
        $this->app->forgetInstance(SgcasaDriver::class);
        $this->assertInstanceOf(FakeSgcasaDriver::class, app(SgcasaDriver::class));

        config(['services.monitora.driver' => 'sgcasa']);
        $this->app->forgetInstance(SgcasaDriver::class);
        $this->assertInstanceOf(SgcasaHttpDriver::class, app(SgcasaDriver::class));
    }
}
