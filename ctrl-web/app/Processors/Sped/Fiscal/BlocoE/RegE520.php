<?php

namespace App\Processors\Sped\Fiscal\BlocoE;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegE520 extends AbstractReg
{

    /// Saldo credor do IPI transferido do período anterior
    public $VL_SD_ANT_IPI;
    /// Valor total dos débitos por "Saídas com débito do imposto"
    public $VL_DEB_IPI;
    /// Valor total dos créditos por "Entradas e aquisições com crédito do imposto"
    public $VL_CRED_IPI;
    /// Valor de "Outros débitos" do IPI (inclusive estornos de crédito)
    public $VL_OD_IPI;
    /// Valor de "Outros créditos" do IPI (inclusive estornos de débitos)
    public $VL_OC_IPI;
    /// Valor do saldo credor do IPI a transportar para o período seguinte
    public $VL_SC_IPI;
    /// Valor do saldo devedor do IPI a recolher
    public $VL_SD_IPI;

    protected function setAttributes($data = [])
    {
        $this->VL_SD_ANT_IPI = Util::numberFormat(0, 2);
        $this->VL_DEB_IPI = Util::numberFormat(0, 2);
        $this->VL_CRED_IPI = Util::numberFormat(0, 2);
        $this->VL_OD_IPI = Util::numberFormat(0, 2);
        $this->VL_OC_IPI = Util::numberFormat(0, 2);
        $this->VL_SC_IPI = Util::numberFormat(0, 2);
        $this->VL_SD_IPI = Util::numberFormat(0, 2);

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'VL_SD_ANT_IPI' => static::getBaseVR("Saldo credor do IPI transferido do período anterior"),
            'VL_DEB_IPI'    => static::getBaseVR("Valor total dos débitos por \"Saídas com débito do imposto\""),
            'VL_CRED_IPI'   => static::getBaseVR("Valor total dos créditos por \"Entradas e aquisições com crédito do imposto\""),
            'VL_OD_IPI'     => static::getBaseVR("Valor de \"Outros débitos\" do IPI (inclusive estornos de crédito)"),
            'VL_OC_IPI'     => static::getBaseVR("Valor de \"Outros créditos\" do IPI (inclusive estornos de débitos)"),
            'VL_SC_IPI'     => static::getBaseVR("Valor do saldo credor do IPI a transportar para o período seguinte"),
            'VL_SD_IPI'     => static::getBaseVR("Valor do saldo devedor do IPI a recolher"),
        ];
    }

}
