<?php

namespace Tests\Domain;

use App\Domain\Fiscal\ResolucaoTributariaService;
use App\Models\Empresa;
use App\Models\Fiscal\NfImposto;
use App\Models\Fiscal\NfImpostoEstado;
use App\Models\Fiscal\OperacaoFiscal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F5-09 — a matriz de cenários da resolução tributária.
 *
 * > *"cenários por UF/regime/PF/PJ/entrada/saída/ST; validar com especialistas e
 * > ambientes oficiais atuais."*
 *
 * ## O que é código e o que não é
 *
 * A segunda metade da tarefa — validar com especialista contábil e transmitir
 * para o ambiente oficial de homologação de cada UF — **não é trabalho de
 * código**. Depende de certificado real, de credenciamento na SEFAZ e de alguém
 * que responda pelo enquadramento. Fica registrada como operação, do mesmo jeito
 * que o F4-07.
 *
 * A primeira metade é: as combinações que o serviço precisa distinguir estão
 * cobertas, e cada uma escolhe o conjunto certo de valores.
 *
 * ## O que a medição encontrou
 *
 * `CalculoImpostoService` tem dez testes bons — ele responde *quanto*.
 * `ResolucaoTributariaService`, que responde *quais* CST e alíquotas valem,
 * **não tinha teste nenhum**. É ele quem escolhe entre os dois conjuntos
 * completos de tributos (PJ e consumidor final) e entre regra base e linha
 * interestadual.
 *
 * Um erro ali troca o CST da nota inteira, e o cálculo — correto — obedece.
 *
 * ## Sobre o regime tributário
 *
 * Verifiquei: o regime não entra na resolução, e está certo. Ele vai como `CRT`
 * no XML, e o que muda de fato entre Simples e Normal são os CST/CSOSN — que
 * moram na matriz, por empresa. Fazer o serviço ramificar por regime duplicaria
 * a decisão em dois lugares, e o segundo sairia de sincronia.
 */
class CenariosFiscaisTest extends TestCase
{
    use RefreshDatabase;

    /** Regra com os dois conjuntos completos: PJ e consumidor final. */
    private function regra(Empresa $e): NfImposto
    {
        $operacaoId = OperacaoFiscal::withoutGrupo()->create([
            'grupo_id' => $e->grupo_id,
            'descricao' => 'Venda '.uniqid(),
            'cfop' => '5102', 'ativo' => true,
        ])->id;

        return NfImposto::withoutTenant()->create([
            'empresa_id' => $e->id, 'grupo_id' => $e->grupo_id,
            'operacao_fiscal_id' => $operacaoId, 'vigencia_inicio' => '2000-01-01',

            // PJ (contribuinte): tributado, com ST.
            'cst_icms' => '10', 'aliq_icms' => 18, 'perc_bc_icms' => 100,
            'origem_icms' => 0, 'modalidade_bc_icms' => 3,
            'mva' => 40, 'aliq_icms_st' => 18, 'perc_bc_icms_st' => 100,
            'aliq_diferimento' => 33.33, 'taxa_fecop' => 2,
            'cst_pis' => '01', 'aliq_pis' => 1.65,
            'cst_cofins' => '01', 'aliq_cofins' => 7.6,

            // Consumidor final: valores DIFERENTES de propósito — é assim que o
            // teste distingue "escolheu o conjunto certo" de "coincidiu".
            'pf_cst_icms' => '00', 'pf_aliq_icms' => 12, 'pf_perc_bc_icms' => 100,
            'pf_origem_icms' => 1, 'pf_modalidade_bc_icms' => 0,
            'pf_taxa_fecop' => 1,
            'pf_cst_pis' => '49', 'pf_aliq_pis' => 0,
            'pf_cst_cofins' => '49', 'pf_aliq_cofins' => 0,
        ]);
    }

    private function linhaInterestadual(NfImposto $regra, string $origem, string $destino): NfImpostoEstado
    {
        return NfImpostoEstado::withoutTenant()->create([
            'nf_imposto_id' => $regra->id,
            'empresa_id' => $regra->empresa_id, 'grupo_id' => $regra->grupo_id,
            'origem_uf' => $origem, 'destino_uf' => $destino,
            'cst_icms' => '10', 'aliq_icms' => 7, 'perc_bc_icms' => 100,
            'origem_icms' => 0, 'modalidade_bc_icms' => 3,
            'mva' => 50, 'aliq_icms_st' => 17,
            'taxa_fecop' => 2, 'aliq_icms_dest' => 18,
            'pf_cst_icms' => '00', 'pf_aliq_icms' => 7, 'pf_perc_bc_icms' => 100,
            'pf_origem_icms' => 0, 'pf_modalidade_bc_icms' => 0,
            'pf_taxa_fecop' => 2, 'pf_aliq_icms_dest' => 18,
        ]);
    }

    // ── Dentro do estado ──────────────────────────────────────────────────

    /** PJ dentro do estado: usa o conjunto base, com ST e diferimento. */
    public function test_pj_dentro_do_estado_usa_a_regra_base(): void
    {
        $empresa = Empresa::factory()->create();
        $regra = $this->regra($empresa);

        $r = app(ResolucaoTributariaService::class)->resolver($regra, 'PR', 'PR', consumidorFinal: false);

        $this->assertSame('10', $r['cst_icms']);
        $this->assertSame(18.0, $r['aliq_icms']);
        $this->assertSame(40.0, $r['mva_st'], 'PJ recolhe ST');
        $this->assertSame(33.33, $r['aliq_diferimento'], 'e tem diferimento dentro do estado');
        $this->assertFalse($r['difal'], 'DIFAL não existe dentro do estado');
    }

    /**
     * Consumidor final dentro do estado: usa o conjunto `pf_*`, e **não** recolhe
     * ST nem diferimento.
     *
     * A distinção é do legado (`ImpostoDB`) e não é detalhe: cobrar ST de venda a
     * consumidor final é imposto a mais na nota do cliente.
     */
    public function test_consumidor_final_dentro_do_estado_usa_o_conjunto_pf(): void
    {
        $empresa = Empresa::factory()->create();
        $regra = $this->regra($empresa);

        $r = app(ResolucaoTributariaService::class)->resolver($regra, 'PR', 'PR', consumidorFinal: true);

        $this->assertSame('00', $r['cst_icms'], 'o CST vem do conjunto PF');
        $this->assertSame(12.0, $r['aliq_icms']);
        $this->assertSame(1, $r['origem_icms'], 'inclusive a origem');
        $this->assertSame(0.0, $r['mva_st'], 'consumidor final não recolhe ST');
        $this->assertSame(0.0, $r['aliq_diferimento'], 'nem tem diferimento');
        $this->assertFalse($r['difal']);
    }

    /** PIS/COFINS também trocam de conjunto — e podem zerar. */
    public function test_pis_cofins_seguem_o_conjunto_do_destinatario(): void
    {
        $empresa = Empresa::factory()->create();
        $regra = $this->regra($empresa);
        $servico = app(ResolucaoTributariaService::class);

        $pj = $servico->resolver($regra, 'PR', 'PR', consumidorFinal: false);
        $pf = $servico->resolver($regra, 'PR', 'PR', consumidorFinal: true);

        $this->assertSame('01', $pj['cst_pis']);
        $this->assertSame(1.65, $pj['aliq_pis']);

        $this->assertSame('49', $pf['cst_pis'], 'outro CST para consumidor final');
        $this->assertSame(0.0, $pf['aliq_pis']);
    }

    // ── Entre estados ─────────────────────────────────────────────────────

    /** Interestadual PJ: usa a linha do par origem→destino, não a regra base. */
    public function test_pj_interestadual_usa_a_linha_do_par_de_ufs(): void
    {
        $empresa = Empresa::factory()->create();
        $regra = $this->regra($empresa);
        $this->linhaInterestadual($regra, 'PR', 'SC');

        $r = app(ResolucaoTributariaService::class)->resolver($regra, 'PR', 'SC', consumidorFinal: false);

        $this->assertSame(7.0, $r['aliq_icms'], 'a alíquota interestadual, não os 18 da regra base');
        $this->assertSame(50.0, $r['mva_st'], 'o MVA do par de UFs');
        $this->assertSame(0.0, $r['aliq_diferimento'], 'fora do estado não há diferimento');
        $this->assertFalse($r['difal'], 'PJ contribuinte não gera DIFAL');
    }

    /**
     * DIFAL: só existe em operação interestadual **e** para consumidor final.
     *
     * As duas condições juntas — é o que separa a venda para uma revenda de
     * outro estado (sem DIFAL) da venda para uma pessoa lá (com).
     */
    public function test_difal_exige_interestadual_e_consumidor_final(): void
    {
        $empresa = Empresa::factory()->create();
        $regra = $this->regra($empresa);
        $this->linhaInterestadual($regra, 'PR', 'SC');
        $servico = app(ResolucaoTributariaService::class);

        $this->assertTrue(
            $servico->resolver($regra, 'PR', 'SC', consumidorFinal: true)['difal'],
            'interestadual + consumidor final = DIFAL',
        );
        $this->assertFalse(
            $servico->resolver($regra, 'PR', 'SC', consumidorFinal: false)['difal'],
            'interestadual para contribuinte não gera DIFAL',
        );
        $this->assertFalse(
            $servico->resolver($regra, 'PR', 'PR', consumidorFinal: true)['difal'],
            'consumidor final dentro do estado também não',
        );
    }

    /**
     * Par de UFs sem linha cadastrada: **falha**, não improvisa.
     *
     * O legado faz o mesmo, e é o comportamento certo: emitir com a alíquota
     * interna numa operação interestadual é tributo errado na nota, e o erro só
     * aparece na apuração do estado de destino.
     */
    public function test_par_de_ufs_sem_regra_bloqueia_em_vez_de_improvisar(): void
    {
        $empresa = Empresa::factory()->create();
        $regra = $this->regra($empresa);
        $this->linhaInterestadual($regra, 'PR', 'SC');

        $this->expectException(ValidationException::class);
        app(ResolucaoTributariaService::class)->resolver($regra, 'PR', 'RS', consumidorFinal: false);
    }

    /**
     * A direção importa: PR→SC não serve para SC→PR.
     *
     * Alíquota interestadual depende de quem envia e de quem recebe; tratar o par
     * como simétrico produziria tributo errado na volta.
     */
    public function test_o_par_de_ufs_tem_direcao(): void
    {
        $empresa = Empresa::factory()->create();
        $regra = $this->regra($empresa);
        $this->linhaInterestadual($regra, 'PR', 'SC');

        $this->expectException(ValidationException::class);
        app(ResolucaoTributariaService::class)->resolver($regra, 'SC', 'PR', consumidorFinal: false);
    }

    /** A UF é comparada sem depender de caixa — o cadastro nem sempre normaliza. */
    public function test_uf_em_minusculas_resolve_igual(): void
    {
        $empresa = Empresa::factory()->create();
        $regra = $this->regra($empresa);
        $this->linhaInterestadual($regra, 'PR', 'SC');

        $r = app(ResolucaoTributariaService::class)->resolver($regra, 'pr', 'sc', consumidorFinal: false);

        $this->assertSame(7.0, $r['aliq_icms']);
    }

    /** FCP acompanha o conjunto escolhido, dentro e fora do estado. */
    public function test_fcp_segue_o_cenario(): void
    {
        $empresa = Empresa::factory()->create();
        $regra = $this->regra($empresa);
        $this->linhaInterestadual($regra, 'PR', 'SC');
        $servico = app(ResolucaoTributariaService::class);

        $this->assertSame(2.0, $servico->resolver($regra, 'PR', 'PR', false)['aliq_fcp']);
        $this->assertSame(1.0, $servico->resolver($regra, 'PR', 'PR', true)['aliq_fcp'], 'o FCP do conjunto PF');
        $this->assertSame(2.0, $servico->resolver($regra, 'PR', 'SC', false)['aliq_fcp']);
    }
}
