<?php

namespace Tests\Feature;

use App\Domain\Caixa\CaixaService;
use App\Domain\Financeiro\ReconciliacaoFinanceira;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F5-10 — as invariantes do dinheiro, conferidas por empresa e período.
 *
 * ## O que existia
 *
 * `notify:inconsistencias` já comparava saldo de conta com movimentos. Faltavam
 * três coisas para atender a tarefa:
 *
 *  - **recorte por empresa e período**. Num SaaS, "há 3 inconsistências" não diz
 *    de quem; e sem período não se fecha um mês;
 *  - as outras dimensões que a tarefa nomeia — título, pagamento, documento;
 *  - **sair com falha**. Ele achava divergência e devolvia SUCCESS, então não
 *    servia de portão.
 *
 * ## Conferência nunca ajusta
 *
 * Mesmo princípio do F4, mesma razão: se a soma das parcelas não bate com o
 * título, ou o título está errado, ou falta parcela. Sobrescrever resolve a tela
 * e apaga a pergunta — e se a resposta era a segunda, o dinheiro some da
 * contabilidade sem rastro.
 *
 * Cada teste aqui **provoca** uma divergência à mão e verifica que ela é
 * encontrada. Um teste que só rodasse a conferência num banco saudável passaria
 * com um serviço que devolve lista vazia sempre.
 */
class ReconciliacaoFinanceiraTest extends TestCase
{
    use RefreshDatabase;

    private string $inicio = '2026-08-01';

    private string $fim = '2026-08-31';

    private function empresa(): Empresa
    {
        $empresa = Empresa::factory()->create();
        app(TenantContext::class)->set($empresa->id, $empresa->grupo_id);

        return $empresa;
    }

    /** Título com uma parcela, ambos coerentes. */
    private function tituloComParcela(Empresa $e, float $valor = 200.00): array
    {
        $titulo = Financeiro::create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'pagarreceber' => 'R', 'descricao' => 'Venda', 'valor' => $valor,
            'data_emissao' => '2026-08-10', 'cancelado' => false,
        ]);

        $parcela = FinanceiroParcela::create([
            'financeiro_id' => $titulo->id, 'numero' => 1,
            'vencimento' => '2026-08-20', 'valor' => $valor,
            'baixado' => false, 'valor_efetivado' => 0,
        ]);

        return [$titulo, $parcela];
    }

    private function divergencias(Empresa $e)
    {
        return app(ReconciliacaoFinanceira::class)->divergencias($e->id, $this->inicio, $this->fim);
    }

    /** O caso saudável: sem divergência inventada. */
    public function test_base_coerente_nao_acusa_nada(): void
    {
        $empresa = $this->empresa();
        $this->tituloComParcela($empresa);

        $this->assertTrue($this->divergencias($empresa)->isEmpty());
    }

    /**
     * Σ parcelas ≠ valor do título.
     *
     * Vem de parcelamento editado depois da criação: alguém muda o valor de uma
     * parcela e o título fica com o total antigo.
     */
    public function test_titulo_que_nao_bate_com_as_parcelas_e_acusado(): void
    {
        $empresa = $this->empresa();
        [, $parcela] = $this->tituloComParcela($empresa, 200.00);

        $parcela->update(['valor' => 150.00]);

        $achado = $this->divergencias($empresa)
            ->firstWhere('invariante', 'titulo_x_parcelas');

        $this->assertNotNull($achado);
        $this->assertSame(200.00, $achado['esperado']);
        $this->assertSame(150.00, $achado['obtido']);
        $this->assertSame(50.00, $achado['diferenca']);
    }

    /**
     * Parcela baixada sem data: recebimento que não cai em período nenhum, e
     * portanto some de qualquer DRE.
     */
    public function test_parcela_baixada_sem_data_e_acusada(): void
    {
        $empresa = $this->empresa();
        [, $parcela] = $this->tituloComParcela($empresa);

        // Escrita direta, simulando o estado sujo que se quer descobrir.
        DB::table('financeiroparcelas')->where('id', $parcela->id)->update([
            'baixado' => true, 'valor_efetivado' => 200.00, 'datahora_baixa' => null,
        ]);

        $achado = $this->divergencias($empresa)
            ->firstWhere('invariante', 'parcela_baixada_incompleta');

        $this->assertNotNull($achado);
        $this->assertSame('baixada sem data', $achado['observacao']);
    }

    /** Baixada sem valor: o sistema diz ter recebido sem dizer quanto. */
    public function test_parcela_baixada_sem_valor_e_acusada(): void
    {
        $empresa = $this->empresa();
        [, $parcela] = $this->tituloComParcela($empresa);

        DB::table('financeiroparcelas')->where('id', $parcela->id)->update([
            'baixado' => true, 'valor_efetivado' => 0, 'datahora_baixa' => now(),
        ]);

        $achado = $this->divergencias($empresa)
            ->firstWhere('invariante', 'parcela_baixada_incompleta');

        $this->assertNotNull($achado);
        $this->assertSame('baixada sem valor', $achado['observacao']);
    }

    /** Saldo da conta que não é a soma dos movimentos. */
    public function test_saldo_de_conta_que_nao_bate_e_acusado(): void
    {
        $empresa = $this->empresa();
        $conta = app(CaixaService::class)->criarConta([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Banco', 'saldo_inicial' => 0,
        ]);

        // Mexe no saldo sem movimento correspondente.
        DB::table('contas')->where('id', $conta->id)->update(['saldo_atual' => 500.00]);

        $achado = $this->divergencias($empresa)
            ->firstWhere('invariante', 'conta_x_movimentos');

        $this->assertNotNull($achado);
        $this->assertSame(500.00, $achado['esperado']);
        $this->assertSame(0.0, $achado['obtido']);
    }

    /**
     * Movimento de baixa apontando para parcela aberta.
     *
     * É o estorno pela metade: o caixa registrou a baixa e o financeiro não —
     * as duas metades contam histórias diferentes.
     */
    public function test_movimento_de_baixa_orfao_e_acusado(): void
    {
        $empresa = $this->empresa();
        [, $parcela] = $this->tituloComParcela($empresa);
        $caixa = app(CaixaService::class);

        $conta = $caixa->criarConta([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Caixa', 'saldo_inicial' => 0,
        ]);
        $caixa->baixarParcela($conta->id, $parcela->id, $empresa->id);

        // Reabre a parcela SEM o movimento de estorno — o estado sujo.
        DB::table('financeiroparcelas')->where('id', $parcela->id)->update([
            'baixado' => false, 'valor_efetivado' => 0, 'datahora_baixa' => null,
        ]);
        DB::table('contamovimentos')->where('empresa_id', $empresa->id)
            ->update(['datahora' => '2026-08-15 10:00:00']);

        $achado = $this->divergencias($empresa)
            ->firstWhere('invariante', 'movimento_de_baixa_orfao');

        $this->assertNotNull($achado, 'o caixa e o financeiro discordam');
    }

    /**
     * Um estorno LEGÍTIMO não vira falso positivo.
     *
     * Depois de um estorno a parcela fica aberta e o movimento de baixa
     * continua existindo — é o estado correto. Acusá-lo transformaria a
     * conferência em ruído, e ruído se desliga.
     */
    public function test_estorno_legitimo_nao_e_falso_positivo(): void
    {
        $empresa = $this->empresa();
        [, $parcela] = $this->tituloComParcela($empresa);
        $caixa = app(CaixaService::class);

        $conta = $caixa->criarConta([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Caixa', 'saldo_inicial' => 0,
        ]);
        $movimento = $caixa->baixarParcela($conta->id, $parcela->id, $empresa->id);
        $caixa->estornar($movimento->id, $empresa->id);

        DB::table('contamovimentos')->where('empresa_id', $empresa->id)
            ->update(['datahora' => '2026-08-15 10:00:00']);

        $this->assertNull(
            $this->divergencias($empresa)->firstWhere('invariante', 'movimento_de_baixa_orfao'),
            'o estorno deixa a parcela aberta de propósito',
        );
    }

    /** A fronteira do SaaS: a divergência de uma revenda não aparece na outra. */
    public function test_a_conferencia_nao_atravessa_empresas(): void
    {
        $comProblema = $this->empresa();
        [, $parcela] = $this->tituloComParcela($comProblema, 200.00);
        $parcela->update(['valor' => 150.00]);

        $limpa = $this->empresa();

        $this->assertTrue($this->divergencias($limpa)->isEmpty());
        $this->assertFalse($this->divergencias($comProblema)->isEmpty());
    }

    /** Fora do período não entra: é o que permite fechar um mês. */
    public function test_o_periodo_recorta_o_que_e_conferido(): void
    {
        $empresa = $this->empresa();
        [$titulo, $parcela] = $this->tituloComParcela($empresa, 200.00);
        $parcela->update(['valor' => 150.00]);

        $titulo->update(['data_emissao' => '2026-05-10']);

        $this->assertTrue(
            $this->divergencias($empresa)->isEmpty(),
            'título de maio não entra na conferência de agosto',
        );
    }

    /** O comando sai com FALHA quando há divergência — é o que faltava. */
    public function test_o_comando_reprova_quando_ha_divergencia(): void
    {
        $empresa = $this->empresa();
        [, $parcela] = $this->tituloComParcela($empresa, 200.00);
        $parcela->update(['valor' => 150.00]);

        $this->artisan('financeiro:conferir --inicio=2026-08-01 --fim=2026-08-31')
            ->expectsOutputToContain('divergência')
            ->assertFailed();
    }

    /** E passa quando tudo fecha. */
    public function test_o_comando_aprova_quando_tudo_fecha(): void
    {
        $empresa = $this->empresa();
        $this->tituloComParcela($empresa);

        $this->artisan('financeiro:conferir --inicio=2026-08-01 --fim=2026-08-31')
            ->expectsOutputToContain('tudo fecha')
            ->assertSuccessful();
    }

    /**
     * O comando NÃO ajusta nada.
     *
     * É a promessa central da tarefa, e a única forma de prová-la é conferir o
     * estado depois de rodar.
     */
    public function test_a_conferencia_nao_altera_nada(): void
    {
        $empresa = $this->empresa();
        [$titulo, $parcela] = $this->tituloComParcela($empresa, 200.00);
        $parcela->update(['valor' => 150.00]);

        $this->artisan('financeiro:conferir --inicio=2026-08-01 --fim=2026-08-31')->assertFailed();

        $this->assertSame('150.00', (string) $parcela->refresh()->valor, 'a parcela continua como estava');
        $this->assertSame('200.00', (string) $titulo->refresh()->valor, 'e o título também');
    }
}
