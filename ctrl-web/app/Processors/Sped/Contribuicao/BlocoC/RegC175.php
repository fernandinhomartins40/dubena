<?php

namespace App\Processors\Sped\Contribuicao\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\Util;

/**
 * REGISTRO C175: REGISTRO ANALÍTICO DO DOCUMENTO (CÓDIGO 65)
 */
class RegC175 extends AbstractReg
{
    /**
     * Código Fiscal de Operação e Prestação
     */
    protected $CFOP;
    /**
     * Valor da Operação
     */
    protected $VL_OPR;
    /**
     * Valor do Desconto comercial/exclusão
     */
    protected $VL_DESC;
    /**
     * Código da Situação Tributária referente ao PIS
     */
    protected $CST_PIS;
    /**
     * Valor da base de cálculo do PIS
     */
    protected $VL_BC_PIS;
    /**
     * Alíquota do PIS
     */
    protected $ALIQ_PIS;
    /**
     * Base de cálculo PIS/PASEP (em quantidade) 
     */
    protected $QUANT_BC_PIS;
    /**
     * Alíquota do PIS (em reais) 
     */
    protected $ALIQ_PIS_QUANT;
    /**
     * Valor do PIS creditado/debitado
     */
    protected $VL_PIS;
    /**
     * Código da Situação Tributária referente ao COFINS
     */
    protected $CST_COFINS;
    /**
     * Valor da base de cálculo do COFINS
     */
    protected $VL_BC_COFINS;
    /**
     * Alíquota do COFINS
     */
    protected $ALIQ_COFINS;
    /**
     * Base de cálculo COFINS (em quantidade) 
     */
    protected $QUANT_BC_COFINS;
    /**
     * Alíquota do COFINS (em reais) 
     */
    protected $ALIQ_COFINS_QUANT;
    /**
     * Valor do COFINS creditado/debitado
     */
    protected $VL_COFINS;
    /**
     * Código da conta analítica contábil debitada/creditada 
     */
    protected $COD_CTA;
    /**
     * Informação complementar 
     */
    protected $INFO_COMPL;

    protected function setAttributes($data = [])
    {
        $this->ALIQ_COFINS = Util::numberFormat($data->pcofins);
        $this->ALIQ_COFINS_QUANT = "";
        $this->ALIQ_PIS = $data->ppis > 0 ? Util::numberFormat($data->ppis) : $data->ppis;
        $this->ALIQ_PIS_QUANT = "";
        $this->COD_CTA =  $data->planoconta_id;
        $this->CFOP = $data->cfop;
        $this->CST_PIS = Util::fillStrWith($data->cstpis, 2, "0");
        $this->CST_COFINS = Util::fillStrWith($data->cstcofins, 2, "0");
        $this->INFO_COMPL = "";
        $this->QUANT_BC_COFINS = "";
        $this->QUANT_BC_PIS = "";
        $this->VL_BC_PIS = Util::numberFormat($data->vbcpis);
        $this->VL_BC_COFINS = Util::numberFormat($data->vbccofins);
        $this->VL_COFINS = Util::numberFormat($data->vcofins);
        $this->VL_DESC = Util::numberFormat($data->vdesc);
        $this->VL_OPR = Util::numberFormat($data->vprod);
        $this->VL_PIS = Util::numberFormat($data->vpis);
        $this->setGenericError("NF Produto " . $data->cprod);

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'CFOP'              => static::getBaseVR("Código fiscal de operação e prestação", 4, true),
            'VL_OPR'            => static::getBaseVR("Valor da operação", false),
            'VL_DESC'           => static::getBaseVR("Valor do desconto comercial / exclusão da base de cálculo do PIS/PASEP e da COFINS", false, false, "N"),
            'CST_PIS'           => static::getBaseVR("Código da Situação Tributária referente ao PIS/PASEP", 2, true, "N"),
            'VL_BC_PIS'         => static::getBaseVR("Valor da base de cálculo do PIS/PASEP (em valor)", false, false, "N"),
            'ALIQ_PIS'          => static::getBaseVR("Alíquota do PIS/PASEP (em percentual)", 8, false, "N"),
            'QUANT_BC_PIS'      => static::getBaseVR("Base de cálculo PIS/PASEP (em quantidade)", false, false, "N"),
            'ALIQ_PIS_QUANT'    => static::getBaseVR("Alíquota do PIS (em reais)", false, false, "N"),
            'VL_PIS'            => static::getBaseVR("Valor do PIS/PASEP", false, false, "N"),
            'CST_COFINS'        => static::getBaseVR("Código da Situação Tributária referente a Cofins", 2, true),
            'VL_BC_COFINS'      => static::getBaseVR("Valor da base de cálculo da Cofins", false, false, "N"),
            'ALIQ_COFINS'       => static::getBaseVR("Alíquota da Cofins (em percentual)", 8, false, "N"),
            'QUANT_BC_COFINS'   => static::getBaseVR("Base de cálculo COFINS", false, false, "N"),
            'ALIQ_COFINS_QUANT' => static::getBaseVR("Alíquota da COFINS (em reais)", false, false, "N"),
            'VL_COFINS'         => static::getBaseVR("Valor da Cofins", false, false, "N"),
            'COD_CTA'           => static::getBaseVR("Código da conta analítica contábil debitada/creditada", 255, false, "N"),
            'INFO_COMPL'        => static::getBaseVR("Informação complementar", false, false, "N"),
        ];
    }
}