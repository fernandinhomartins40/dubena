<?php

namespace Tests\Caracterizacao;

use Tests\TestCase;
use Tests\Caracterizacao\Support\FixturesFiscais;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use App\Processors\financeiroProcessor;
use App\Financeiro;
use App\Financeiroparcela;
use App\Financeirorateio;

/**
 * Caracterização (golden master) — FASE 1, leva 3.
 *
 * Exercita financeiroProcessor::gravar() SEM baixa (baixar=false): grava o
 * financeiro, suas parcelas e o rateio (centro/plano). Fixa o que o motor
 * persiste hoje, para a refatoração (Fase 4) não alterar. A baixa via
 * caixaProcessor (conta/fechamento/movimento) fica para leva posterior.
 *
 * PHPUnit 7.5 / Laravel 5.4 / PHP 7.4.
 */
class GravarFinanceiroTest extends TestCase
{
    use DatabaseTransactions;
    use FixturesFiscais;

    public function testGravarFinanceiroComParcelaUnicaERateio()
    {
        $this->criarCenarioFiscal();
        $cliente = $this->criarCliente();
        $plano = $this->criarPlanoconta('R', '001');
        $centro = $this->criarCentrocusto('001');

        $req = new Request([
            'cliente_id'          => $cliente->id,
            'dataemissao'         => '15/01/2026',
            'datacompetencia'     => '15/01/2026',
            'datavencimento'      => '15/02/2026',
            'planoconta_id'       => $plano->id,
            'centrocusto_id'      => $centro->id,
            'pagarreceber'        => 'R',
            'descricao'           => 'Lançamento Caracterização',
            'documento'           => 'DOC-1',
            'valor'               => '1.000,00',
            'contamovimentotipo_id' => null,
            'baixar'              => null,           // sem baixa
            'datahorabaixa'       => '',
            'conta_id'            => null,
            'origemAgrupar'       => null,
            'parcelasOrigem'      => null,
            'condicaopagamento_id' => '',
            'cartaonsu'           => null,
            'cartaoautorizacao'   => null,
        ]);

        $proc = new financeiroProcessor();
        $proc->setFinanceiroRequest($req);
        $proc->setRateiosRequest(json_encode([]));   // cai no rateio único (centro+plano)
        $proc->setParcelasRequest(json_encode(['desconto' => '0,00', 'data' => []])); // parcela única
        $proc->setBaixar(false);

        $ret = $proc->gravar();
        // gravar() retorna 'OK|' ou true conforme o tipoRetorno; o que importa é
        // que NÃO falhou (false) e não acumulou erros.
        $this->assertNotFalse($ret, 'gravar() falhou: ' . implode('; ', $proc->getErrors()));
        $this->assertEmpty($proc->getErrors());

        // Financeiro persistido com o valor convertido (1000.00).
        $fin = Financeiro::where('cliente_id', $cliente->id)
            ->where('documento', 'DOC-1')->first();
        $this->assertNotNull($fin);
        $this->assertEquals(1000.0, (float) $fin->valor, '', 0.0001);
        $this->assertSame('R', $fin->pagarreceber);

        // Uma parcela única no valor total.
        $parcelas = Financeiroparcela::where('financeiro_id', $fin->id)->get();
        $this->assertCount(1, $parcelas);
        $this->assertEquals(1000.0, (float) $parcelas[0]->valor, '', 0.0001);
        $this->assertEquals(1, (int) $parcelas[0]->numero);

        // Rateio único cobrindo 100% no centro/plano informados.
        $rateios = Financeirorateio::where('financeiro_id', $fin->id)->get();
        $this->assertCount(1, $rateios);
        $this->assertEquals((int) $centro->id, (int) $rateios[0]->centrocusto_id);
        $this->assertEquals((int) $plano->id, (int) $rateios[0]->planoconta_id);
        $this->assertEquals(1000.0, (float) $rateios[0]->valor, '', 0.0001);
    }
}
