<?php

namespace Tests\Feature;

use App\Models\Caixa\Conta;
use App\Models\Caixa\ContaMovimento;
use App\Models\Cliente\Cliente;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Financeiro\Financeiro;
use App\Models\Pedido\Pedido;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\Convenio;
use App\Models\Satelite\ValeGas;
use Database\Seeders\HomologSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE da FASE C3 — seed de homologação.
 *
 * Garante que o HomologSeeder roda ponta-a-ponta e produz dados ÍNTEGROS:
 * nenhuma tabela de negócio essencial vazia + os invariantes auditáveis verdes
 * (Σ estoquehistorico = saldo; Σ contamovimentos = conta.saldo_atual). É o que
 * torna a homologação confiável (a auditoria apontou homologação vazia).
 */
class HomologSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_popula_negocio_e_mantem_invariantes(): void
    {
        $this->seed(HomologSeeder::class);

        // Nenhuma tabela de negócio essencial vazia.
        $this->assertGreaterThanOrEqual(20, Cliente::query()->count());
        $this->assertGreaterThanOrEqual(4, Produto::withoutGlobalScopes()->count());
        $this->assertGreaterThanOrEqual(30, Pedido::withoutGlobalScopes()->count());
        $this->assertGreaterThanOrEqual(30, Financeiro::withoutGlobalScopes()->count());
        $this->assertGreaterThanOrEqual(1, Conta::withoutGlobalScopes()->count());
        $this->assertGreaterThanOrEqual(1, Convenio::withoutGlobalScopes()->count());
        $this->assertGreaterThanOrEqual(1, ValeGas::withoutGlobalScopes()->count());
        $this->assertGreaterThanOrEqual(1, Comodato::withoutGlobalScopes()->count());

        // INVARIANTE estoque: Σ histórico = saldo (por setor×produto).
        foreach (EstoqueSaldo::withoutGlobalScopes()->get() as $saldo) {
            $hist = EstoqueHistorico::withoutGlobalScopes()
                ->where('setor_id', $saldo->setor_id)
                ->where('produto_id', $saldo->produto_id)
                ->sum('quantidade');
            $this->assertEqualsWithDelta((float) $saldo->quantidade, (float) $hist, 0.001,
                "Estoque diverge no setor {$saldo->setor_id} / produto {$saldo->produto_id}.");
        }

        // INVARIANTE caixa: Σ movimentos = saldo_atual.
        foreach (Conta::withoutGlobalScopes()->get() as $conta) {
            $soma = ContaMovimento::where('conta_id', $conta->id)->sum('valor');
            $this->assertEqualsWithDelta((float) $conta->saldo_atual, (float) $soma, 0.001,
                "Saldo da conta {$conta->id} diverge dos movimentos.");
        }
    }

    public function test_seed_e_idempotente(): void
    {
        $this->seed(HomologSeeder::class);
        $pedidosApos1 = Pedido::withoutGlobalScopes()->count();

        // Rodar de novo não deve duplicar pedidos/contas (idempotência defensiva).
        $this->seed(HomologSeeder::class);
        $this->assertSame($pedidosApos1, Pedido::withoutGlobalScopes()->count());
        $this->assertSame(1, Conta::withoutGlobalScopes()->where('descricao', 'Caixa Geral')->count());
    }
}
