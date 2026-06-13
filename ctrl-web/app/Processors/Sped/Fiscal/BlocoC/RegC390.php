<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegC390 extends AbstractReg
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
    /// Valor do ICMS referente à redução da BC ICMS
    public $VL_RED_BC;
    /// Código da Observação do Lançamento Fiscal
    public $COD_OBS;

    protected function validaRegistro()
    {
        if (Util::isNullOrEmpty($this->CST_ICMS))
            $this->addError("CST ICMS não informado ou inválido.");

        if (Util::isNullOrEmpty($this->CFOP))
            $this->addError("CFOP não informado ou inválido.");

        return $this;
    }

    public function layout()
    {
        $this->line = parent::setLine([
                    $this->numReg,
                    $this->CST_ICMS,
                    $this->CFOP,
                    Util::numberFormat($this->ALIQ_ICMS, 2),
                    Util::numberFormat($this->VL_OPR, 2),
                    Util::numberFormat($this->VL_BC_ICMS, 2),
                    Util::numberFormat($this->VL_ICMS, 2),
                    Util::numberFormat($this->VL_RED_BC, 2),
                    $this->COD_OBS
        ]);

        return $this;
    }

    protected function setAttributes($item = [])
    {
        $this->CST_ICMS = Util::fillStrWith($item->cst, 3, "0");
        $this->CFOP = $item->cfop;
        $this->ALIQ_ICMS = Util::numberFormat($item->picms, 2);
        $this->COD_OBS = "";
        $this->VL_BC_ICMS = Util::numberFormat($item->vbc, 2);
        $this->VL_ICMS = Util::numberFormat($item->vicms, 2);
        $this->VL_OPR = Util::numberFormat($item->vprod, 2);
        if (floatval($item->predbc) > 0.00) {
            $this->VL_RED_BC = Util::numberFormat($item->vicmsdeson, 2);
        }
        $this->VL_RED_BC = "0,00";

        return $this;
    }

    protected function getValidationArray()
    {
        return[
            
        ];
    }

}
