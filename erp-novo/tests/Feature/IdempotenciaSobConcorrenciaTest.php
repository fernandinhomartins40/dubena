<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Fiscal\NfEntradaService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Satelite\ComodatoService;
use App\Domain\Tenant\TenantContext;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueHistorico;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\Financeiro;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\ComodatoMovimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F4-01 — "rerun não duplica" sob CONCORRÊNCIA, não só em sequência.
 *
 * Pedido, NF de entrada e comodato se diziam idempotentes por uma flag
 * (`estoque_movimentado`, `movimentou_estoque`, saldo em posse), mas a flag era
 * lida do model EM MEMÓRIA, carregado antes da transação e sem lock. Duas
 * requisições simultâneas — duplo clique, painel e app do entregador ao mesmo
 * tempo, reenvio do app após timeout — carregam o MESMO estado e passam as duas
 * pela checagem.
 *
 * A corrida fica determinística aqui com duas instâncias carregadas ANTES da
 * primeira operação: é exatamente o que cada requisição enxerga. Os testes de
 * idempotência que já existiam faziam `->refresh()` antes de repetir — o que a
 * requisição concorrente real não faz — e por isso nunca acusaram.
 */
class IdempotenciaSobConcorrenciaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Setor $setor;

    private Produto $produto;

    private Cliente $cliente;

    private EstoqueService $estoque;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($this->empresa->id, $this->empresa->grupo_id);
        $this->setor = Setor::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->produto = Produto::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id, 'preco_venda' => 100]);
        $this->cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id]);
        $this->estoque = app(EstoqueService::class);
        $this->estoque->entrada($this->setor->id, $this->produto->id, 100, 10.0);
    }

    private function saldo(): float
    {
        return $this->estoque->saldoDerivado($this->setor->id, $this->produto->id);
    }

    public function test_concluir_o_mesmo_pedido_em_duas_requisicoes_baixa_uma_vez(): void
    {
        $service = app(PedidoService::class);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $concluido = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->empresa->grupo_id]);

        $pedido = $service->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'pedidosituacao_id' => $pendente->id, 'setor_id' => $this->setor->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 10, 'preco_unitario' => 100]]);

        // Duas requisições: cada uma carregou o pedido ANTES de a outra gravar.
        $requisicaoA = Pedido::query()->findOrFail($pedido->id);
        $requisicaoB = Pedido::query()->findOrFail($pedido->id);

        $service->mudarSituacao($requisicaoA, $concluido->id);
        $service->mudarSituacao($requisicaoB, $concluido->id);

        $this->assertEqualsWithDelta(90, $this->saldo(), 0.001, 'o estoque foi baixado duas vezes para o mesmo pedido');
        $this->assertSame(1, Financeiro::query()->where('origem', 'pedido')->where('origem_id', $pedido->id)->count(),
            'o financeiro do pedido foi gerado duas vezes');
    }

    public function test_concluir_cancelar_e_concluir_de_novo_continua_movimentando(): void
    {
        // A trava não pode engolir um ciclo LEGÍTIMO: é a diferença entre
        // repetição e evento novo.
        $service = app(PedidoService::class);
        $pendente = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)->create(['grupo_id' => $this->empresa->grupo_id]);
        $concluido = PedidoSituacao::factory()->efeito(EfeitoPedido::CONCLUIDO)->create(['grupo_id' => $this->empresa->grupo_id]);
        $cancelado = PedidoSituacao::factory()->efeito(EfeitoPedido::CANCELADO)->create(['grupo_id' => $this->empresa->grupo_id]);

        $pedido = $service->criar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'pedidosituacao_id' => $pendente->id, 'setor_id' => $this->setor->id,
        ], [['produto_id' => $this->produto->id, 'quantidade' => 10, 'preco_unitario' => 100]]);

        $service->mudarSituacao($pedido, $concluido->id);
        $service->mudarSituacao($pedido, $cancelado->id);
        $this->assertEqualsWithDelta(100, $this->saldo(), 0.001);

        $service->mudarSituacao($pedido, $concluido->id);
        $this->assertEqualsWithDelta(90, $this->saldo(), 0.001);
    }

    public function test_processar_a_mesma_nf_de_entrada_em_duas_requisicoes_entra_uma_vez(): void
    {
        $svc = app(NfEntradaService::class);
        $chave = str_pad('352', 44, '7');
        $xml = <<<XML
        <nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><NFe><infNFe Id="NFe{$chave}" versao="4.00">
          <ide><nNF>77</nNF><serie>1</serie><dhEmi>2026-06-01T10:00:00-03:00</dhEmi></ide>
          <emit><CNPJ>12345678000199</CNPJ><xNome>Fornecedor</xNome></emit>
          <det nItem="1"><prod><cProd>{$this->produto->id}</cProd><xProd>{$this->produto->descricao}</xProd><NCM>27111910</NCM>
            <CFOP>1102</CFOP><qCom>10</qCom><vUnCom>90</vUnCom><vProd>900.00</vProd></prod></det>
          <total><ICMSTot><vProd>900.00</vProd><vNF>900.00</vNF></ICMSTot></total>
        </infNFe></NFe></nfeProc>
        XML;
        $nota = $svc->importarXml($this->empresa->id, $this->empresa->grupo_id, $xml);
        $this->assertNotNull($nota->itens->first()->produto_id, 'pré-condição: o item precisa casar com o produto');

        $requisicaoA = $nota->fresh()->load('itens');
        $requisicaoB = $nota->fresh()->load('itens');

        $svc->processar($requisicaoA, $this->setor->id);
        $svc->processar($requisicaoB, $this->setor->id);

        $this->assertEqualsWithDelta(110, $this->saldo(), 0.001, 'a NF de entrada deu entrada duas vezes');
        $this->assertSame(1, Financeiro::query()->where('origem', 'nf_entrada')->where('origem_id', $nota->id)->count(),
            'a conta a pagar foi gerada duas vezes');
    }

    private function comodato(float $qtd): Comodato
    {
        return app(ComodatoService::class)->emprestar([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $this->cliente->id, 'produto_id' => $this->produto->id,
            'setor_id' => $this->setor->id, 'quantidade' => $qtd,
        ]);
    }

    public function test_duas_devolucoes_simultaneas_nao_devolvem_mais_que_o_emprestado(): void
    {
        $svc = app(ComodatoService::class);
        $comodato = $this->comodato(5); // estoque 95

        $requisicaoA = Comodato::query()->findOrFail($comodato->id);
        $requisicaoB = Comodato::query()->findOrFail($comodato->id);

        $svc->devolver($requisicaoA, 3);

        try {
            $svc->devolver($requisicaoB, 3);
            $this->fail('a segunda devolução de 3 com só 2 pendentes deveria ser recusada');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertEqualsWithDelta(98, $this->saldo(), 0.001, 'entrou em estoque vasilhame que não estava com o cliente');
        $this->assertEqualsWithDelta(3, (float) $comodato->refresh()->quantidade_devolvida, 0.001);
    }

    public function test_estornar_a_mesma_devolucao_em_duas_requisicoes_estorna_uma_vez(): void
    {
        $svc = app(ComodatoService::class);
        $comodato = $this->comodato(5);
        $svc->devolver($comodato, 2);
        $devolucao = ComodatoMovimento::query()->where('comodato_id', $comodato->id)
            ->where('tipo', ComodatoMovimento::DEVOLUCAO)->firstOrFail();

        $requisicaoA = ComodatoMovimento::query()->findOrFail($devolucao->id);
        $requisicaoB = ComodatoMovimento::query()->findOrFail($devolucao->id);

        $svc->estornar($requisicaoA);
        try {
            $svc->estornar($requisicaoB);
            $this->fail('a mesma devolução não pode ser estornada duas vezes');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertSame(1, ComodatoMovimento::query()->where('estorna_id', $devolucao->id)->count());
        $this->assertEqualsWithDelta(95, $this->saldo(), 0.001);
    }

    public function test_dois_acrescimos_simultaneos_somam_os_dois_no_contrato(): void
    {
        // Os dois acréscimos são LEGÍTIMOS (operadores diferentes). O defeito
        // era o contrato registrar só um enquanto o estoque baixava os dois:
        // a custódia passava a divergir do estoque sem causa visível.
        $svc = app(ComodatoService::class);
        $comodato = $this->comodato(5);

        $requisicaoA = Comodato::query()->findOrFail($comodato->id);
        $requisicaoB = Comodato::query()->findOrFail($comodato->id);

        $svc->acrescentar($requisicaoA, 2);
        $svc->acrescentar($requisicaoB, 3);

        $this->assertEqualsWithDelta(10, (float) $comodato->refresh()->quantidade, 0.001);
        $this->assertEqualsWithDelta(90, $this->saldo(), 0.001);
    }

    public function test_entrada_da_nf_grava_chave_de_idempotencia_por_item(): void
    {
        // Defesa no BANCO, além do lock: o índice único parcial recusa o
        // segundo movimento do mesmo item mesmo que alguém contorne o serviço.
        $svc = app(NfEntradaService::class);
        $chave = str_pad('353', 44, '7');
        $xml = <<<XML
        <nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><NFe><infNFe Id="NFe{$chave}" versao="4.00">
          <ide><nNF>78</nNF><serie>1</serie><dhEmi>2026-06-01T10:00:00-03:00</dhEmi></ide>
          <emit><CNPJ>12345678000199</CNPJ><xNome>Fornecedor</xNome></emit>
          <det nItem="1"><prod><cProd>{$this->produto->id}</cProd><xProd>{$this->produto->descricao}</xProd><NCM>27111910</NCM>
            <CFOP>1102</CFOP><qCom>4</qCom><vUnCom>90</vUnCom><vProd>360.00</vProd></prod></det>
          <total><ICMSTot><vProd>360.00</vProd><vNF>360.00</vNF></ICMSTot></total>
        </infNFe></NFe></nfeProc>
        XML;
        $nota = $svc->importarXml($this->empresa->id, $this->empresa->grupo_id, $xml);
        $svc->processar($nota, $this->setor->id);

        $item = $nota->itens->first();
        $this->assertSame(1, EstoqueHistorico::query()->where('chave_idempotencia', "nf-entrada-item:{$item->id}")->count());
    }
}
