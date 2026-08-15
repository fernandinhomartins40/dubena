<?php

namespace Tests\Feature;

use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Fiscal\ResolucaoTributariaService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Fiscal\MalhaFiscal;
use App\Models\Fiscal\NfImposto;
use App\Models\Fiscal\NfImpostoEstado;
use App\Models\Fiscal\OperacaoFiscal;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * MATRIZ DE TRIBUTAÇÃO — a regra fiscal real (CST/alíquota/MVA por operação ×
 * grupo fiscal × UF × consumidor final), portada de NFIMPOSTOS/NFIMPOSTOESTADOS.
 *
 * Antes disso o FiscalService tributava TODO item com CST 00 / 18% / CFOP 5102
 * fixos. Os testes abaixo protegem as quatro decisões que o legado toma e que se
 * perderiam de novo numa refatoração distraída: PJ × consumidor final, interno ×
 * interestadual, a recusa a faturar sem regra de UF, e o CFOP 5xxx→6xxx.
 */
class MatrizTributariaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{empresa:Empresa,operacao:OperacaoFiscal,grupoFiscal:MalhaFiscal,produto:Produto} */
    private function cenario(string $ufEmpresa = 'PR'): array
    {
        $empresa = Empresa::factory()->create(['uf' => $ufEmpresa]);

        $operacao = OperacaoFiscal::withoutGrupo()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => '5405 - Venda de Mercadorias',
            'cfop' => '5405',
            'ativo' => true,
        ]);

        $grupoFiscal = MalhaFiscal::withoutGrupo()->create([
            'grupo_id' => $empresa->grupo_id,
            'tipo' => 'grupos-fiscais',
            'codigo' => 'GLP',
            'descricao' => 'GLP',
            'ativo' => true,
        ]);

        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'grupo_fiscal_id' => $grupoFiscal->id,
            'preco_venda' => 100,
            'ncm' => '27111910',
        ]);

        DB::table('produto_operacao_fiscal')->insert([
            'operacao_fiscal_id' => $operacao->id,
            'produto_id' => $produto->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('empresa', 'operacao', 'grupoFiscal', 'produto');
    }

    /** Regra com conjuntos PJ e PF deliberadamente diferentes. */
    private function regra(array $c, array $extra = []): NfImposto
    {
        return NfImposto::withoutTenant()->create(array_merge([
            'empresa_id' => $c['empresa']->id,
            'grupo_id' => $c['empresa']->grupo_id,
            'operacao_fiscal_id' => $c['operacao']->id,
            'grupo_fiscal_id' => $c['grupoFiscal']->id,
            // PJ: tributado a 12%
            'cst_icms' => '00', 'aliq_icms' => 12.0, 'perc_bc_icms' => 100,
            'cst_pis' => '01', 'aliq_pis' => 1.65, 'perc_bc_pis' => 100,
            'cst_cofins' => '01', 'aliq_cofins' => 7.6, 'perc_bc_cofins' => 100,
            // PF/consumidor final: ST (CST 60), sem ICMS próprio
            'pf_cst_icms' => '60', 'pf_aliq_icms' => 0.0, 'pf_perc_bc_icms' => 100,
            'pf_cst_pis' => '49', 'pf_aliq_pis' => 0.0, 'pf_perc_bc_pis' => 100,
            'pf_cst_cofins' => '49', 'pf_aliq_cofins' => 0.0, 'pf_perc_bc_cofins' => 100,
        ], $extra));
    }

    // ── 1) PJ × consumidor final saem da MESMA regra por caminhos diferentes ──
    public function test_consumidor_final_usa_o_conjunto_pf_da_regra(): void
    {
        $c = $this->cenario();
        $regra = $this->regra($c);
        $servico = app(ResolucaoTributariaService::class);

        $pj = $servico->resolver($regra, 'PR', 'PR', consumidorFinal: false);
        $pf = $servico->resolver($regra, 'PR', 'PR', consumidorFinal: true);

        $this->assertSame('00', $pj['cst_icms']);
        $this->assertSame(12.0, $pj['aliq_icms']);
        $this->assertSame(1.65, $pj['aliq_pis']);

        $this->assertSame('60', $pf['cst_icms']);
        $this->assertSame(0.0, $pf['aliq_icms']);
        $this->assertSame(0.0, $pf['aliq_pis']);
    }

    // ── 2) Consumidor final não recolhe ST nem diferimento (regra do ImpostoDB) ──
    public function test_consumidor_final_zera_st_e_diferimento(): void
    {
        $c = $this->cenario();
        $regra = $this->regra($c, [
            'mva' => 45.0, 'aliq_icms_st' => 18.0, 'aliq_diferimento' => 33.0,
        ]);
        $servico = app(ResolucaoTributariaService::class);

        $pj = $servico->resolver($regra, 'PR', 'PR', false);
        $this->assertSame(45.0, $pj['mva_st']);
        $this->assertSame(33.0, $pj['aliq_diferimento']);

        $pf = $servico->resolver($regra, 'PR', 'PR', true);
        $this->assertSame(0.0, $pf['mva_st']);
        $this->assertSame(0.0, $pf['aliq_icms_st']);
        $this->assertSame(0.0, $pf['aliq_diferimento']);
    }

    // ── 3) Interestadual usa a linha da UF, não a regra base ──
    public function test_interestadual_usa_a_regra_do_par_de_ufs(): void
    {
        $c = $this->cenario('PR');
        $regra = $this->regra($c);

        NfImpostoEstado::withoutTenant()->create([
            'empresa_id' => $c['empresa']->id,
            'grupo_id' => $c['empresa']->grupo_id,
            'nf_imposto_id' => $regra->id,
            'origem_uf' => 'PR', 'destino_uf' => 'SC',
            'cst_icms' => '00', 'aliq_icms' => 7.0, 'perc_bc_icms' => 100,
            'pf_cst_icms' => '00', 'pf_aliq_icms' => 7.0, 'pf_perc_bc_icms' => 100,
            'pf_aliq_icms_dest' => 17.0,
        ]);

        $servico = app(ResolucaoTributariaService::class);
        $regra->load('estados');

        // Dentro do PR: 12% (regra base). Para SC: 7% (regra da UF).
        $this->assertSame(12.0, $servico->resolver($regra, 'PR', 'PR', false)['aliq_icms']);

        $inter = $servico->resolver($regra, 'PR', 'SC', false);
        $this->assertSame(7.0, $inter['aliq_icms']);
        $this->assertSame(17.0, $inter['aliq_icms_dest']);
    }

    // ── 4) DIFAL só para consumidor final interestadual ──
    public function test_difal_apenas_para_consumidor_final_interestadual(): void
    {
        $c = $this->cenario('PR');
        $regra = $this->regra($c);
        NfImpostoEstado::withoutTenant()->create([
            'empresa_id' => $c['empresa']->id,
            'grupo_id' => $c['empresa']->grupo_id,
            'nf_imposto_id' => $regra->id,
            'origem_uf' => 'PR', 'destino_uf' => 'SC',
            'cst_icms' => '00', 'aliq_icms' => 7.0,
            'pf_cst_icms' => '00', 'pf_aliq_icms' => 7.0, 'pf_aliq_icms_dest' => 17.0,
        ]);
        $regra->load('estados');
        $servico = app(ResolucaoTributariaService::class);

        $this->assertFalse($servico->resolver($regra, 'PR', 'PR', true)['difal']);
        $this->assertFalse($servico->resolver($regra, 'PR', 'SC', false)['difal']);
        $this->assertTrue($servico->resolver($regra, 'PR', 'SC', true)['difal']);
    }

    // ── 5) Sem regra de UF, ERRA — não fatura com alíquota errada ──
    public function test_interestadual_sem_regra_de_uf_falha(): void
    {
        $c = $this->cenario('PR');
        $regra = $this->regra($c);

        $this->expectException(ValidationException::class);
        app(ResolucaoTributariaService::class)->resolver($regra, 'PR', 'SP', false);
    }

    // ── 6) A regra específica do grupo fiscal ganha da coringa ──
    public function test_regra_do_grupo_fiscal_prevalece_sobre_a_coringa(): void
    {
        $c = $this->cenario();
        $this->regra($c, ['grupo_fiscal_id' => null, 'aliq_icms' => 25.0]);
        $especifica = $this->regra($c);

        $achada = app(ResolucaoTributariaService::class)->regraPara(
            $c['empresa']->id, $c['operacao']->id, $c['grupoFiscal']->id
        );

        $this->assertSame($especifica->id, $achada?->id);
        $this->assertSame(12.0, $achada->aliq_icms);
    }

    // ── 7) Ponta a ponta: a nota sai com a tributação da matriz ──
    public function test_nota_do_pedido_usa_a_matriz_em_vez_do_padrao(): void
    {
        $c = $this->cenario('PR');
        $this->regra($c);

        $situacao = PedidoSituacao::factory()->create([
            'grupo_id' => $c['empresa']->grupo_id, 'efeito' => EfeitoPedido::PENDENTE,
        ]);
        // Cliente PJ do mesmo estado → conjunto PJ, operação interna.
        $cliente = Cliente::factory()->create([
            'empresa_id' => $c['empresa']->id, 'grupo_id' => $c['empresa']->grupo_id,
            'uf' => 'PR', 'cnpj' => '11222333000181',
        ]);

        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $c['empresa']->id, 'grupo_id' => $c['empresa']->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
        ], [['produto_id' => $c['produto']->id, 'quantidade' => 2]]);

        $nota = app(\App\Domain\Fiscal\FiscalService::class)
            ->montarDoPedido($pedido, ModeloDocumento::NFE);

        $item = $nota->itens->first();
        // Da matriz (12%), não do padrão histórico (18%).
        $this->assertSame('00', $item->cst_icms);
        $this->assertSame(12.0, (float) $item->aliq_icms);
        $this->assertSame('5405', $item->cfop);
        $this->assertSame(24.0, (float) $item->valor_icms); // 200 × 12%
    }

    // ── 8) Venda interestadual converte o CFOP 5xxx → 6xxx ──
    public function test_cfop_vira_6xxx_em_venda_interestadual(): void
    {
        $c = $this->cenario('PR');
        $regra = $this->regra($c);
        NfImpostoEstado::withoutTenant()->create([
            'empresa_id' => $c['empresa']->id,
            'grupo_id' => $c['empresa']->grupo_id,
            'nf_imposto_id' => $regra->id,
            'origem_uf' => 'PR', 'destino_uf' => 'SC',
            'cst_icms' => '00', 'aliq_icms' => 7.0,
            'pf_cst_icms' => '00', 'pf_aliq_icms' => 7.0, 'pf_aliq_icms_dest' => 17.0,
        ]);

        $situacao = PedidoSituacao::factory()->create([
            'grupo_id' => $c['empresa']->grupo_id, 'efeito' => EfeitoPedido::PENDENTE,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $c['empresa']->id, 'grupo_id' => $c['empresa']->grupo_id,
            'uf' => 'SC', 'cnpj' => '11222333000181',
        ]);

        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $c['empresa']->id, 'grupo_id' => $c['empresa']->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
        ], [['produto_id' => $c['produto']->id, 'quantidade' => 1]]);

        $nota = app(\App\Domain\Fiscal\FiscalService::class)
            ->montarDoPedido($pedido, ModeloDocumento::NFE);

        $item = $nota->itens->first();
        $this->assertSame('6405', $item->cfop);
        $this->assertSame(7.0, (float) $item->aliq_icms);
    }

    // ── 9) Sem regra cadastrada, mantém o padrão histórico (não quebra) ──
    public function test_sem_regra_cai_no_padrao_anterior(): void
    {
        $c = $this->cenario('PR');
        // nenhuma regra criada de propósito

        $situacao = PedidoSituacao::factory()->create([
            'grupo_id' => $c['empresa']->grupo_id, 'efeito' => EfeitoPedido::PENDENTE,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $c['empresa']->id, 'grupo_id' => $c['empresa']->grupo_id, 'uf' => 'PR',
        ]);
        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $c['empresa']->id, 'grupo_id' => $c['empresa']->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
        ], [['produto_id' => $c['produto']->id, 'quantidade' => 1]]);

        $nota = app(\App\Domain\Fiscal\FiscalService::class)
            ->montarDoPedido($pedido, ModeloDocumento::NFE);

        $this->assertSame('00', $nota->itens->first()->cst_icms);
        $this->assertSame(18.0, (float) $nota->itens->first()->aliq_icms);
    }
}
