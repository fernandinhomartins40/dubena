<?php

namespace Tests\Feature;

use App\Domain\Mobile\Contracts\PagamentoDriver;
use App\Domain\Mobile\Drivers\EredeDriver;
use App\Domain\Mobile\PagamentoOnlineService;
use App\Domain\Mobile\SituacaoPagamento;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * F6-08 — rede indisponível é diferente de recusa de negócio.
 *
 * ## O defeito
 *
 * `EredeDriver::autorizar` tinha um `catch (\Throwable)` que devolvia
 * `aprovado => false`. Isso significa que **queda de rede virava "cartão
 * recusado"** — e o `PagamentoOnlineService` gravava `NEGADO`, um estado
 * terminal.
 *
 * ## Por que isso custa dinheiro do cliente
 *
 * Um timeout costuma acontecer **depois** que a operadora autorizou: é a
 * resposta que se perde no caminho, não o pedido. Então o quadro real é:
 *
 *  1. a operadora aprova e debita o cartão;
 *  2. a resposta não chega;
 *  3. o sistema grava NEGADO e a tela diz "recusado";
 *  4. o operador tenta de novo — **e o cliente é cobrado duas vezes**.
 *
 * O mesmo vale para HTTP 5xx: erro no servidor **dela** não é decisão sobre a
 * transação, e o pedido pode ter sido processado antes do erro.
 *
 * ## Por que um estado próprio, e não uma flag
 *
 * A ação é diferente. Recusa se resolve com outro cartão, ali na hora.
 * Indeterminado se resolve **consultando a operadora** antes de qualquer nova
 * tentativa. Tratar os dois igual leva à ação errada nos dois casos.
 *
 * `aprovado` continua `false` no caso indeterminado: não se entrega mercadoria
 * sobre uma dúvida. O que muda é que a dúvida fica registrada em vez de
 * encerrada como recusa.
 */
class FalhaDeRedeNaoERecusaTest extends TestCase
{
    use RefreshDatabase;

    private function pedido(): Pedido
    {
        $empresa = Empresa::factory()->create();
        $setor = Setor::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $empresa->grupo_id]);

        return app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
            'setor_id' => $setor->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 1]]);
    }

    /** Troca o driver por um que devolve exatamente o desfecho pedido. */
    private function comDriver(bool $aprovado, bool $indeterminado): void
    {
        $this->app->bind(PagamentoDriver::class, fn () => new class($aprovado, $indeterminado) implements PagamentoDriver
        {
            public function __construct(private bool $aprovado, private bool $indeterminado) {}

            public function gateway(): string
            {
                return 'teste';
            }

            public function autorizar(array $dados): array
            {
                return [
                    'aprovado' => $this->aprovado,
                    'indeterminado' => $this->indeterminado,
                    'tid' => null, 'nsu' => null, 'autorizacao' => null, 'bandeira' => null,
                    'mensagem' => 'x',
                ];
            }

            public function estornar(string $tid): array
            {
                return ['cancelado' => true, 'mensagem' => 'ok'];
            }
        });
    }

    // ── O driver ──────────────────────────────────────────────────────────

    /** Conexão recusada: indeterminado, não recusado. */
    public function test_queda_de_rede_devolve_indeterminado(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $r = (new EredeDriver)->autorizar(['valor' => 100.0, 'parcelas' => 1, 'token' => 'tok']);

        $this->assertFalse($r['aprovado'], 'não se entrega mercadoria sobre uma dúvida');
        $this->assertTrue($r['indeterminado'], 'mas a dúvida precisa ficar registrada');
        $this->assertStringContainsString('não respondeu', $r['mensagem']);
    }

    /** HTTP 500: erro no servidor DELA também não é decisão sobre a transação. */
    public function test_erro_de_servidor_devolve_indeterminado(): void
    {
        Http::fake(fn () => Http::response(['erro' => 'interno'], 500));

        $r = (new EredeDriver)->autorizar(['valor' => 100.0, 'parcelas' => 1, 'token' => 'tok']);

        $this->assertFalse($r['aprovado']);
        $this->assertTrue($r['indeterminado']);
    }

    /**
     * Recusa DE VERDADE continua sendo recusa.
     *
     * Este é o contraponto necessário: se tudo virasse indeterminado, o estado
     * novo não distinguiria nada e a operação ficaria cheia de casos pendentes
     * que são simplesmente cartões sem limite.
     */
    public function test_recusa_da_operadora_continua_sendo_recusa(): void
    {
        Http::fake(fn () => Http::response([
            'returnCode' => '51', 'returnMessage' => 'Saldo insuficiente',
        ], 200));

        $r = (new EredeDriver)->autorizar(['valor' => 100.0, 'parcelas' => 1, 'token' => 'tok']);

        $this->assertFalse($r['aprovado']);
        $this->assertFalse($r['indeterminado'], 'a operadora RESPONDEU: isso é decisão, não dúvida');
        $this->assertStringContainsString('Saldo insuficiente', $r['mensagem']);
    }

    /** Aprovação continua aprovando. */
    public function test_aprovacao_continua_aprovando(): void
    {
        Http::fake(fn () => Http::response([
            'returnCode' => '00', 'returnMessage' => 'Aprovado', 'tid' => 'T1', 'nsu' => 'N1',
        ], 200));

        $r = (new EredeDriver)->autorizar(['valor' => 100.0, 'parcelas' => 1, 'token' => 'tok']);

        $this->assertTrue($r['aprovado']);
        $this->assertFalse($r['indeterminado']);
        $this->assertSame('T1', $r['tid']);
    }

    // ── O serviço ─────────────────────────────────────────────────────────

    /**
     * O estado indeterminado chega ao registro — é o que mantém a pergunta em
     * aberto até alguém consultar a operadora.
     */
    public function test_pagamento_indeterminado_nao_e_gravado_como_negado(): void
    {
        $this->comDriver(aprovado: false, indeterminado: true);
        $pedido = $this->pedido();

        $pagamento = app(PagamentoOnlineService::class)
            ->cobrarPedido($pedido, ['token' => 'tok', 'parcelas' => 1]);

        // O model casta `situacao` para o enum — compara-se o caso, não a string.
        $this->assertSame(SituacaoPagamento::INDETERMINADO, $pagamento->situacao);
        $this->assertTrue($pagamento->situacao->indeterminado());
        $this->assertFalse($pagamento->situacao->aprovado(), 'e não vale como aprovado');
    }

    /** Recusa real segue gravando NEGADO. */
    public function test_recusa_real_grava_negado(): void
    {
        $this->comDriver(aprovado: false, indeterminado: false);
        $pedido = $this->pedido();

        $pagamento = app(PagamentoOnlineService::class)
            ->cobrarPedido($pedido, ['token' => 'tok', 'parcelas' => 1]);

        $this->assertSame(SituacaoPagamento::NEGADO, $pagamento->situacao);
    }

    /** E a aprovação, AUTORIZADO. */
    public function test_aprovacao_grava_autorizado(): void
    {
        $this->comDriver(aprovado: true, indeterminado: false);
        $pedido = $this->pedido();

        $pagamento = app(PagamentoOnlineService::class)
            ->cobrarPedido($pedido, ['token' => 'tok', 'parcelas' => 1]);

        $this->assertSame(SituacaoPagamento::AUTORIZADO, $pagamento->situacao);
        $this->assertTrue($pagamento->situacao->aprovado());
    }

    /**
     * Driver antigo, que não sabe de `indeterminado`, continua funcionando.
     *
     * O campo é opcional no contrato de propósito: um driver de terceiro que só
     * devolva `aprovado` não pode quebrar — e, na dúvida, o comportamento é o
     * conservador de antes (recusa), não o novo.
     */
    public function test_driver_sem_o_campo_novo_nao_quebra(): void
    {
        $this->app->bind(PagamentoDriver::class, fn () => new class implements PagamentoDriver
        {
            public function gateway(): string
            {
                return 'antigo';
            }

            public function autorizar(array $dados): array
            {
                return [
                    'aprovado' => false, 'tid' => null, 'nsu' => null,
                    'autorizacao' => null, 'bandeira' => null, 'mensagem' => 'recusado',
                ];
            }

            public function estornar(string $tid): array
            {
                return ['cancelado' => true, 'mensagem' => 'ok'];
            }
        });

        $pagamento = app(PagamentoOnlineService::class)
            ->cobrarPedido($this->pedido(), ['token' => 'tok', 'parcelas' => 1]);

        $this->assertSame(SituacaoPagamento::NEGADO, $pagamento->situacao);
    }
}
