<?php

namespace App\Processors\Sped\Contribuicao\BlocoM;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\Util;

/**
 * REGISTRO M105: DETALHAMENTO DA BASE DE CALCULO DO CRÉDITO APURADO NO PERÍODO – PIS/PASEP
 */
class RegM105 extends AbstractReg
{
    /**
     * Código da Base de Cálculo do Crédito apurado no período, conforme a Tabela 4.3.7
     */
    protected $NAT_BC_CRED;
    /**
     * Código da Situação Tributária referente ao crédito de
     * PIS/Pasep (Tabela 4.3.3) vinculado ao tipo de crédito
     * escriturado em M100.
     */
    protected $CST_PIS;
    /**
     * Valor Total da Base de Cálculo escriturada nos documentos
     * e operações (Blocos “A”, “C”, “D” e “F”), referente ao CST_PIS informado no Campo 03.
     */
    protected $VL_BC_PIS_TOT;
    /**
     * Parcela do Valor Total da Base de Cálculo informada no
     * Campo 04, vinculada a receitas com incidência cumulativa.
     * Campo de preenchimento específico para a pessoa
     * jurídica sujeita ao regime cumulativo e não-cumulativo da
     * contribuição (COD_INC_TRIB = 3 do Registro 0110)
     */
    protected $VL_BC_PIS_CUM;
    /**
     * Valor Total da Base de Cálculo do Crédito, vinculada a
     * receitas com incidência não-cumulativa (Campo 04 – Campo 05).
     */
    protected $VL_BC_PIS_NC;
    /**
     * Valor da Base de Cálculo do Crédito, vinculada ao tipo de
     * Crédito escriturado em M100.
     * - Para os CST_PIS = “50”, “51”, “52”, “60”, “61” e “62”:
     * Informar o valor do Campo 06 (VL_BC_PIS_NC);
     * - Para os CST_PIS = “53”, “54”, “55”, “56”, “63”, “64”
     * “65” e “66” (Crédito sobre operações vinculadas a mais
     * de um tipo de receita): Informar a parcela do valor do
     * Campo 06 (VL_BC_PIS_NC) vinculada especificamente
     * ao tipo de crédito escriturado em M100.
     * O valor deste campo será transportado para o Campo 04
     * (VL_BC_PIS) do registro M100
     */
    protected $VL_BC_PIS;
    /**
     * Quantidade Total da Base de Cálculo do Crédito apurado
     * em Unidade de Medida de Produto, escriturada nos
     * documentos e operações (Blocos “A”, “C”, “D” e “F”),
     * referente ao CST_PIS informado no Campo 03
     */
    protected $QUANT_BC_PIS_TOT;
    /**
     * Parcela da base de cálculo do crédito em quantidade
     * (campo 08) vinculada ao tipo de crédito escriturado em M100.
     * - Para os CST_PIS = “50”, “51” e “52”: Informar o valor
     * do Campo 08 (QUANT_BC_PIS);
     * - Para os CST_PIS = “53”, “54”, “55” e “56” (crédito
     * vinculado a mais de um tipo de receita): Informar a
     * parcela do valor do Campo 08 (QUANT_BC_PIS)
     * vinculada ao tipo de crédito escriturado em M100.
     * O valor deste campo será transportado para o Campo 06
     * (QUANT_BC_PIS) do registro M100.
     */
    protected $QUANT_BC_PIS;
    /**
     * Descrição do crédito
     */
    protected $DESC_CRED;

    protected function setAttributes($data = [])
    {
        $this->NAT_BC_CRED = Util::fillStrWith($data->piscofinstipobccredito, 2, "0");
        $this->CST_PIS = Util::fillStrWith($data->cstpis, 2, "0");
        $this->VL_BC_PIS_TOT = Util::numberFormat($data->vbcpis);
        $this->VL_BC_PIS_CUM = 0;
        $this->VL_BC_PIS_NC = Util::numberFormat($data->vbcpis);
        $this->VL_BC_PIS = Util::numberFormat($data->vbcpis);
        $this->QUANT_BC_PIS_TOT = "";
        $this->QUANT_BC_PIS = "";
        $this->DESC_CRED = "";
        $this->setGenericError("Crédito Nota id: " . $data->nf_id);
        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'NAT_BC_CRED'       => static::getBaseVR("Código da Base de Cálculo do Crédito apurado no período", 2, true),
            'CST_PIS'           => static::getBaseVR("Código da Situação Tributária referente ao crédito de PIS/Pasep", 2, true),
            'VL_BC_PIS_TOT'     => static::getBaseVR("Valor Total da Base de Cálculo", false, false, "N"),
            'VL_BC_PIS_CUM'     => static::getBaseVR("Parcela do Valor Total da Base de Cálculo", false, false, "N"),
            'VL_BC_PIS_NC'      => static::getBaseVR("Valor Total da Base de Cálculo do Crédito", false, false, "N"),
            'VL_BC_PIS'         => static::getBaseVR("Valor da Base de Cálculo do Crédito", false, false, "N"),
            'QUANT_BC_PIS_TOT'  => static::getBaseVR("Quantidade Total da Base de Cálculo do Crédito", false, false, "N"),
            'QUANT_BC_PIS'      => static::getBaseVR("Parcela da base de cálculo do crédito", false, false, "N"),
            'DESC_CRED'         => static::getBaseVR("Descrição do crédito ", 60, false, "N"),
        ];
    }
}
