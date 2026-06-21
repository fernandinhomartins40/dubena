<?php

namespace Tests\Migration;

use App\Domain\Estoque\EstoqueService;
use App\Etl\Invariants\BalanceInvariant;
use App\Etl\Support\MigrationContext;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prova a invariante de ouro do estoque (e do cutover): Σ movimentos = saldo.
 */
class BalanceInvariantTest extends TestCase
{
    use RefreshDatabase;

    private function invariante(): BalanceInvariant
    {
        return new BalanceInvariant(
            new MigrationContext(),
            'estoquehistorico', 'quantidade',
            'estoquesaldos', 'quantidade',
            ['setor_id', 'produto_id'],
        );
    }

    public function test_passa_quando_saldo_bate_com_historico(): void
    {
        $service = app(EstoqueService::class);
        $setor = Setor::factory()->create();
        $produto = Produto::factory()->create(['empresa_id' => $setor->empresa_id, 'grupo_id' => $setor->grupo_id]);

        $service->entrada($setor->id, $produto->id, 100, 10.0);
        $service->saida($setor->id, $produto->id, 30);

        $this->assertTrue($this->invariante()->verificar()->ok);
    }

    public function test_falha_quando_saldo_diverge_do_historico(): void
    {
        $service = app(EstoqueService::class);
        $setor = Setor::factory()->create();
        $produto = Produto::factory()->create(['empresa_id' => $setor->empresa_id, 'grupo_id' => $setor->grupo_id]);
        $service->entrada($setor->id, $produto->id, 100, 10.0);

        // Corrompe o saldo materializado (simula divergência que a invariante deve pegar).
        EstoqueSaldo::query()
            ->where('setor_id', $setor->id)->where('produto_id', $produto->id)
            ->update(['quantidade' => 999]);

        $this->assertFalse($this->invariante()->verificar()->ok);
    }
}
