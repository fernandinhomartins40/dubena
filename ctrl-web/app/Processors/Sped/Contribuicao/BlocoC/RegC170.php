<?php

namespace App\Processors\Sped\Contribuicao\BlocoC;

use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

/**
 * REGISTRO C170: COMPLEMENTO DO DOCUMENTO - ITENS DO DOCUMENTO (CÓDIGOS 01, 1B, 04 e 55)
 */
class RegC170 extends AbstractReg
{
    /**
     * Número seqüencial do item no documento fiscal
     */
    protected $NUM_ITEM;
    /**
     * Código do item (campo 02 do Registro 0200)
     */
    protected $COD_ITEM;
    /**
     * Descrição complementar do item como adotado no documento fiscal
     */
    protected $DESCR_COMPL;
    /**
     * Quantidade do item
     */
    protected $QTD;
    /**
     * Unidade do item (Campo 02 do registro 0190)
     */
    protected $UNID;
    /**
     * Valor total do item (mercadorias ou serviços)
     */
    protected $VL_ITEM;
    /**
     * Valor do desconto comercial
     */
    protected $VL_DESC;
    /**
     * Movimentação física do ITEM/PRODUTO:
     * 0. SIM
     * 1. NÃO
     */
    protected $IND_MOV;
    /**
     * Código da Situação Tributária referente ao ICMS, conforme a Tabela indicada no item 4.3.1
     */
    protected $CST_ICMS;
    /**
     * Código Fiscal de Operação e Prestação
     */
    protected $CFOP;
    /**
     * Código da natureza da operação (campo 02 do Registro 0400)
     */
    protected $COD_NAT;
    /**
     * Valor da base de cálculo do ICMS
     */
    protected $VL_BC_ICMS;
    /**
     * Alíquota do ICMS
     */
    protected $ALIQ_ICMS;
    /**
     * Valor do ICMS creditado/debitado
     */
    protected $VL_ICMS;
    /**
     * Valor da base de cálculo referente à substituição tributária
     */
    protected $VL_BC_ICMS_ST;
    /**
     * Alíquota do ICMS da substituição tributária na unidade da federação de destino
     */
    protected $ALIQ_ST;
    /**
     * Alíquota do ICMS da substituição tributária na unidade da federação de destino
     */
    protected $VL_ICMS_ST;
    /**
     * Valor do ICMS referente à substituição tributária
     */
    protected $IND_APUR;
    /**
     * Indicador de período de apuração do IPI:
     * 0 - Mensal;
     * 1 Decendial
     */
    protected $CST_IPI;
    /**
     * Código da Situação Tributária referente ao IPI, conforme a Tabela
     * indicada no item 4.3.2.
     */
    protected $COD_ENQ;
    /**
     * Valor da base de cálculo do IPI
     */
    protected $VL_BC_IPI;
    /**
     * Alíquota do IPI 
     */
    protected $ALIQ_IPI;
    /**
     * Valor do IPI creditado/debitado
     */
    protected $VL_IPI;
    /**
     * Código da Situação Tributária referente ao PIS
     */
    protected $CST_PIS;
    /**
     *  Alíquota do PIS (em percentual)
     */
    protected $ALIQ_PIS;
    /**
     * Valor da base de cálculo do PIS
     */
    protected $VL_BC_PIS;
    /**
     * Quantidade – Base de cálculo PIS/PASEP
     */
    protected $QUANT_BC_PIS;
    /**
     * Alíquota do PIS (em reais)
     */
    protected $ALIQ_PIS_QUANT;
    /**
     * Valor do PIS
     */
    protected $VL_PIS;
    /**
     * Código da Situação Tributária referente ao COFINS
     */
    protected $CST_COFINS;
    /**
     * alor da base de cálculo da COFINS
     */
    protected $VL_BC_COFINS;
    /**
     * Alíquota do COFINS (em percentual)
     */
    protected $ALIQ_COFINS;
    /**
     * Quantidade – Base de cálculo COFINS
     */
    protected $QUANT_BC_COFINS;
    /**
     * Alíquota da COFINS (em reais)
     */
    protected $ALIQ_COFINS_QUANT;
    /**
     * Valor da COFINS
     */
    protected $VL_COFINS;
    /**
     * Código da conta analítica contábil debitada/creditada
     */
    protected $COD_CTA;

    protected function setAttributes($data = [])
    {
        $this->ALIQ_IPI = Util::numberFormat($data->pipi);
        $this->ALIQ_ICMS = Util::numberFormat($data->picms);
        $this->ALIQ_ST = 0;
        $this->ALIQ_PIS = Util::numberFormat($data->ppis);
        $this->ALIQ_PIS_QUANT = null;
        $this->ALIQ_COFINS = Util::numberFormat($data->pcofins);
        $this->ALIQ_COFINS_QUANT = null;
        $this->COD_ITEM = $data->cprod;
        $this->CST_ICMS = Util::fillStrWith($data->cst, 3, "0");
        $this->COD_ENQ = "";
        $this->COD_CTA = $data->planoconta_id;
        $this->CFOP = Util::fillStrWith($data->cfop, 4, "0");
        $this->COD_NAT = "";
        $this->CST_IPI = is_null($data->cstipi) ? null : Util::fillStrWith($data->cstipi, 2, "0");
        $this->CST_PIS = is_null($data->cstpis) ? null : Util::fillStrWith($data->cstpis, 2, "0");
        $this->CST_COFINS = Util::fillStrWith($data->cstcofins, 2, "0");
        $this->DESCR_COMPL = "";
        $this->IND_MOV = "1";
        $this->IND_APUR = "0";
        $this->NUM_ITEM = $data->NUM_ITEM;
        $this->QTD = $data->qcom > 0 ? Util::numberFormat($data->qcom, 5) : null;
        $this->QUANT_BC_PIS = null;
        $this->QUANT_BC_COFINS = null;
        $this->UNID = $data->ucom;
        $this->VL_BC_ICMS = Util::numberFormat($data->vbc);
        $this->VL_BC_ICMS_ST = Util::numberFormat($data->vbcstret);
        $this->VL_BC_IPI = Util::numberFormat($data->vbcipi);
        $this->VL_BC_PIS = Util::numberFormat($data->vbcpis);
        $this->VL_BC_COFINS = Util::numberFormat($data->vbccofins);
        $this->VL_COFINS = Util::numberFormat($data->vcofins);
        $this->VL_DESC = Util::numberFormat($data->vdesc);
        $this->VL_ITEM = Util::numberFormat($data->vprod);
        $this->VL_ICMS = Util::numberFormat($data->vicms);
        $this->VL_ICMS_ST = Util::numberFormat($data->vicmsstret);
        $this->VL_IPI = Util::numberFormat($data->vipi);
        $this->VL_PIS = Util::numberFormat($data->vpis);
        $this->setGenericError("NF " . $data->nf_id . " Produto " . $data->cprod);

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'NUM_ITEM'          => static::getBaseVR("Número seqüencial do item no documento fiscal", 3),
            'COD_ITEM'          => static::getBaseVR("Código do item", 60),
            'DESCR_COMPL'       => static::getBaseVR("Descrição complementar do item como adotado no documento fiscal", false, false, "N"),
            'QTD'               => static::getBaseVR("Quantidade do item", false, false, "N"),
            'UNID'              => static::getBaseVR("Unidade do item", 6, false, "N"),
            'VL_ITEM'           => static::getBaseVR("Valor total do item", false),
            'VL_DESC'           => static::getBaseVR("Valor do desconto comercial / exclusão da base de cálculo do PIS/PASEP e da COFINS", false, false, "N"),
            'IND_MOV'           => static::getBaseVR("Movimentação física do Produto/Item", 1, false, "N", ['0', '1']),
            'CST_ICMS'          => static::getBaseVR("Código da Situação Tributária referente ao ICMS", 3, true, "N"),
            'CFOP'              => static::getBaseVR("Código Fiscal de Operação e Prestação", 4, true),
            'COD_NAT'           => static::getBaseVR("Código da natureza da operação", 10, false, "N"),
            'VL_BC_ICMS'        => static::getBaseVR("Valor da base de cálculo do ICMS ", false, false, "N"),
            'ALIQ_ICMS'         => static::getBaseVR("Alíquota do ICMS", 6, false, "N"),
            'VL_ICMS'           => static::getBaseVR("Valor do ICMS creditado/debitado", false, false, "N"),
            'VL_BC_ICMS_ST'     => static::getBaseVR("Valor da base de cálculo referente à substituição tributária", false, false, "N"),
            'ALIQ_ST'           => static::getBaseVR("Alíquota do ICMS da substituição tributária na unidade da federação de destino", 6, false, "N"),
            'VL_ICMS_ST'        => static::getBaseVR("Valor do ICMS referente à substituição tributária", false, false, "N"),
            'IND_APUR'          => static::getBaseVR("Indicador de período de apuração do IPI", 1, true, "N", ['0', '1']),
            'CST_IPI'           => static::getBaseVR("Código da Situação Tributária referente ao IPI", 2, true, "N"),
            'COD_ENQ'           => static::getBaseVR("Código de enquadramento legal do IPI", 3, true, "N"),
            'VL_BC_IPI'         => static::getBaseVR("Valor da base de cálculo do IPI", false, false, "N"),
            'ALIQ_IPI'          => static::getBaseVR("Alíquota do IPI", 6, false, "N"),
            'VL_IPI'            => static::getBaseVR("Valor do IPI creditado/debitado", false, false, "N"),
            'CST_PIS'           => static::getBaseVR("Código da Situação Tributária referente ao PIS". 2, true, $this->calls('CST_PIS')),
            'VL_BC_PIS'         => static::getBaseVR("Valor da base de cálculo do PIS/PASEP", false, false, "N"),
            'ALIQ_PIS'          => static::getBaseVR("Alíquota do PIS", 8, false, "N"),
            'QUANT_BC_PIS'      => static::getBaseVR("Quantidade – Base de cálculo PIS/PASEP", false, false, "N"),
            'ALIQ_PIS_QUANT'    => static::getBaseVR("Alíquota do PIS/PASEP", false, false, "N"),
            'VL_PIS'            => static::getBaseVR("Valor do PIS/PASEP", false, false, "N"),
            'CST_COFINS'        => static::getBaseVR("Código da Situação Tributária referente ao COFINS", 2, true),
            'VL_BC_COFINS'      => static::getBaseVR("Valor da base de cálculo da COFINS", false, false, "N"),
            'ALIQ_COFINS'       => static::getBaseVR("Alíquota do COFINS", 8, false, "N"),
            'QUANT_BC_COFINS'   => static::getBaseVR("Quantidade – Base de cálculo COFINS", false, false, "N"),
            'ALIQ_COFINS_QUANT' => static::getBaseVR("Alíquota da COFINS", false, false, "N"),
            'VL_COFINS'         => static::getBaseVR("Valor da COFINS", false, false, "N"),
            'COD_CTA'           => static::getBaseVR("Código da conta analítica contábil debitada/creditada ", 255, false, "N"),
        ];
    }

    private function calls($index)
    {
        $calls = collect([
            'CST_PIS'  => function($value) {
                if (in_array(substr($this->CFOP, 0, 1), [1, 2, 3]) && $value >= 50) {
                    $this->addError("Para as operações de entrada, CFOP iniciado "
                            . "por 1, 2 ou 3, o CST do PIS precisa menor ser que 50");
                } else if (in_array(substr($this->CFOP, 0, 1), [5, 6, 7]) && $value < 50) {
                    $this->addError("Para as operações de entrada, CFOP iniciado "
                            . "por 5, 6, 7, o CST do PIS precisa ser maior ou igual a 50");
                }
            }
        ]);

        // return $calls->get($index);
    }
}
