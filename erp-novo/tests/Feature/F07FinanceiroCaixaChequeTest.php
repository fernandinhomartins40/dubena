<?php

namespace Tests\Feature;

use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\FinanceiroService;
use App\Models\Caixa\Conta;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F07 — superfície HTTP do financeiro/caixa/cheque (rotas antes órfãs): agrupar/
 * desagrupar títulos, baixa em lote, lançamento em caixa fechado, e transições de
 * situação de cheque (depósito/compensação/devolução). Caminho de DINHEIRO.
 */
class F07FinanceiroCaixaChequeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true]);

        return [$user, $empresa];
    }

    private function titulo(Empresa $e, float $valor, string $pr = 'R'): Financeiro
    {
        return app(FinanceiroService::class)->criar([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'pagarreceber' => $pr, 'valor' => $valor,
        ]);
    }

    private function conta(Empresa $e, float $inicial = 0): Conta
    {
        return app(CaixaService::class)->criarConta([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id, 'descricao' => 'Caixa', 'saldo_inicial' => $inicial,
        ]);
    }

    // ── Financeiro: agrupar / desagrupar ──
    public function test_agrupar_titulos_via_api(): void
    {
        [$user, $empresa] = $this->suporte();
        $t1 = $this->titulo($empresa, 100); $t2 = $this->titulo($empresa, 150);

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/financeiro/lancamentos/agrupar', [
            'titulos' => [$t1->id, $t2->id], 'pagarreceber' => 'R', 'descricao' => 'Fechamento mês',
        ])->assertCreated();

        $agrupadorId = $resp->json('data.id');
        // O agrupador soma os títulos; os originais ficam marcados como agrupados.
        $this->assertEqualsWithDelta(250.0, (float) Financeiro::withoutTenant()->find($agrupadorId)->valor, 0.01);

        // Desagrupar reativa os originais e cancela o agrupador.
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/financeiro/lancamentos/{$agrupadorId}/desagrupar")
            ->assertOk();
        $this->assertTrue((bool) Financeiro::withoutTenant()->find($agrupadorId)->cancelado);
    }

    // ── Caixa: baixa em lote ──
    public function test_baixa_titulos_em_lote_credita_conta(): void
    {
        [$user, $empresa] = $this->suporte();
        $conta = $this->conta($empresa, 0);
        $p1 = $this->titulo($empresa, 100)->parcelas->first();
        $p2 = $this->titulo($empresa, 200)->parcelas->first();

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/baixar-titulos", [
            'itens' => [['parcela_id' => $p1->id], ['parcela_id' => $p2->id, 'desconto' => 10]],
        ])->assertCreated();

        // Recebimento entra: 100 + (200-10) = 290.
        $saldo = (float) Conta::withoutTenant()->find($conta->id)->saldo_atual;
        $this->assertEqualsWithDelta(290.0, $saldo, 0.01);
        $this->assertTrue((bool) FinanceiroParcela::withoutTenant()->find($p1->id)->baixado);
        $this->assertTrue((bool) FinanceiroParcela::withoutTenant()->find($p2->id)->baixado);
    }

    public function test_baixa_titulos_e_tudo_ou_nada(): void
    {
        [$user, $empresa] = $this->suporte();
        $conta = $this->conta($empresa, 0);
        $p1 = $this->titulo($empresa, 100)->parcelas->first();

        // Segundo item com parcela inexistente → validação falha, nada é baixado.
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/baixar-titulos", [
            'itens' => [['parcela_id' => $p1->id], ['parcela_id' => 999999]],
        ])->assertStatus(422);

        $this->assertFalse((bool) FinanceiroParcela::withoutTenant()->find($p1->id)->baixado);
        $this->assertEqualsWithDelta(0.0, (float) Conta::withoutTenant()->find($conta->id)->saldo_atual, 0.01);
    }

    // ── Caixa: lançamento em caixa fechado ──
    public function test_lancar_em_caixa_fechado(): void
    {
        [$user, $empresa] = $this->suporte();
        $conta = $this->conta($empresa, 0);
        app(CaixaService::class)->abrir($conta->id);   // abre (cria fechamento)
        app(CaixaService::class)->fechar($conta->id);  // e fecha o caixa

        // Lançamento normal recusaria; lancar-fechado é autorizado.
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/caixa/{$conta->id}/lancar-fechado", [
            'valor' => 50, 'tipo' => 'AJUSTE', 'descricao' => 'Ajuste retroativo',
        ])->assertCreated();

        $this->assertEqualsWithDelta(50.0, (float) Conta::withoutTenant()->find($conta->id)->saldo_atual, 0.01);
    }

    // ── Cheque: depósito → compensação credita caixa; devolução ──
    public function test_cheque_recebido_compensa_e_credita_caixa(): void
    {
        [$user, $empresa] = $this->suporte();
        $conta = $this->conta($empresa, 0);

        $cheque = $this->actingAs($user, 'sanctum')->postJson('/api/admin/cheques', [
            'especie' => 'R', 'numero' => '000123', 'valor' => 300, 'titular' => 'Cliente X',
        ])->assertCreated()->json('data');

        // CARTEIRA → DEPOSITADO → COMPENSADO (compensar exige conta; credita o caixa).
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/cheques/{$cheque['id']}/situacao", ['situacao' => 'DEPOSITADO'])->assertOk();
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/cheques/{$cheque['id']}/situacao", [
            'situacao' => 'COMPENSADO', 'conta_id' => $conta->id,
        ])->assertOk()->assertJsonPath('data.situacao', 'COMPENSADO');

        $this->assertEqualsWithDelta(300.0, (float) Conta::withoutTenant()->find($conta->id)->saldo_atual, 0.01);
    }

    public function test_cheque_transicao_invalida_bloqueia(): void
    {
        [$user, $empresa] = $this->suporte();
        $cheque = $this->actingAs($user, 'sanctum')->postJson('/api/admin/cheques', [
            'especie' => 'R', 'numero' => '999', 'valor' => 100,
        ])->assertCreated()->json('data');

        // CARTEIRA → COMPENSADO direto não é permitido (precisa depositar antes).
        $this->actingAs($user, 'sanctum')->putJson("/api/admin/cheques/{$cheque['id']}/situacao", ['situacao' => 'COMPENSADO'])
            ->assertStatus(422);
    }
}
