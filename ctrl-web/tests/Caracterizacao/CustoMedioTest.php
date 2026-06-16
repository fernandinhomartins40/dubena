<?php

namespace Tests\Caracterizacao;

use Tests\TestCase;
use App\Produto;

/**
 * Stub de Produto para caracterização: captura o customedio gravado por
 * Produto::updateCustoMedio SEM tocar no banco (sobrescreve update()).
 * Evita o Mockery (cuja geração por reflection dispara deprecated em PHP 7.4).
 */
class ProdutoCustoStub extends Produto
{
    public $customedioGravado = null;

    public function update(array $attributes = [], array $options = [])
    {
        if (array_key_exists('customedio', $attributes)) {
            $this->customedioGravado = $attributes['customedio'];
            $this->customedio = $attributes['customedio'];
        }
        return true;
    }
}

/**
 * Caracterização (golden master) — FASE 1.
 *
 * Fixa o comportamento ATUAL de Produto::updateCustoMedio (custo médio ponderado),
 * para que a refatoração do EstoqueProcessor (Fase 4) não altere os números.
 *
 * Sem dados reais: o método já roda em produção (homologada), então o valor que
 * ele produz HOJE é a referência. Estes testes quebram se o cálculo mudar.
 *
 * PHPUnit 7.5 / Laravel 5.4 / PHP 7.4.
 */
class CustoMedioTest extends TestCase
{
    private function stub($customedioAtual)
    {
        $p = new ProdutoCustoStub();
        $p->customedio = $customedioAtual;
        return $p;
    }

    /**
     * ENTRADA (estorno=false): entra qtde nova ao estoque, recalcula o médio.
     * customedio=10, estoque=100, entra 50 a 16 → (10*100 + 50*16)/150 = 12.0
     */
    public function testEntradaRecalculaCustoMedioPonderado()
    {
        $p = $this->stub(10.0);
        Produto::updateCustoMedio($p, 100, 50, 16.0, false);
        $this->assertEqualsWithDelta(12.0, $p->customedioGravado, 0.0001);
    }

    /**
     * SAÍDA/ESTORNO (estorno=true): remove qtde do estoque.
     * customedio=12, estoque=150, sai 50 a 16 → (12*150 - 50*16)/100 = 10.0
     */
    public function testEstornoRecalculaCustoMedio()
    {
        $p = $this->stub(12.0);
        Produto::updateCustoMedio($p, 150, 50, 16.0, true);
        $this->assertEqualsWithDelta(10.0, $p->customedioGravado, 0.0001);
    }

    /**
     * Estoque resultante <= 0 → custo médio zera (regra do método).
     * customedio=10, estoque=50, estorna 50 → qdeapos=0 → 0
     */
    public function testEstoqueZeradoZeraCustoMedio()
    {
        $p = $this->stub(10.0);
        Produto::updateCustoMedio($p, 50, 50, 16.0, true);
        $this->assertEqualsWithDelta(0, $p->customedioGravado, 0.0001);
    }

    /**
     * Custo médio inicial ZERO: qdeestoque vira 0 (regra), só a entrada conta.
     * customedio=0, estoque=100, entra 10 a 20 → (0 + 10*20)/10 = 20.0
     */
    public function testCustoMedioInicialZeroUsaApenasEntrada()
    {
        $p = $this->stub(0.0);
        Produto::updateCustoMedio($p, 100, 10, 20.0, false);
        $this->assertEqualsWithDelta(20.0, $p->customedioGravado, 0.0001);
    }
}
