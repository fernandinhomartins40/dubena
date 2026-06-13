<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

/**
 * Description of RegC170
 *
 * @author Jeferson
 */
class RegC190 extends AbstractReg
{

    /**
     * Código da Situação Tributária
     * @var mixed
     */
    protected $CST_ICMS;

    /**
     * scal de Operação e Prestação do agrupamento de itens
     * @var mixed
     */
    protected $CFOP;

    /**
     * Alíquota do ICMS
     * @var mixed
     */
    protected $ALIQ_ICMS;

    /**
     *  operação
     * @var mixed
     */
    protected $VL_OPR;

    /**
     * Valor da base de cálculo do ICMS
     * @var mixed
     */
    protected $VL_BC_ICMS;

    /**
     *  ICMS
     * @var mixed
     */
    protected $VL_ICMS;

    /**
     * Valor da base de cálculo do ICMS ST
     * @var mixed
     */
    protected $VL_BC_ICMS_ST;

    /**
     *  ICMS ST
     * @var mixed
     */
    protected $VL_ICMS_ST;

    /**
     * Valor não tributado em função da redução da base de cálculo do ICMS
     * @var mixed
     */
    protected $VL_RED_BC;

    /**
     *  IPI
     * @var mixed
     */
    protected $VL_IPI;

    /**
     * Código da observação do lançamento fiscal
     * @var mixed
     */
    protected $COD_OBS;

    protected function getValidationArray()
    {
        return [
            'CST_ICMS'      => static::getBaseVR("Código da Situação Tributária", 3, true),
            'CFOP'          => static::getBaseVR("Código Fiscal de Operação e Prestação do agrupamento de itens", 4, true),
            'ALIQ_ICMS'     => static::getBaseVR("Alíquota do ICMS", 6, false, "OC"),
            'VL_OPR'        => static::getBaseVR("Valor da operação"),
            'VL_BC_ICMS'    => static::getBaseVR("Valor da base de cálculo do ICMS"),
            'VL_ICMS'       => static::getBaseVR("Valor do ICMS"),
            'VL_BC_ICMS_ST' => static::getBaseVR("Valor da base de cálculo do ICMS ST"),
            'VL_ICMS_ST'    => static::getBaseVR("Valor do ICMS ST"),
            'VL_RED_BC'     => static::getBaseVR("Valor não tributado em função da redução da base de cálculo do ICMS"),
            'VL_IPI'        => static::getBaseVR("Valor do IPI"),
            'COD_OBS'       => static::getBaseVR("Código da observação do lançamento fiscal", 6, false, "OC")
        ];
    }

    protected function setAttributes($item = array())
    {
        $this->CST_ICMS = Util::fillStrWith($item->cst, 3, "0");
        $this->CFOP = $item->cfop;
        $this->ALIQ_ICMS = Util::numberFormat($item->picms, 2);
        $this->VL_OPR = Util::numberFormat($item->vprod, 2);
        $this->VL_BC_ICMS = Util::numberFormat($item->vbc, 2);
        $this->VL_ICMS = Util::numberFormat($item->vicms, 2);
        $this->VL_BC_ICMS_ST = Util::numberFormat($item->vbcst, 2);
        $this->VL_ICMS_ST = Util::numberFormat($item->vicmsst, 2);
        $this->VL_RED_BC = $item->vred;
        $this->VL_IPI = Util::numberFormat($item->vipi, 2);
        $this->COD_OBS = "";

        return $this;
    }

}
