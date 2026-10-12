<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A tela lia `receber_aberto`… e o servidor só devolvia `a_receber`: os quatro
 * cartões do Financeiro mostravam "—" sempre.
 */
class FinanceiroResumoTest extends TestCase
{
    use RefreshDatabase;

    public function test_resumo_devolve_o_que_a_tela_le_com_o_realizado_do_mes(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $doGrupo = ['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'cancelado' => false, 'created_at' => now(), 'updated_at' => now()];
        $r = DB::table('financeiros')->insertGetId($doGrupo + ['pagarreceber' => 'R', 'valor' => 300]);
        $p = DB::table('financeiros')->insertGetId($doGrupo + ['pagarreceber' => 'P', 'valor' => 80]);
        $parcela = fn (int $f, int $n, float $v, bool $baixado = false, ?string $quando = null, float $efetivado = 0) => DB::table('financeiroparcelas')->insert([
            'financeiro_id' => $f, 'empresa_id' => $empresa->id, 'numero' => $n, 'vencimento' => today(), 'valor' => $v,
            'baixado' => $baixado, 'datahora_baixa' => $quando, 'valor_efetivado' => $efetivado, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $parcela($r, 1, 100);
        $parcela($r, 2, 100, true, now()->toDateTimeString(), 105);              // recebido neste mês, com juros
        $parcela($r, 3, 100, true, now()->subMonths(2)->toDateTimeString(), 100); // recebido em outro mês: fora
        $parcela($p, 1, 80);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/financeiro/lancamentos/resumo')->assertOk()
            ->assertJsonPath('data.receber_aberto', 100)
            ->assertJsonPath('data.pagar_aberto', 80)
            ->assertJsonPath('data.receber_mes', 105)
            ->assertJsonPath('data.pagar_mes', 0)
            ->assertJsonPath('data.a_receber', 100);
    }
}
