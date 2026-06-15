<?php

namespace Tests\Caracterizacao;

use Tests\TestCase;
use Tests\Caracterizacao\Support\FixturesFiscais;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Processors\EstoqueProcessor;
use App\Estoquesetorhistorico;
use App\Estoquesetor;
use App\Estoqueproduto;
use App\Produto;
use App\Services\CarbonCustom as Carbon;

/**
 * Caracterização (golden master) — FASE 1, leva 2.
 *
 * Exercita o EstoqueProcessor::movimentarEstoque (orquestrador com estado) sobre
 * fixtures sintéticas e fixa o efeito mensurável: saldo do setor, saldo do
 * produto e custo médio. Garante que a refatoração (Fase 4) não altere o que o
 * motor grava hoje. DatabaseTransactions reverte tudo ao fim de cada teste.
 *
 * PHPUnit 7.5 / Laravel 5.4 / PHP 7.4.
 */
class MovimentacaoEstoqueTest extends TestCase
{
    use DatabaseTransactions;
    use FixturesFiscais;

    /** Monta um histórico de movimentação para o cenário criado. */
    private function historico($movimentacao, $quantidade)
    {
        $h = new Estoquesetorhistorico();
        $h->user_id = 1;
        $h->setor_id = $this->setor->id;
        $h->produto_id = $this->produto->id;
        $h->movimentacao = $movimentacao;
        $h->quantidade = $quantidade;
        $h->motivo = 'Caracterização';
        $h->datahora = Carbon::now();
        $h->datahoracompetencia = Carbon::now();
        $h->entidade = 'Teste';
        $h->entidade_id = 1;
        $h->grupo_id = $this->empresa->grupo_id;
        $h->empresa_id = $this->empresa->id;
        return $h;
    }

    /**
     * Sonda inicial: o cenário sintético é criado sem violar FK/NOT NULL e a
     * Session fica populada. Mantém-se mesmo se os demais cenários evoluírem.
     */
    public function testCenarioSinteticoMontaSemErro()
    {
        $this->criarCenarioFiscal();

        $this->assertNotNull($this->empresa->id);
        $this->assertNotNull($this->setor->id);
        $this->assertNotNull($this->produto->id);
        $this->assertEquals(0, (float) $this->produto->customedio);
    }

    /**
     * ENTRADA cria o estoque do setor/produto com a quantidade movimentada.
     */
    public function testEntradaCriaSaldoSetorEProduto()
    {
        $this->criarCenarioFiscal();
        $proc = new EstoqueProcessor();

        $ok = $proc->movimentarEstoque([$this->historico('ENTRADA', 10)]);

        $this->assertTrue($ok, implode('; ', $proc->getErrors()));

        $setor = Estoquesetor::where('produto_id', $this->produto->id)
            ->where('setor_id', $this->setor->id)->first();
        $produto = Estoqueproduto::where('produto_id', $this->produto->id)->first();

        $this->assertNotNull($setor);
        $this->assertEquals(10, (float) $setor->quantidade);
        $this->assertNotNull($produto);
        $this->assertEquals(10, (float) $produto->quantidade);
    }

    /**
     * ENTRADA seguida de SAÍDA resulta no saldo líquido (10 - 4 = 6).
     */
    public function testEntradaDepoisSaidaResultaSaldoLiquido()
    {
        $this->criarCenarioFiscal();
        $proc = new EstoqueProcessor();

        $this->assertTrue($proc->movimentarEstoque([$this->historico('ENTRADA', 10)]),
            implode('; ', $proc->getErrors()));

        $proc2 = new EstoqueProcessor();
        $this->assertTrue($proc2->movimentarEstoque([$this->historico('SAIDA', 4)]),
            implode('; ', $proc2->getErrors()));

        $setor = Estoquesetor::where('produto_id', $this->produto->id)
            ->where('setor_id', $this->setor->id)->first();
        $this->assertEquals(6, (float) $setor->quantidade);
    }

    /**
     * SAÍDA sem saldo, com empresa que NÃO permite negativar, falha (regra).
     */
    public function testSaidaSemSaldoSemNegativarFalha()
    {
        $this->criarCenarioFiscal(['permiteestoquenegativo' => 0]);
        $proc = new EstoqueProcessor();

        $ok = $proc->movimentarEstoque([$this->historico('SAIDA', 5)]);

        $this->assertFalse($ok);
        $this->assertNotEmpty($proc->getErrors());
    }
}
