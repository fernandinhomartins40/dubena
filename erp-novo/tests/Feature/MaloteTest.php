<?php

namespace Tests\Feature;

use App\Domain\Caixa\MaloteService;
use App\Domain\Pedido\EfeitoPedido;
use App\Models\Caixa\Conta;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * T4.3 — fechamento de malote (acerto de valores do entregador).
 *
 * ⚠️ Esta funcionalidade é **condicionada à decisão do dono**: o plano exige
 * confirmar se o acerto físico ainda acontece na operação. Os testes existem
 * para que, se a resposta for "sim", o código esteja provado — e para que a
 * regra extraída do legado fique registrada de forma verificável.
 *
 * A regra que eles fixam: o malote **confere e baixa**, e nada mais. No legado
 * o `store()` mexia no status do pedido junto com a baixa, o que impedia
 * refazer a conferência sem efeito colateral.
 */
class MaloteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa,2:Conta} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        // Ator do cenário: precisa de papel real (a factory concede). O usuário
        // sem permissão é criado nos testes que exercitam a negação.
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        $conta = Conta::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Malote', 'tipo' => 'CAIXA',
            'saldo_inicial' => 0, 'saldo_atual' => 0, 'fechado' => false, 'ativo' => true,
        ]);

        return [$user, $empresa, $conta];
    }

    /** @var array<string,PedidoSituacao> */
    private array $situacoes = [];

    /**
     * Situação de pedido com o efeito pedido (o campo é NOT NULL).
     *
     * Memoizada: a descrição é unique por grupo, e criar uma por pedido faria
     * o segundo pedido do mesmo teste violar a constraint.
     */
    private function situacao(Empresa $e, EfeitoPedido $efeito): PedidoSituacao
    {
        $chave = $e->grupo_id.':'.$efeito->value;

        return $this->situacoes[$chave] ??= PedidoSituacao::create([
            'grupo_id' => $e->grupo_id,
            'descricao' => $efeito->value,
            'efeito' => $efeito->value,
            'ativo' => true,
        ]);
    }

    /** Pedido com título e uma parcela em aberto (ou baixada). */
    private function pedido(Empresa $e, float $valor, bool $baixada = false, array $extra = []): Pedido
    {
        $cliente = Cliente::create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'nome' => 'Cliente '.uniqid(), 'cliente' => true,
        ]);

        $titulo = Financeiro::create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'cliente_id' => $cliente->id, 'pagarreceber' => 'R',
            'descricao' => 'Venda', 'valor' => $valor,
            'data_emissao' => now(), 'cancelado' => $extra['cancelado'] ?? false,
        ]);

        FinanceiroParcela::create([
            'financeiro_id' => $titulo->id, 'numero' => 1,
            'vencimento' => now()->toDateString(), 'valor' => $valor,
            'baixado' => $baixada,
            'valor_efetivado' => $baixada ? $valor : 0,
            'datahora_baixa' => $baixada ? now() : null,
        ]);

        return Pedido::create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'cliente_id' => $cliente->id,
            'financeiro_id' => $titulo->id,
            'datahora' => $extra['datahora'] ?? now(),
            'valor_venda' => $valor,
            'entregador_user_id' => $extra['entregador_user_id'] ?? null,
            'setor_id' => $extra['setor_id'] ?? null,
            'pedidosituacao_id' => ($extra['situacao'] ?? $this->situacao($e, EfeitoPedido::CONCLUIDO))->id,
        ]);
    }

    // ── Conferência ──────────────────────────────────────────────────────────

    public function test_conferencia_lista_pedidos_do_periodo_com_valor_a_baixar(): void
    {
        [, $empresa] = $this->cenario();
        $this->pedido($empresa, 120.00);
        $this->pedido($empresa, 80.00);

        $r = app(MaloteService::class)->conferir(
            $empresa->id, now()->toDateString(), now()->toDateString(),
        );

        $this->assertSame(2, $r['totais']['pedidos']);
        $this->assertSame(200.00, $r['totais']['valor_total']);
        $this->assertSame(200.00, $r['totais']['valor_a_baixar']);
    }

    public function test_pedido_ja_baixado_aparece_mas_nao_soma_no_a_baixar(): void
    {
        [, $empresa] = $this->cenario();
        $this->pedido($empresa, 100.00, baixada: true);

        $r = app(MaloteService::class)->conferir(
            $empresa->id, now()->toDateString(), now()->toDateString(),
        );

        // O conferente precisa VER que o pedido existe — se sumisse da lista,
        // ele acharia que faltou entrega.
        $this->assertSame(1, $r['totais']['pedidos']);
        $this->assertSame(100.00, $r['totais']['valor_total']);
        $this->assertSame(0.0, $r['totais']['valor_a_baixar']);
        $this->assertTrue($r['pedidos'][0]['ja_baixado']);
    }

    public function test_filtra_por_entregador(): void
    {
        [$user, $empresa] = $this->cenario();
        $outro = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->pedido($empresa, 50.00, extra: ['entregador_user_id' => $user->id]);
        $this->pedido($empresa, 70.00, extra: ['entregador_user_id' => $outro->id]);

        $r = app(MaloteService::class)->conferir(
            $empresa->id, now()->toDateString(), now()->toDateString(), null, $user->id,
        );

        // O malote é o acerto de UM entregador: misturar dois faria o conferente
        // cobrar de quem não recebeu.
        $this->assertSame(1, $r['totais']['pedidos']);
        $this->assertSame(50.00, $r['totais']['valor_total']);
    }

    public function test_fora_do_periodo_nao_entra(): void
    {
        [, $empresa] = $this->cenario();
        $this->pedido($empresa, 90.00, extra: ['datahora' => now()->subDays(5)]);

        $r = app(MaloteService::class)->conferir(
            $empresa->id, now()->toDateString(), now()->toDateString(),
        );

        $this->assertSame(0, $r['totais']['pedidos']);
    }

    // ── Fechamento ───────────────────────────────────────────────────────────

    public function test_fechar_baixa_as_parcelas_na_conta(): void
    {
        [$user, $empresa, $conta] = $this->cenario();
        $p1 = $this->pedido($empresa, 120.00);
        $p2 = $this->pedido($empresa, 80.00);

        $r = app(MaloteService::class)->fechar($empresa->id, [$p1->id, $p2->id], $conta->id, $user->id);

        $this->assertSame(2, $r['baixadas']);
        $this->assertSame(200.00, $r['valor']);
        $this->assertSame(0, FinanceiroParcela::where('baixado', false)->count());
    }

    public function test_fechar_nao_altera_o_pedido(): void
    {
        [$user, $empresa, $conta] = $this->cenario();
        $pedido = $this->pedido($empresa, 60.00);
        // Compara contra o estado JÁ PERSISTIDO: o objeto recém-criado tem
        // `estoque_movimentado` null em memória e false ao reler, e essa
        // diferença é do cast, não uma alteração do malote.
        $campos = ['pedidosituacao_id', 'valor_venda', 'estoque_movimentado'];
        $antes = $pedido->fresh()->only($campos);

        app(MaloteService::class)->fechar($empresa->id, [$pedido->id], $conta->id, $user->id);

        // No legado, fechar mexia no status do pedido junto com a baixa — o que
        // tornava impossível refazer a conferência sem efeito colateral.
        $this->assertSame($antes, $pedido->fresh()->only($campos));
    }

    public function test_pedido_ja_baixado_e_ignorado_sem_travar_o_fechamento(): void
    {
        [$user, $empresa, $conta] = $this->cenario();
        $aberto = $this->pedido($empresa, 100.00);
        $fechado = $this->pedido($empresa, 50.00, baixada: true);

        $r = app(MaloteService::class)->fechar(
            $empresa->id, [$aberto->id, $fechado->id], $conta->id, $user->id,
        );

        // Travar tudo por causa de um já acertado faria o conferente caçar o
        // pedido na lista. Reporta e segue.
        $this->assertSame(1, $r['baixadas']);
        $this->assertSame([$fechado->id], $r['ignorados']);
    }

    public function test_titulo_cancelado_nao_e_baixado(): void
    {
        [$user, $empresa, $conta] = $this->cenario();
        $pedido = $this->pedido($empresa, 200.00, extra: ['cancelado' => true]);

        // Baixar parcela de título estornado criaria receita que não existe.
        $this->expectException(ValidationException::class);
        app(MaloteService::class)->fechar($empresa->id, [$pedido->id], $conta->id, $user->id);
    }

    public function test_sem_conta_configurada_a_mensagem_e_acionavel(): void
    {
        [$user, $empresa] = $this->cenario();
        $pedido = $this->pedido($empresa, 30.00);

        try {
            app(MaloteService::class)->fechar($empresa->id, [$pedido->id], null, $user->id);
            $this->fail('deveria exigir conta');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Empresas', $e->errors()['conta_id'][0]);
        }
    }

    public function test_usa_a_conta_configurada_na_empresa(): void
    {
        [$user, $empresa, $conta] = $this->cenario();
        EmpresaConfig::create([
            'empresa_id' => $empresa->id,
            'dados' => ['maloteconta_id' => $conta->id],
        ]);
        $pedido = $this->pedido($empresa, 45.00);

        $r = app(MaloteService::class)->fechar($empresa->id, [$pedido->id], null, $user->id);

        $this->assertSame($conta->id, $r['conta_id']);
    }

    public function test_conta_de_outra_empresa_e_recusada(): void
    {
        [$user, $empresa] = $this->cenario();
        $outra = Empresa::factory()->create();
        $contaAlheia = Conta::create([
            'empresa_id' => $outra->id, 'grupo_id' => $outra->grupo_id,
            'descricao' => 'Alheia', 'tipo' => 'CAIXA',
            'saldo_inicial' => 0, 'saldo_atual' => 0, 'fechado' => false, 'ativo' => true,
        ]);
        $pedido = $this->pedido($empresa, 10.00);

        $this->expectException(ValidationException::class);
        app(MaloteService::class)->fechar($empresa->id, [$pedido->id], $contaAlheia->id, $user->id);
    }

    // ── API ──────────────────────────────────────────────────────────────────

    public function test_endpoints_respondem_e_exigem_permissao(): void
    {
        [$user, $empresa, $conta] = $this->cenario();
        $pedido = $this->pedido($empresa, 25.00);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/malotes/conferencia?inicio='.now()->toDateString().'&fim='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.totais.pedidos', 1);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/malotes/fechar', ['pedidos' => [$pedido->id], 'conta_id' => $conta->id])
            ->assertOk()
            ->assertJsonPath('data.baixadas', 1);

        $leitor = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $this->actingAs($leitor, 'sanctum')
            ->postJson('/api/admin/malotes/fechar', ['pedidos' => [$pedido->id], 'conta_id' => $conta->id])
            ->assertStatus(403);
    }
}
