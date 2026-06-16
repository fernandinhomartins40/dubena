<?php

namespace Tests\Caracterizacao;

use Tests\TestCase;
use Tests\Caracterizacao\Support\FixturesFiscais;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use App\Processors\financeiroProcessor;
use App\Financeiro;
use App\Financeiroparcela;
use App\Contamovimento;
use App\Conta;

/**
 * Caracterização (golden master) — FASE 1, leva 4.
 *
 * Exercita o ciclo COMPLETO de baixa de um título a receber: financeiroProcessor
 * ::gravar() com baixar=true → caixaProcessor (validarBaixaTitulos + receberCaixa
 * + movimentarCaixa). Fixa o efeito: a parcela vira baixado=true com
 * valorefetivado aplicado, um contamovimento é gerado e o saldo da conta sobe.
 *
 * Cadeia de fixtures: usuário autenticado + conta-caixa (Contauser operar=1) +
 * fechamento aberto + condição de pagamento à vista (tipo 0, evita fluxo cartão).
 *
 * PHPUnit 7.5 / Laravel 5.4 / PHP 7.4.
 */
class BaixaTituloTest extends TestCase
{
    use DatabaseTransactions;
    use FixturesFiscais;

    protected function setUp(): void
    {
        parent::setUp();
        // A lib de auditoria (venturecraft/revisionable) chama Event::fire(), método
        // removido na versão de framework instalada — quebra ao salvar entidades
        // auditadas. Desligamos o event dispatcher do Eloquent durante o teste
        // (técnica de teste; não altera produção) para isolar o motor de baixa.
        \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
    }

    public function testBaixaDeTituloAReceberMarcaParcelaGeraMovimentoEAtualizaSaldo()
    {
        $this->criarCenarioFiscal();
        $user = $this->criarUsuarioLogado();
        $cliente = $this->criarCliente();
        $plano = $this->criarPlanoconta('R', '001');
        $centro = $this->criarCentrocusto('001');
        $conta = $this->criarContaCaixa($user, 0);   // saldo inicial 0
        $condicao = $this->criarCondicaoAVista();

        $req = new Request([
            'cliente_id'            => $cliente->id,
            'dataemissao'           => '15/01/2026',
            'datacompetencia'       => '15/01/2026',
            'datavencimento'        => '15/01/2026',
            'planoconta_id'         => $plano->id,
            'centrocusto_id'        => $centro->id,
            'pagarreceber'          => 'R',
            'descricao'             => 'Baixa Caracterização',
            'documento'             => 'DOC-BX',
            'valor'                 => '100,00',
            'contamovimentotipo_id' => null,
            'baixar'                => '1',
            'datahorabaixa'         => '15/01/2026 10:00:00',
            'conta_id'              => $conta->id,
            'origemAgrupar'         => null,
            'parcelasOrigem'        => null,
            'condicaopagamento_id'  => $condicao->id,
            'cartaonsu'             => null,
            'cartaoautorizacao'     => null,
        ]);

        $proc = new financeiroProcessor();
        $proc->setFinanceiroRequest($req);
        $proc->setRateiosRequest(json_encode([]));
        $proc->setParcelasRequest(json_encode(['desconto' => '0,00', 'data' => []]));
        $proc->setBaixar(true);
        $proc->setContaFechamentoId(-1); // não lança em fechado específico (caixa aberto)

        $ret = $proc->gravar();
        $this->assertNotFalse($ret, 'baixa falhou: ' . implode('; ', $proc->getErrors()));
        $this->assertEmpty($proc->getErrors());

        $fin = Financeiro::where('documento', 'DOC-BX')->first();
        $this->assertNotNull($fin);

        // Parcela baixada com o valor efetivado.
        $parcela = Financeiroparcela::where('financeiro_id', $fin->id)->first();
        $this->assertNotNull($parcela);
        $this->assertTrue((bool) $parcela->baixado, 'parcela deveria estar baixada');
        $this->assertEqualsWithDelta(100.0, (float) $parcela->valorefetivado, 0.0001);

        // Movimento de caixa gerado para a parcela.
        $movto = Contamovimento::where('financeiroparcela_id', $parcela->id)->first();
        $this->assertNotNull($movto, 'contamovimento não gerado');
        $this->assertEqualsWithDelta(100.0, (float) $movto->valorefetivado, 0.0001);
        $this->assertSame('R', $movto->pagarreceber);

        // Saldo da conta sobe em 100 (recebimento).
        $conta->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $conta->saldoatual, 0.0001);
    }
}
