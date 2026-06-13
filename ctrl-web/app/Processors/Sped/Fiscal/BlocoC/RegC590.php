<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegC590 extends AbstractReg
{

    /// Código da Situação Tributária referente ao ICMS, conforme a Tabela indicada no item 4.3.1
    public $CST_ICMS;
    /// Código Fiscal de Operação e Prestação
    public $CFOP;
    /// Alíquota do ICMS
    public $ALIQ_ICMS;
    /// Valor da base de cálculo do ICMS
    public $VL_OPR;
    /// Valor da base de cálculo do ICMS
    public $VL_BC_ICMS;
    /// Valor do ICMS creditado/debitado
    public $VL_ICMS;
    /// Valor da base de cálculo referente à substituição tributária
    public $VL_BC_ICMS_ST;
    /// Valor do ICMS referente à substituição tributária
    public $VL_ICMS_ST;
    /// Valor do ICMS referente à redução da BC ICMS
    public $VL_RED_BC;
    /// Código da Observação do Lançamento Fiscal
    public $COD_OBS;

    protected function setAttributes($item = [])
    {
        $this->CST_ICMS = Util::fillStrWith($item->cst, 3, "0");
        $this->ALIQ_ICMS = Util::numberFormat($item->picms, 2);
        $this->CFOP = $item->cfop;
        $this->COD_OBS = "";
        $this->VL_BC_ICMS = Util::numberFormat($item->vbc, 2);
        $this->VL_BC_ICMS_ST = Util::numberFormat($item->vbcstret, 2);
        $this->VL_ICMS = Util::numberFormat($item->vicms, 2);
        $this->VL_ICMS_ST = Util::numberFormat($item->vicmsstret, 2);
        $this->VL_OPR = Util::numberFormat($item->vprod, 2);
        $this->VL_RED_BC = Util::numberFormat($item->vicmsstret, 2);

        return $this;
    }

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
            'COD_OBS'       => static::getBaseVR("Código da observação do lançamento fiscal", 6, false, "OC")
        ];
    }

}
