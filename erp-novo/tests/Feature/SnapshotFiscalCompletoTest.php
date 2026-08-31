<?php

namespace Tests\Feature;

use App\Domain\Fiscal\FiscalService;
use App\Domain\Fiscal\ModeloDocumento;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Fiscal\NfImposto;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Fiscal\OperacaoFiscal;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F5-08 — o item congela a resolução tributária INTEIRA.
 *
 * ## O defeito, em três camadas concordando
 *
 * `XmlNfeBuilder` já lia do item:
 *
 * ```php
 * $icms->orig = (int) $item->origem_icms;
 * $pis->CST   = $item->cst_pis;
 * $pis->vBC   = (float) $item->bc_pis;
 * ```
 *
 * E essas colunas **não existiam em `nota_itens`**. Existiam em `nf_impostos` —
 * a regra —, e nunca foram copiadas.
 *
 * `FiscalService` calculava todas elas na montagem e as descartava: não estavam
 * no `create()` do item nem no `$fillable`. Coluna, gravação e model, os três
 * jogando fora o mesmo dado.
 *
 * ## Por que ninguém viu
 *
 * O driver real da SEFAZ é gate. Em homologação quem responde é o
 * `FakeSefazDriver`, que autoriza tudo — inclusive um XML com `orig` = 0 para
 * produto importado e CST de PIS nulo.
 *
 * O efeito seria descoberto na primeira transmissão real, ou pior: aceito, e a
 * divergência apareceria na apuração.
 *
 * ## Por que congelar e não reler
 *
 * Depois de autorizada, a NF-e é imutável na SEFAZ. E a matriz agora tem
 * vigência **justamente porque muda** (F5-07): remontar o XML relendo a regra
 * produziria divergência com o autorizado. Mesma decisão já tomada para
 * descrição, NCM e unidade em F3-03 — este teste só cobre a metade que faltava.
 */
class SnapshotFiscalCompletoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Emite uma nota com regra tributária completa, incluindo os campos que
     * antes se perdiam.
     */
    private function notaEmitida(Empresa $empresa, int $origemIcms = 1, int $modalidadeBc = 3): NotaFiscal
    {
        $situacao = PedidoSituacao::factory()->create([
            'grupo_id' => $empresa->grupo_id, 'efeito' => EfeitoPedido::PENDENTE,
        ]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'preco_venda' => 100, 'ncm' => '27111910',
        ]);

        ConfigFiscal::withoutTenant()->firstOrCreate(
            ['empresa_id' => $empresa->id],
            ['ambiente' => 2, 'serie_nfe' => 1, 'serie_nfce' => 1, 'regime_tributario' => 1],
        );

        $operacao = OperacaoFiscal::withoutGrupo()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Venda tributada '.uniqid(),
            'cfop' => '5102', 'ativo' => true,
        ]);

        DB::table('produto_operacao_fiscal')->insert([
            'operacao_fiscal_id' => $operacao->id, 'produto_id' => $produto->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        NfImposto::withoutTenant()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'operacao_fiscal_id' => $operacao->id,
            'vigencia_inicio' => '2000-01-01',

            // `origem_icms = 1` é importação direta: o valor que, sem o
            // snapshot, virava 0 ("nacional") no XML.
            'cst_icms' => '00', 'aliq_icms' => 18, 'perc_bc_icms' => 100,
            'origem_icms' => $origemIcms, 'modalidade_bc_icms' => $modalidadeBc,
            'cst_pis' => '01', 'aliq_pis' => 1.65, 'perc_bc_pis' => 100,
            'cst_cofins' => '01', 'aliq_cofins' => 7.6, 'perc_bc_cofins' => 100,
            'pf_cst_icms' => '00', 'pf_aliq_icms' => 18, 'pf_perc_bc_icms' => 100,
            'pf_origem_icms' => $origemIcms, 'pf_modalidade_bc_icms' => $modalidadeBc,
            'pf_cst_pis' => '01', 'pf_aliq_pis' => 1.65, 'pf_perc_bc_pis' => 100,
            'pf_cst_cofins' => '01', 'pf_aliq_cofins' => 7.6, 'pf_perc_bc_cofins' => 100,
        ]);

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        $pedido = app(PedidoService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
        ], [['produto_id' => $produto->id, 'quantidade' => 3]]);

        return app(FiscalService::class)->emitirDoPedido($pedido, ModeloDocumento::NFE);
    }

    /**
     * O caso que motivou a tarefa: origem da mercadoria não pode virar 0.
     *
     * `orig` = 0 significa "nacional". Declarar nacional um produto importado é
     * informação falsa na nota — e sai sem nenhum aviso.
     */
    public function test_a_origem_da_mercadoria_e_congelada_no_item(): void
    {
        $empresa = Empresa::factory()->create();
        $nota = $this->notaEmitida($empresa, origemIcms: 1);

        $item = $nota->itens()->firstOrFail();

        $this->assertSame(1, (int) $item->origem_icms, 'importado não pode virar nacional no XML');
    }

    /** A modalidade de determinação da base também vai no XML (`modBC`). */
    public function test_a_modalidade_da_base_e_congelada(): void
    {
        $empresa = Empresa::factory()->create();
        $nota = $this->notaEmitida($empresa, modalidadeBc: 3);

        $this->assertSame(3, (int) $nota->itens()->value('modalidade_bc_icms'));
    }

    /**
     * CST de PIS e COFINS: o XML mandava o valor do tributo sem o código que o
     * justifica.
     */
    public function test_o_cst_de_pis_e_cofins_e_congelado(): void
    {
        $empresa = Empresa::factory()->create();
        $nota = $this->notaEmitida($empresa);

        $item = $nota->itens()->firstOrFail();

        $this->assertSame('01', $item->cst_pis);
        $this->assertSame('01', $item->cst_cofins);
    }

    /**
     * A base de cálculo de PIS/COFINS não pode ir zerada com o valor
     * preenchido — isso é um XML internamente inconsistente.
     */
    public function test_a_base_de_pis_e_cofins_acompanha_o_valor(): void
    {
        $empresa = Empresa::factory()->create();
        $nota = $this->notaEmitida($empresa);

        $item = $nota->itens()->firstOrFail();

        $this->assertGreaterThan(0, (float) $item->valor_pis, 'o cenário precisa gerar tributo');
        $this->assertGreaterThan(0, (float) $item->bc_pis, 'valor sem base é inconsistente');
        $this->assertGreaterThan(0, (float) $item->bc_cofins);
        $this->assertSame((float) $item->bc_icms, (float) $item->bc_pis, 'no caso geral a base é a mesma');
    }

    /**
     * O congelamento sobrevive à mudança da regra.
     *
     * É o teste que dá sentido a "congela, não relê": alterar a matriz depois da
     * emissão não pode mexer no que já foi autorizado.
     */
    public function test_alterar_a_regra_depois_nao_muda_a_nota_emitida(): void
    {
        $empresa = Empresa::factory()->create();
        $nota = $this->notaEmitida($empresa, origemIcms: 1);

        $antes = (int) $nota->itens()->value('origem_icms');

        // A revenda corrige o cadastro: o produto passou a ser nacional.
        NfImposto::withoutTenant()->where('empresa_id', $empresa->id)
            ->update(['origem_icms' => 0, 'aliq_icms' => 25]);

        $item = $nota->refresh()->itens()->firstOrFail();

        $this->assertSame($antes, (int) $item->origem_icms, 'o item guarda o que valia na emissão');
        $this->assertSame(18.0, round((float) $item->aliq_icms, 2), 'e a alíquota também');
    }

    /**
     * Guardião: nenhum campo que o XML lê do item pode faltar na tabela.
     *
     * Este é o teste que teria evitado o defeito. `XmlNfeBuilder` lia seis
     * campos inexistentes, e nada acusava — o PHP devolve `null` para
     * propriedade ausente de um model, sem erro.
     */
    public function test_todo_campo_que_o_xml_le_do_item_existe_na_tabela(): void
    {
        $fonte = (string) file_get_contents(app_path('Domain/Fiscal/XmlNfeBuilder.php'));

        preg_match_all('/\$item->([a-z_]+)/', $fonte, $m);
        $lidos = array_unique($m[1]);

        // Relações e acessores não são colunas.
        $naoSaoColunas = ['produto', 'get'];

        $colunas = Schema::getColumnListing('nota_itens');
        $faltando = [];

        foreach ($lidos as $campo) {
            if (in_array($campo, $naoSaoColunas, true)) {
                continue;
            }
            if (! in_array($campo, $colunas, true)) {
                $faltando[] = $campo;
            }
        }

        $this->assertGreaterThan(10, count($lidos), 'a varredura precisa ter encontrado campos');
        $this->assertSame([], $faltando, 'o XML lê do item campos que a tabela não tem — sairiam nulos');
    }
}
