<?php

namespace Tests\Feature;

use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\FinanceiroService;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N6 — API de caixa: contas, abrir/fechar, baixar parcela, transferir, RBAC.
 */
class CaixaTest extends TestCase
{
    use RefreshDatabase;

    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    public function test_cria_conta_e_lista(): void
    {
        [$user] = $this->suporte();

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/caixa/contas', [
            'descricao' => 'Caixa Loja', 'tipo' => 'CAIXA', 'saldo_inicial' => 100,
        ])->assertCreated();

        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/admin/caixa/contas')->assertOk();
        $this->assertEqualsWithDelta(100, collect($resp->json('data'))->first()['saldoatual'], 0.001);
    }

    public function test_baixar_parcela_via_api_credita_conta(): void
    {
        [$user, $empresa] = $this->suporte();
        $conta = app(CaixaService::class)->criarConta(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Caixa', 'saldo_inicial' => 0]);
        $f = app(FinanceiroService::class)->criar(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'pagarreceber' => 'R', 'valor' => 150]);

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/baixar", [
            'parcela_id' => $f->parcelas->first()->id,
        ])->assertCreated();

        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/admin/caixa/contas')->json('data');
        $this->assertEqualsWithDelta(150, collect($resp)->firstWhere('id', $conta->id)['saldoatual'], 0.001);
    }

    public function test_transferencia_via_api(): void
    {
        [$user, $empresa] = $this->suporte();
        $svc = app(CaixaService::class);
        $a = $svc->criarConta(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'A', 'saldo_inicial' => 500]);
        $b = $svc->criarConta(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'B', 'saldo_inicial' => 0]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/caixa/transferencias', [
            'conta_origem_id' => $a->id, 'conta_destino_id' => $b->id, 'valor' => 200,
        ])->assertCreated();

        $resp = collect($this->actingAs($user, 'sanctum')->getJson('/api/admin/caixa/contas')->json('data'));
        $this->assertEqualsWithDelta(300, $resp->firstWhere('id', $a->id)['saldoatual'], 0.001);
        $this->assertEqualsWithDelta(200, $resp->firstWhere('id', $b->id)['saldoatual'], 0.001);
    }

    public function test_transferencia_via_api_recusa_conta_de_outro_tenant(): void
    {
        [$user, $empresa] = $this->suporte();
        $svc = app(CaixaService::class);
        $origem = $svc->criarConta([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Origem', 'saldo_inicial' => 500,
        ]);
        $outra = Empresa::factory()->create();
        $destino = $svc->criarConta([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'descricao' => 'Destino alheio', 'saldo_inicial' => 0,
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/caixa/transferencias', [
            'conta_origem_id' => $origem->id, 'conta_destino_id' => $destino->id, 'valor' => 200,
        ])->assertStatus(422);

        $this->assertEqualsWithDelta(500, (float) $origem->refresh()->saldo_atual, 0.001);
        $this->assertEqualsWithDelta(0, (float) $destino->refresh()->saldo_atual, 0.001);
    }

    public function test_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/caixa/contas')->assertStatus(403);
    }
}
