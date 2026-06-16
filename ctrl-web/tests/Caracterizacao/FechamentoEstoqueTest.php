<?php

namespace Tests\Caracterizacao;

use Tests\TestCase;
use Tests\Caracterizacao\Support\FixturesFiscais;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Processors\EstoqueProcessor;
use App\Estoquesetorhistorico;
use App\Estoquefechamento;
use App\Estoquefechamentosetor;
use App\Services\CarbonCustom as Carbon;

/**
 * Caracterização (golden master) — FASE 1, leva 5.
 *
 * Exercita EstoqueProcessor::fecharEstoque no PRIMEIRO fechamento: agrega as
 * movimentações (ENTRADA/SAÍDA) por setor+produto até a data e grava o saldo
 * em estoquefechamentosetors. Fixa o que o motor consolida hoje, para a
 * refatoração (Fase 4) não alterar o fechamento de estoque.
 *
 * Desliga o event dispatcher (lib de auditoria chama Event::fire(), removido).
 *
 * PHPUnit 7.5 / Laravel 5.4 / PHP 7.4.
 */
class FechamentoEstoqueTest extends TestCase
{
    use DatabaseTransactions;
    use FixturesFiscais;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
    }

    private function historico($movimentacao, $quantidade, $quandoSubHoras = 1)
    {
        $h = new Estoquesetorhistorico();
        $h->user_id = 1;
        $h->setor_id = $this->setor->id;
        $h->produto_id = $this->produto->id;
        $h->movimentacao = $movimentacao;
        $h->quantidade = $quantidade;
        $h->motivo = 'Caracterização';
        $h->datahora = Carbon::now()->subHours($quandoSubHoras);
        $h->datahoracompetencia = Carbon::now()->subHours($quandoSubHoras);
        $h->entidade = 'Teste';
        $h->entidade_id = 1;
        $h->grupo_id = $this->empresa->grupo_id;
        $h->empresa_id = $this->empresa->id;
        return $h;
    }

    public function testPrimeiroFechamentoConsolidaSaldoPorSetorProduto()
    {
        $this->criarCenarioFiscal();

        // Movimenta: ENTRADA 10, depois SAÍDA 4 (saldo 6).
        $proc = new EstoqueProcessor();
        $this->assertTrue($proc->movimentarEstoque([$this->historico('ENTRADA', 10, 3)]),
            implode('; ', $proc->getErrors()));
        $proc2 = new EstoqueProcessor();
        $this->assertTrue($proc2->movimentarEstoque([$this->historico('SAIDA', 4, 2)]),
            implode('; ', $proc2->getErrors()));

        // Fecha o estoque com data/hora após as movimentações.
        $fech = new Estoquefechamento();
        $fech->grupo_id = $this->empresa->grupo_id;
        $fech->empresa_id = $this->empresa->id;
        $fech->datahorafechamento = Carbon::now();
        $fech->reaberto = 0;

        $procF = new EstoqueProcessor();
        $ok = $procF->fecharEstoque($fech);
        $this->assertTrue($ok, 'fecharEstoque falhou: ' . implode('; ', $procF->getErrors()));

        // O fechamento foi persistido.
        $this->assertNotNull($fech->id);

        // Consolidou o saldo do setor/produto (10 - 4 = 6) no fechamento.
        $linha = Estoquefechamentosetor::where('estoquefechamento_id', $fech->id)
            ->where('setor_id', $this->setor->id)
            ->where('produto_id', $this->produto->id)
            ->first();
        $this->assertNotNull($linha, 'estoquefechamentosetor não gerado');
        $this->assertEqualsWithDelta(6, (float) $linha->quantidade, 0.0001);
    }

    public function testFechamentoSemMovimentacaoFalha()
    {
        $this->criarCenarioFiscal();

        $fech = new Estoquefechamento();
        $fech->grupo_id = $this->empresa->grupo_id;
        $fech->empresa_id = $this->empresa->id;
        $fech->datahorafechamento = Carbon::now();
        $fech->reaberto = 0;

        $proc = new EstoqueProcessor();
        $ok = $proc->fecharEstoque($fech);

        $this->assertFalse($ok);
        $this->assertNotEmpty($proc->getErrors());
    }
}
