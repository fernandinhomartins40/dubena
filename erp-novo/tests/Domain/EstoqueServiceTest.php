<?php

namespace Tests\Domain;

use App\Domain\Estoque\EstoqueService;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * BASELINE do núcleo de estoque (caminho crítico do plano).
 * Prova a invariante Σ histórico = saldo, custo médio ponderado, transferência,
 * acerto e fechamento. Sem isso, o N3 não avança (DoD).
 */
class EstoqueServiceTest extends TestCase
{
    use RefreshDatabase;

    private EstoqueService $service;
    private Setor $setor;
    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EstoqueService::class);
        // Setor e produto da MESMA empresa (FK coerente).
        $this->setor = Setor::factory()->create();
        $this->produto = Produto::factory()->create([
            'empresa_id' => $this->setor->empresa_id,
            'grupo_id' => $this->setor->grupo_id,
        ]);
    }

    private function saldo(): float
    {
        return (float) EstoqueSaldo::query()
            ->where('setor_id', $this->setor->id)->where('produto_id', $this->produto->id)
            ->value('quantidade');
    }

    public function test_invariante_soma_historico_igual_saldo(): void
    {
        $this->service->entrada($this->setor->id, $this->produto->id, 100, 10.0);
        $this->service->saida($this->setor->id, $this->produto->id, 30);
        $this->service->entrada($this->setor->id, $this->produto->id, 50, 12.0);
        $this->service->saida($this->setor->id, $this->produto->id, 20);

        $derivado = $this->service->saldoDerivado($this->setor->id, $this->produto->id);
        $this->assertEqualsWithDelta(100, $derivado, 0.001);          // 100-30+50-20
        $this->assertEqualsWithDelta($derivado, $this->saldo(), 0.001); // saldo materializado = derivado
    }

    public function test_cada_movimento_grava_saldo_resultante_correto(): void
    {
        $this->service->entrada($this->setor->id, $this->produto->id, 100, 10.0);
        $this->service->saida($this->setor->id, $this->produto->id, 40);

        $movs = EstoqueHistorico::query()->orderBy('id')->get();
        $this->assertEqualsWithDelta(100, (float) $movs[0]->saldo_resultante, 0.001);
        $this->assertEqualsWithDelta(60, (float) $movs[1]->saldo_resultante, 0.001);
    }

    public function test_custo_medio_ponderado(): void
    {
        // 100 @ 10 = 1000; +50 @ 16 = 800; total 1800 / 150 = 12,00
        $this->service->entrada($this->setor->id, $this->produto->id, 100, 10.0);
        $this->service->entrada($this->setor->id, $this->produto->id, 50, 16.0);

        $custo = (float) EstoqueSaldo::query()
            ->where('setor_id', $this->setor->id)->where('produto_id', $this->produto->id)
            ->value('custo_medio');
        $this->assertEqualsWithDelta(12.0, $custo, 0.0001);
    }

    public function test_saida_maior_que_saldo_bloqueia(): void
    {
        $this->service->entrada($this->setor->id, $this->produto->id, 10);

        $this->expectException(ValidationException::class);
        $this->service->saida($this->setor->id, $this->produto->id, 25);
    }

    public function test_transferencia_move_entre_setores_preservando_total(): void
    {
        $destino = Setor::factory()->create(['empresa_id' => $this->setor->empresa_id, 'grupo_id' => $this->setor->grupo_id]);
        $this->service->entrada($this->setor->id, $this->produto->id, 100, 9.0);

        $this->service->transferir($this->setor->id, $destino->id, $this->produto->id, 40);

        $this->assertEqualsWithDelta(60, $this->service->saldoDerivado($this->setor->id, $this->produto->id), 0.001);
        $this->assertEqualsWithDelta(40, $this->service->saldoDerivado($destino->id, $this->produto->id), 0.001);
        // O total entre setores é preservado.
        $total = $this->service->saldoDerivado($this->setor->id, $this->produto->id)
            + $this->service->saldoDerivado($destino->id, $this->produto->id);
        $this->assertEqualsWithDelta(100, $total, 0.001);
    }

    public function test_acerto_ajusta_para_quantidade_contada(): void
    {
        $this->service->entrada($this->setor->id, $this->produto->id, 100, 10.0);

        // Contagem física encontrou 95 (quebra de 5).
        $mov = $this->service->acertar($this->setor->id, $this->produto->id, 95);

        $this->assertEqualsWithDelta(-5, (float) $mov->quantidade, 0.001);
        $this->assertEqualsWithDelta(95, $this->saldo(), 0.001);
        $this->assertEqualsWithDelta(95, $this->service->saldoDerivado($this->setor->id, $this->produto->id), 0.001);
    }

    public function test_acerto_sem_diferenca_nao_gera_movimento(): void
    {
        $this->service->entrada($this->setor->id, $this->produto->id, 50);
        $mov = $this->service->acertar($this->setor->id, $this->produto->id, 50);

        $this->assertNull($mov);
        $this->assertEquals(1, EstoqueHistorico::query()->count()); // só a entrada
    }

    public function test_fechamento_registra_saldo_inicial_e_final(): void
    {
        $this->service->entrada($this->setor->id, $this->produto->id, 80, 10.0);

        $f1 = $this->service->fechar($this->setor->id, $this->produto->id, '2026-01-31');
        $this->assertEqualsWithDelta(0, (float) $f1->saldo_inicial, 0.001);
        $this->assertEqualsWithDelta(80, (float) $f1->saldo_final, 0.001);

        $this->service->entrada($this->setor->id, $this->produto->id, 20);
        $f2 = $this->service->fechar($this->setor->id, $this->produto->id, '2026-02-28');
        // saldo inicial do 2º fechamento = saldo final do 1º.
        $this->assertEqualsWithDelta(80, (float) $f2->saldo_inicial, 0.001);
        $this->assertEqualsWithDelta(100, (float) $f2->saldo_final, 0.001);
    }
}
