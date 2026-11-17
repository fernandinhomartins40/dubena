<?php

namespace Tests\Feature;

use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Domain\Fiscal\Drivers\FakeSefazDriver;
use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Fiscal\CartaCorrecao;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Fiscal\NfImposto;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Fiscal\OperacaoFiscal;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CC-e: numeração (nSeqEvento) sem corrida e SEFAZ fora da transação
 * (pendência da frente B na Rodada 3 + PIE-23).
 *
 * A concorrência é simulada de forma determinística: o driver, DURANTE a
 * chamada à SEFAZ da primeira correção, dispara a segunda. É exatamente a
 * janela em que duas requisições simultâneas se cruzavam.
 */
class CartaCorrecaoSequenciaTest extends TestCase
{
    use RefreshDatabase;

    private function notaAutorizada(): NotaFiscal
    {
        $empresa = Empresa::factory()->create();
        $situacao = PedidoSituacao::factory()->create(['grupo_id' => $empresa->grupo_id, 'efeito' => EfeitoPedido::PENDENTE]);
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'preco_venda' => 100, 'ncm' => '27111910']);
        ConfigFiscal::withoutTenant()->firstOrCreate(
            ['empresa_id' => $empresa->id],
            ['ambiente' => 2, 'serie_nfe' => 1, 'serie_nfce' => 1, 'regime_tributario' => 1],
        );
        $operacao = OperacaoFiscal::withoutGrupo()->create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Venda '.uniqid(), 'cfop' => '5102', 'ativo' => true,
        ]);
        DB::table('produto_operacao_fiscal')->insert([
            'operacao_fiscal_id' => $operacao->id, 'produto_id' => $produto->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        NfImposto::withoutTenant()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'operacao_fiscal_id' => $operacao->id,
            'cst_icms' => '00', 'aliq_icms' => 18, 'perc_bc_icms' => 100,
            'cst_pis' => '01', 'aliq_pis' => 1.65, 'perc_bc_pis' => 100,
            'cst_cofins' => '01', 'aliq_cofins' => 7.6, 'perc_bc_cofins' => 100,
            'pf_cst_icms' => '00', 'pf_aliq_icms' => 18, 'pf_perc_bc_icms' => 100,
            'pf_cst_pis' => '01', 'pf_aliq_pis' => 1.65, 'pf_perc_bc_pis' => 100,
            'pf_cst_cofins' => '01', 'pf_aliq_cofins' => 7.6, 'pf_perc_bc_cofins' => 100,
        ]);
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 3]]);

        return app(FiscalService::class)->emitirDoPedido($pedido, ModeloDocumento::NFE);
    }

    public function test_correcao_concorrente_recebe_o_numero_seguinte(): void
    {
        $nota = $this->notaAutorizada();
        $enviados = [];
        $driver = new class($enviados) extends FakeSefazDriver
        {
            /** @var \Closure|null disparada uma vez, no meio da primeira chamada */
            public ?\Closure $durante = null;

            public function __construct(private array &$enviados) {}

            public function cartaCorrecao(NotaFiscal $nota, string $correcao, int $sequencia): array
            {
                $this->enviados[] = $sequencia;
                if ($this->durante !== null) {
                    $f = $this->durante;
                    $this->durante = null;
                    $f();
                }

                return parent::cartaCorrecao($nota, $correcao, $sequencia);
            }
        };
        $driver->durante = fn () => app(FiscalService::class)
            ->cartaCorrecao($nota->fresh(), 'Segunda correcao feita ao mesmo tempo');
        $this->app->instance(SefazDriver::class, $driver);

        $primeira = app(FiscalService::class)->cartaCorrecao($nota, 'Primeira correcao do endereco de entrega');

        // A SEFAZ nunca recebe o mesmo nSeqEvento duas vezes.
        $this->assertSame([1, 2], $enviados);
        $this->assertSame(1, $primeira->sequencia);
        $this->assertSame([1, 2], CartaCorrecao::query()->where('nota_fiscal_id', $nota->id)
            ->orderBy('sequencia')->pluck('sequencia')->all());
        $this->assertSame(2, CartaCorrecao::query()->where('registrada', true)->count());
    }

    public function test_sefaz_e_chamada_fora_da_transacao(): void
    {
        $nota = $this->notaAutorizada();
        // RefreshDatabase já abre uma transação: o nível de base é o de agora.
        $base = DB::transactionLevel();
        $driver = new class extends FakeSefazDriver
        {
            public ?int $nivel = null;

            public function cartaCorrecao(NotaFiscal $nota, string $correcao, int $sequencia): array
            {
                $this->nivel = DB::transactionLevel();

                return parent::cartaCorrecao($nota, $correcao, $sequencia);
            }
        };
        $this->app->instance(SefazDriver::class, $driver);

        app(FiscalService::class)->cartaCorrecao($nota, 'Correcao do endereco de entrega');

        $this->assertSame($base, $driver->nivel);
    }

    public function test_falha_de_comunicacao_libera_o_numero(): void
    {
        $nota = $this->notaAutorizada();
        $this->app->instance(SefazDriver::class, new class extends FakeSefazDriver
        {
            public function cartaCorrecao(NotaFiscal $nota, string $correcao, int $sequencia): array
            {
                throw new \RuntimeException('SEFAZ fora do ar');
            }
        });

        try {
            app(FiscalService::class)->cartaCorrecao($nota, 'Correcao do endereco de entrega');
            $this->fail('A falha da SEFAZ deveria propagar.');
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, CartaCorrecao::query()->where('nota_fiscal_id', $nota->id)->count());

        $this->app->instance(SefazDriver::class, new FakeSefazDriver);
        $this->assertSame(1, app(FiscalService::class)->cartaCorrecao($nota, 'Correcao do endereco de entrega')->sequencia);
    }
}
