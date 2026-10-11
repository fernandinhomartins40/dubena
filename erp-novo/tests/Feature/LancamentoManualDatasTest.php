<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Financeiro\Financeiro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O lançamento manual descartava a data, o vencimento e a condição que a
 * pessoa digitava: emissão virava "hoje" e saía UMA parcela vencendo hoje.
 * Uma conta a pagar de daqui a 30 dias aparecia vencida; o 3x virava à vista.
 */
class LancamentoManualDatasTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        $empresa = Empresa::factory()->create();

        return User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
    }

    public function test_condicao_e_vencimento_digitados_viram_as_parcelas(): void
    {
        $user = $this->usuario();
        $tres = CondicaoPagamento::query()->create([
            'grupo_id' => $user->grupo_id, 'descricao' => '3x', 'num_parcelas' => 3, 'intervalo_dias' => 30, 'dias_primeira' => 30, 'ativo' => true,
        ]);

        $id = $this->actingAs($user, 'sanctum')->postJson('/api/admin/financeiro/lancamentos', [
            'pagarreceber' => 'P', 'valor' => 300,
            'data_emissao' => '2026-10-01', 'vencimento' => '2026-10-11',
            'condicaopagamento_id' => $tres->id,
        ])->assertCreated()->json('data.id');

        $f = Financeiro::query()->with('parcelas')->findOrFail($id);
        $this->assertSame('2026-10-01', substr((string) $f->data_emissao, 0, 10));
        $this->assertSame(
            ['2026-10-11', '2026-11-10', '2026-12-10'],
            $f->parcelas->sortBy('numero')->map(fn ($p) => substr((string) $p->vencimento, 0, 10))->values()->all(),
            'primeira no vencimento digitado; as demais pelo intervalo da condição',
        );
        $this->assertEqualsWithDelta(300, $f->parcelas->sum('valor'), 0.01);
    }

    /** Sem vencimento, vale a regra da própria condição (dias para a primeira). */
    public function test_sem_vencimento_vale_a_condicao(): void
    {
        $user = $this->usuario();
        $trinta = CondicaoPagamento::query()->create([
            'grupo_id' => $user->grupo_id, 'descricao' => '30 dias', 'num_parcelas' => 1, 'intervalo_dias' => 30, 'dias_primeira' => 30, 'ativo' => true,
        ]);

        $id = $this->actingAs($user, 'sanctum')->postJson('/api/admin/financeiro/lancamentos', [
            'pagarreceber' => 'R', 'valor' => 100, 'data_emissao' => '2026-10-01', 'condicaopagamento_id' => $trinta->id,
        ])->assertCreated()->json('data.id');

        $this->assertSame('2026-10-31', substr((string) Financeiro::query()->findOrFail($id)->parcelas()->value('vencimento'), 0, 10));
    }

    public function test_vencimento_antes_da_emissao_e_recusado(): void
    {
        $this->actingAs($this->usuario(), 'sanctum')->postJson('/api/admin/financeiro/lancamentos', [
            'pagarreceber' => 'R', 'valor' => 100, 'data_emissao' => '2026-10-10', 'vencimento' => '2026-10-01',
        ])->assertStatus(422)->assertJsonValidationErrors('vencimento');
    }
}
