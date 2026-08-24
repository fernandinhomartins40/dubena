<?php

namespace Tests\Feature;

use App\Domain\Satelite\ComodatoService;
use App\Domain\Satelite\GerarAlertasComodato;
use App\Domain\Satelite\VigilanciaComodatoService;
use App\Domain\Satelite\VinculoVasilhame;
use App\Models\Alerta;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Pedido\PedidoItem;
use App\Models\Produto\Produto;
use App\Models\Satelite\Comodato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vigilância do comodato: o vasilhame emprestado está rodando aqui?
 *
 * **A suspeita.** Cliente com comodato grande pode estar enchendo o botijão da
 * revenda na concorrência. O que estes testes fixam é a régua que separa isso de
 * um cliente que simplesmente consome pouco.
 *
 * Os números vêm da medição em produção (2026-08-24):
 *
 *     BRASILCOMP    12 vasilhames, 300 compras/180d → 25,0x  saudável
 *     RESIDENCIAL   25 vasilhames,   92            →  3,7x  desproporcional
 *     J ALEI        60 vasilhames,    0            →  0,0x  parado desde 2022
 */
class ComodatoVigilanciaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $user;

    private Produto $vasilhame;

    private Produto $gas;

    private PedidoSituacao $situacao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::factory()->create();
        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'support' => true,
        ]);

        // Espelha o cadastro real: o casco e o conteúdo são produtos distintos.
        $this->vasilhame = Produto::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Vasilha P13 Kg',
            'ativo' => true,
        ]);
        $this->gas = Produto::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Glp P13',
            'tipo_glp' => 3,
            'ativo' => true,
        ]);
        $this->situacao = PedidoSituacao::create([
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Concluído',
            'efeito' => 'CONCLUIDO',
            'ativo' => true,
        ]);
    }

    private function cliente(string $nome, bool $fornecedor = false): Cliente
    {
        return Cliente::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'nome' => $nome,
            'cliente' => true,
            'fornecedor' => $fornecedor,
        ]);
    }

    private function comodato(Cliente $c, float $qtd): Comodato
    {
        return app(ComodatoService::class)->emprestar([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'cliente_id' => $c->id,
            'produto_id' => $this->vasilhame->id,
            'quantidade' => $qtd,
        ], $this->user->id);
    }

    /** Cria N pedidos de `qtd` unidades cada, espalhados nos últimos `dias`. */
    private function comprar(Cliente $c, int $pedidos, float $qtd, int $dias = 150): void
    {
        for ($i = 0; $i < $pedidos; $i++) {
            $pedido = Pedido::create([
                'empresa_id' => $this->empresa->id,
                'grupo_id' => $this->empresa->grupo_id,
                'cliente_id' => $c->id,
                'pedidosituacao_id' => $this->situacao->id,
                'datahora' => now()->subDays((int) ($dias * ($i + 1) / ($pedidos + 1))),
                'valor_venda' => 100,
            ]);
            PedidoItem::create([
                'empresa_id' => $this->empresa->id,
                'pedido_id' => $pedido->id,
                'produto_id' => $this->gas->id,
                'quantidade' => $qtd,
                'preco_unitario' => 100,
                'valor_total' => 100 * $qtd,
            ]);
        }
    }

    public function test_cliente_com_giro_alto_fica_ok(): void
    {
        $c = $this->cliente('BRASILCOMP');
        $this->comodato($c, 12);
        // 300 unidades / 12 vasilhames = 25x
        $this->comprar($c, 60, 5);

        $a = app(VigilanciaComodatoService::class)->avaliarEmpresa($this->empresa->id);

        $this->assertCount(1, $a);
        $this->assertSame('OK', $a[0]->classificacao);
        $this->assertEqualsWithDelta(25.0, (float) $a[0]->giro, 0.5);
    }

    public function test_muito_vasilhame_e_pouca_compra_vira_atencao(): void
    {
        $c = $this->cliente('RESIDENCIAL CONRADINHO');
        $this->comodato($c, 25);
        // 92 unidades / 25 vasilhames = 3,7x — abaixo do mínimo de 4x.
        $this->comprar($c, 12, 7.67);

        $a = app(VigilanciaComodatoService::class)->avaliarEmpresa($this->empresa->id);

        $this->assertSame('ATENCAO', $a[0]->classificacao);
        $this->assertStringContainsString('abaixo do mínimo', (string) $a[0]->motivo);
    }

    public function test_comodato_sem_nenhuma_compra_e_critico(): void
    {
        $c = $this->cliente('J ALEI COMERCIO DE GAS');
        $this->comodato($c, 60);
        // Nenhum pedido: o caso mais grave da base real.

        $a = app(VigilanciaComodatoService::class)->avaliarEmpresa($this->empresa->id);

        $this->assertSame('CRITICO', $a[0]->classificacao);
        $this->assertStringContainsString('nenhuma compra', (string) $a[0]->motivo);
    }

    /**
     * O sinal mais forte: o cliente comprava muito e parou. Um limiar fixo não
     * pegaria — o giro dele ainda pode estar acima do mínimo.
     */
    public function test_queda_contra_o_proprio_historico_alerta_mesmo_com_giro_aceitavel(): void
    {
        $c = $this->cliente('MULTI PLY WOOD');
        $this->comodato($c, 10);

        // Histórico (12 meses antes da janela): comprava MUITO.
        for ($i = 0; $i < 40; $i++) {
            $pedido = Pedido::create([
                'empresa_id' => $this->empresa->id,
                'grupo_id' => $this->empresa->grupo_id,
                'cliente_id' => $c->id,
                'pedidosituacao_id' => $this->situacao->id,
                'datahora' => now()->subDays(200 + $i * 8),
                'valor_venda' => 100,
            ]);
            PedidoItem::create([
                'empresa_id' => $this->empresa->id,
                'pedido_id' => $pedido->id,
                'produto_id' => $this->gas->id,
                'quantidade' => 30,
                'preco_unitario' => 100,
                'valor_total' => 3000,
            ]);
        }

        // Janela atual: caiu para uma fração.
        $this->comprar($c, 6, 10);

        $a = app(VigilanciaComodatoService::class)->avaliarEmpresa($this->empresa->id);

        $this->assertNotNull($a[0]->baseline_giro);
        $this->assertTrue($a[0]->preocupante(), 'A queda contra o próprio histórico tem de alertar.');
        $this->assertStringContainsString('caiu', (string) $a[0]->motivo);
    }

    /**
     * A distribuidora aparece com 5.633 vasilhames em comodato — mas é o
     * comodato DELA para a revenda, direção oposta. Seria o maior falso
     * positivo da base.
     */
    public function test_fornecedor_nunca_e_vigiado(): void
    {
        $f = $this->cliente('SUPERGASBRAS ENERGIA LTDA', fornecedor: true);
        $this->comodato($f, 5633);

        $a = app(VigilanciaComodatoService::class)->avaliarEmpresa($this->empresa->id);

        $this->assertSame([], $a);
    }

    public function test_comodato_pequeno_nao_gera_ruido(): void
    {
        $c = $this->cliente('Dona Maria');
        // 2 vasilhames, abaixo do mínimo vigiado (4): cliente doméstico sem
        // risco patrimonial relevante.
        $this->comodato($c, 2);

        $this->assertSame([], app(VigilanciaComodatoService::class)->avaliarEmpresa($this->empresa->id));
    }

    public function test_alerta_e_criado_com_severidade_por_patrimonio(): void
    {
        $grande = $this->cliente('Cliente grande parado');
        $this->comodato($grande, 60);

        $pequeno = $this->cliente('Cliente pequeno parado');
        $this->comodato($pequeno, 5);

        app(GerarAlertasComodato::class)->executar($this->empresa->id);

        $alertas = Alerta::where('origem', GerarAlertasComodato::ORIGEM_GIRO)->get();
        $this->assertCount(2, $alertas);

        // Ambos são CRÍTICOS (zero compra), mas 60 vasilhames pesam mais que 5.
        $this->assertSame('ALTA', $alertas->firstWhere('cliente_id', $grande->id)->severidade);
        $this->assertSame('MEDIA', $alertas->firstWhere('cliente_id', $pequeno->id)->severidade);
    }

    /**
     * Um cron semanal sobre o mesmo cliente parado geraria 52 alertas por ano.
     * A equipe pararia de olhar a tela na terceira semana.
     */
    public function test_rodadas_repetidas_nao_duplicam_o_alerta(): void
    {
        $c = $this->cliente('Cliente parado');
        $this->comodato($c, 30);

        $gerador = app(GerarAlertasComodato::class);
        $gerador->executar($this->empresa->id, now());
        $gerador->executar($this->empresa->id, now()->addDay());
        $gerador->executar($this->empresa->id, now()->addDays(2));

        $alertas = Alerta::where('origem', GerarAlertasComodato::ORIGEM_GIRO)->get();

        $this->assertCount(1, $alertas);
        $this->assertSame(3, $alertas[0]->ocorrencias);
    }

    public function test_alerta_fecha_sozinho_quando_o_cliente_volta_a_comprar(): void
    {
        $c = $this->cliente('Cliente que voltou');
        $this->comodato($c, 10);

        $gerador = app(GerarAlertasComodato::class);
        $gerador->executar($this->empresa->id);

        $this->assertSame(Alerta::ABERTO, Alerta::where('cliente_id', $c->id)->sole()->situacao);

        // Voltou a comprar em volume saudável.
        $this->comprar($c, 40, 6);
        $gerador->executar($this->empresa->id, now()->addDay());

        $this->assertSame(Alerta::RESOLVIDO, Alerta::where('cliente_id', $c->id)->sole()->situacao);
    }

    public function test_comodato_vencendo_gera_alerta(): void
    {
        $c = $this->cliente('Cliente com contrato vencido');
        $comodato = $this->comodato($c, 15);
        $comodato->forceFill(['data_vencimento' => now()->subDays(5)->toDateString()])->save();

        app(GerarAlertasComodato::class)->executar($this->empresa->id);

        $alerta = Alerta::where('origem', GerarAlertasComodato::ORIGEM_VENCIMENTO)->sole();

        // Vencido com 15 vasilhames: sem contrato vigente não há instrumento
        // para reaver o patrimônio.
        $this->assertSame('ALTA', $alerta->severidade);
        $this->assertStringContainsString('venceu há', $alerta->titulo);
        $this->assertTrue($alerta->dados['vencido']);
    }

    public function test_comodato_a_vencer_e_apenas_aviso(): void
    {
        $c = $this->cliente('Cliente a renovar');
        $comodato = $this->comodato($c, 15);
        $comodato->forceFill(['data_vencimento' => now()->addDays(10)->toDateString()])->save();

        app(GerarAlertasComodato::class)->executar($this->empresa->id);

        $alerta = Alerta::where('origem', GerarAlertasComodato::ORIGEM_VENCIMENTO)->sole();

        $this->assertSame('BAIXA', $alerta->severidade);
        $this->assertStringContainsString('vence em', $alerta->titulo);
        $this->assertFalse($alerta->dados['vencido']);
    }

    public function test_renovar_encerra_o_alerta_de_vencimento(): void
    {
        $c = $this->cliente('Cliente renovado');
        $comodato = $this->comodato($c, 15);
        $comodato->forceFill(['data_vencimento' => now()->subDays(5)->toDateString()])->save();

        $gerador = app(GerarAlertasComodato::class);
        $gerador->executar($this->empresa->id);
        $this->assertSame(1, Alerta::where('origem', GerarAlertasComodato::ORIGEM_VENCIMENTO)->where('situacao', Alerta::ABERTO)->count());

        app(ComodatoService::class)->renovar($comodato->refresh(), now()->addYear()->toDateString(), $this->user->id);
        $gerador->executar($this->empresa->id, now()->addDay());

        $this->assertSame(0, Alerta::where('origem', GerarAlertasComodato::ORIGEM_VENCIMENTO)->where('situacao', Alerta::ABERTO)->count());
    }

    /**
     * GLP a granel vai para tanque estacionário, não enche botijão. Contá-lo
     * como reabastecimento inflaria o giro e esconderia o desvio procurado.
     */
    public function test_granel_nao_conta_como_recarga_de_vasilhame(): void
    {
        $granel = Produto::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'GLP GRANEL',
            'tipo_glp' => 5,
            'ativo' => true,
        ]);

        $vinculo = app(VinculoVasilhame::class);
        $p45 = Produto::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Vasilha P45 Kg',
            'ativo' => true,
        ]);

        $this->assertFalse($vinculo->ehConteudo($granel));
        $this->assertNotContains($granel->id, $vinculo->conteudosDe($p45));
    }

    /** "Botijão P13 - Recarga" é venda de conteúdo, não casco emprestado. */
    public function test_recarga_conta_como_compra_e_nao_como_vasilhame(): void
    {
        $recarga = Produto::create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->empresa->grupo_id,
            'descricao' => 'Botijão P13 - Recarga',
            'ativo' => true,
        ]);

        $vinculo = app(VinculoVasilhame::class);

        $this->assertFalse($vinculo->ehVasilhame($recarga));
        $this->assertTrue($vinculo->ehConteudo($recarga));
        $this->assertContains($recarga->id, $vinculo->conteudosDe($this->vasilhame));
    }

    public function test_vinculo_liga_vasilhame_ao_gas_de_mesma_capacidade(): void
    {
        $r = app(VinculoVasilhame::class)->aplicar();

        $this->assertSame(1, $r['vinculados']);
        $this->assertSame($this->gas->id, (int) $this->vasilhame->refresh()->produto_retornavel_id);
    }

    /**
     * O par não atravessa empresa.
     *
     * Medido em produção: o "Vasilha P13" das empresas 114-117 e 140 casava com
     * o "Glp P13" da empresa 2. O consumo seria medido contra um produto que
     * nunca aparece nos pedidos daquela empresa — giro zero para todos, e a base
     * inteira delas viraria alerta crítico falso.
     */
    public function test_vinculo_nao_cruza_empresa(): void
    {
        $outraEmpresa = Empresa::factory()->create(['grupo_id' => $this->empresa->grupo_id]);

        $vasilhameVizinho = Produto::create([
            'empresa_id' => $outraEmpresa->id,
            'grupo_id' => $outraEmpresa->grupo_id,
            'descricao' => 'Vasilha P13 Kg',
            'ativo' => true,
        ]);

        $vinculo = app(VinculoVasilhame::class);

        // O gás compatível existe, mas é da empresa do setUp — não serve.
        $this->assertNotContains($this->gas->id, $vinculo->conteudosDe($vasilhameVizinho));
        $this->assertSame([], $vinculo->conteudosDe($vasilhameVizinho));

        $vinculo->aplicar();
        $this->assertNull($vasilhameVizinho->refresh()->produto_retornavel_id);
    }
}
