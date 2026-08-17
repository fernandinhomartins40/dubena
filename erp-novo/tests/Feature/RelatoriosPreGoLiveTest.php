<?php

namespace Tests\Feature;

use App\Domain\Relatorio\RelatorioService;
use App\Models\Caixa\Conta;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE dos dois relatórios PRÉ-GO-LIVE da triagem (§5).
 *
 * - **Fluxo de caixa**: *"é o relatório que responde 'tenho dinheiro para pagar
 *   o quê'"*. Rotina financeira diária/semanal.
 * - **Clientes sem compra**: *"é a lista de quem parou de comprar. Base da
 *   venda ativa"*. Rotina comercial.
 *
 * O que os testes fixam além de "a query roda": as duas decisões que fazem o
 * relatório ser confiável — o fluxo parte do saldo REAL das contas (não de
 * zero) e ignora parcelas já baixadas (que já estão nesse saldo).
 */
class RelatoriosPreGoLiveTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        return [$user, $empresa];
    }

    private function conta(Empresa $e, float $saldo, bool $fechado = false): Conta
    {
        return Conta::create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'descricao' => 'Banco', 'tipo' => 'BANCO',
            'saldo_inicial' => 0, 'saldo_atual' => $saldo,
            'fechado' => $fechado, 'ativo' => true,
        ]);
    }

    /** Título com uma parcela vencendo em $dias, aberta ou baixada. */
    private function parcela(Empresa $e, string $pr, float $valor, int $dias, bool $baixada = false): void
    {
        $titulo = Financeiro::create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'pagarreceber' => $pr, 'descricao' => 'T', 'valor' => $valor,
            'data_emissao' => now(), 'cancelado' => false,
        ]);
        FinanceiroParcela::create([
            'financeiro_id' => $titulo->id, 'numero' => 1,
            'vencimento' => now()->addDays($dias)->toDateString(),
            'valor' => $valor,
            'baixado' => $baixada,
            'valor_efetivado' => $baixada ? $valor : 0,
            'datahora_baixa' => $baixada ? now() : null,
        ]);
    }

    // ── Fluxo de caixa ───────────────────────────────────────────────────────

    public function test_fluxo_parte_do_saldo_real_das_contas(): void
    {
        [, $empresa] = $this->cenario();
        $this->conta($empresa, 1000.00);

        $linhas = app(RelatorioService::class)->fluxoCaixa(
            $empresa->id, now()->toDateString(), now()->addDays(2)->toDateString(),
        );

        // Sem movimento nenhum, o saldo do dia 1 é o que está em conta hoje.
        // Começar em zero mostraria falta de caixa onde há dinheiro.
        $this->assertSame(1000.00, $linhas[0]['saldo_acumulado']);
    }

    public function test_saldo_acumula_dia_a_dia(): void
    {
        [, $empresa] = $this->cenario();
        $this->conta($empresa, 100.00);
        $this->parcela($empresa, 'R', 50.00, 1);   // entra amanhã
        $this->parcela($empresa, 'P', 30.00, 2);   // sai depois de amanhã

        $linhas = app(RelatorioService::class)->fluxoCaixa(
            $empresa->id, now()->toDateString(), now()->addDays(2)->toDateString(),
        );

        $this->assertCount(3, $linhas);
        $this->assertSame(100.00, $linhas[0]['saldo_acumulado']);
        $this->assertSame(150.00, $linhas[1]['saldo_acumulado']);
        $this->assertSame(120.00, $linhas[2]['saldo_acumulado']);
    }

    public function test_parcela_ja_baixada_nao_conta_de_novo(): void
    {
        [, $empresa] = $this->cenario();
        $this->conta($empresa, 500.00);
        $this->parcela($empresa, 'R', 200.00, 1, baixada: true);

        $linhas = app(RelatorioService::class)->fluxoCaixa(
            $empresa->id, now()->toDateString(), now()->addDays(1)->toDateString(),
        );

        // O dinheiro da parcela baixada JÁ está no saldo_atual. Somá-lo de novo
        // dobraria o caixa e faria o financeiro planejar com dinheiro que não existe.
        $this->assertSame(500.00, $linhas[1]['saldo_acumulado']);
    }

    public function test_conta_fechada_fica_de_fora_do_saldo_inicial(): void
    {
        [, $empresa] = $this->cenario();
        $this->conta($empresa, 800.00);
        $this->conta($empresa, 5000.00, fechado: true);

        $linhas = app(RelatorioService::class)->fluxoCaixa(
            $empresa->id, now()->toDateString(), now()->toDateString(),
        );

        // O saldo de uma conta fechada não está disponível para pagar nada.
        $this->assertSame(800.00, $linhas[0]['saldo_acumulado']);
    }

    public function test_dia_negativo_e_sinalizado(): void
    {
        [, $empresa] = $this->cenario();
        $this->conta($empresa, 100.00);
        $this->parcela($empresa, 'P', 500.00, 1);

        $linhas = app(RelatorioService::class)->fluxoCaixa(
            $empresa->id, now()->toDateString(), now()->addDays(1)->toDateString(),
        );

        // É a coluna que o financeiro procura de olho: o dia em que vira.
        $this->assertSame('OK', $linhas[0]['situacao']);
        $this->assertSame('NEGATIVO', $linhas[1]['situacao']);
        $this->assertSame(-400.00, $linhas[1]['saldo_acumulado']);
    }

    public function test_fluxo_sai_pelo_endpoint(): void
    {
        [$user, $empresa] = $this->cenario();
        $this->conta($empresa, 10.00);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/relatorios/fluxo-caixa?inicio='.now()->toDateString().'&fim='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.0.saldo_acumulado', 10);
    }

    // ── Clientes sem compra ──────────────────────────────────────────────────

    private function cliente(Empresa $e, string $nome, ?int $diasAtras): Cliente
    {
        return Cliente::create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'nome' => $nome, 'cliente' => true, 'ativo' => true,
            'data_ultima_compra' => $diasAtras === null ? null : now()->subDays($diasAtras)->toDateString(),
        ]);
    }

    public function test_lista_quem_passou_do_corte(): void
    {
        [, $empresa] = $this->cenario();
        $this->cliente($empresa, 'Comprou ontem', 1);
        $this->cliente($empresa, 'Sumiu faz tempo', 90);

        $linhas = app(RelatorioService::class)->clientesSemCompra($empresa->id, 60);

        $this->assertCount(1, $linhas);
        $this->assertSame('Sumiu faz tempo', $linhas[0]['cliente']);
    }

    public function test_quem_nunca_comprou_entra_na_lista(): void
    {
        [, $empresa] = $this->cenario();
        $this->cliente($empresa, 'Cadastrado e nunca voltou', null);

        $linhas = app(RelatorioService::class)->clientesSemCompra($empresa->id, 60);

        // Cadastro morto ou venda perdida na origem: nos dois casos o comercial
        // precisa ver.
        $this->assertCount(1, $linhas);
        $this->assertSame('Nunca comprou', $linhas[0]['ultima_compra']);
        $this->assertNull($linhas[0]['dias_sem_comprar']);
    }

    public function test_corte_de_dias_e_configuravel(): void
    {
        [, $empresa] = $this->cenario();
        $this->cliente($empresa, 'Parado ha 45 dias', 45);

        // O giro varia por perfil: comércio consome mais rápido que residência.
        $this->assertCount(0, app(RelatorioService::class)->clientesSemCompra($empresa->id, 60));
        $this->assertCount(1, app(RelatorioService::class)->clientesSemCompra($empresa->id, 30));
    }

    public function test_cliente_inativo_nao_entra(): void
    {
        [, $empresa] = $this->cenario();
        $c = $this->cliente($empresa, 'Desativado', 200);
        $c->update(['ativo' => false]);

        // Cliente desativado no cadastro não é venda perdida a recuperar.
        $this->assertCount(0, app(RelatorioService::class)->clientesSemCompra($empresa->id, 60));
    }

    public function test_endpoint_aceita_o_parametro_dias(): void
    {
        [$user, $empresa] = $this->cenario();
        $this->cliente($empresa, 'Parado ha 45 dias', 45);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/relatorios/clientes-sem-compra?dias=30')
            ->assertOk()
            ->assertJsonPath('data.0.cliente', 'Parado ha 45 dias');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/relatorios/clientes-sem-compra?dias=60')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_catalogo_declara_os_dois_relatorios(): void
    {
        [$user] = $this->cenario();

        $resposta = $this->actingAs($user, 'sanctum')->getJson('/api/admin/relatorios/catalogo')->assertOk();

        $slugs = collect($resposta->json('data'))->pluck('slug');
        $this->assertContains('fluxo-caixa', $slugs);
        $this->assertContains('clientes-sem-compra', $slugs);

        // A SPA precisa saber que este relatório tem um filtro extra a desenhar.
        $semCompra = collect($resposta->json('data'))->firstWhere('slug', 'clientes-sem-compra');
        $this->assertSame(['dias'], $semCompra['extras']);
    }
}
