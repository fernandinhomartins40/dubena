<?php

namespace App\Processors\Sped\Fiscal\BlocoE;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegE110 extends AbstractReg
{

    /// Valor total dos débitos por "Saídas e prestações com débito do imposto"
    public $VL_TOT_DEBITOS;
    /// Valor total dos ajustes a débito decorrentes do documento fiscal.
    public $VL_AJ_DEBITOS;
    /// Valor total de "Ajustes a débito"
    public $VL_TOT_AJ_DEBITOS;
    /// Valor total de Ajustes “Estornos de créditos”
    public $VL_ESTORNOS_CRED;
    /// Valor total dos créditos por "Entradas e aquisições com crédito do imposto"
    public $VL_TOT_CREDITOS;
    /// Valor total dos ajustes a crédito decorrentes do documento fiscal.
    public $VL_AJ_CREDITOS;
    /// Valor total de "Ajustes a crédito"
    public $VL_TOT_AJ_CREDITOS;
    /// Valor total de Ajustes “Estornos de Débitos”
    public $VL_ESTORNOS_DEB;
    /// Valor total de "Saldo credor do período anterior"
    public $VL_SLD_CREDOR_ANT;
    /// Valor do saldo devedor apurado
    public $VL_SLD_APURADO;
    /// Valor total de "Deduções"
    public $VL_TOT_DED;
    /// Valor total de "ICMS a recolher" (11-12)
    public $VL_ICMS_RECOLHER;
    /// Valor total de "Saldo credor a transportar para o período seguinte”
    public $VL_SLD_CREDOR_TRANSPORTAR;
    /// Valores recolhidos ou a recolher, extraapuração.
    public $DEB_ESP;

    protected function setAttributes($data = [])
    {
        $this->DEB_ESP = 0;
        $this->VL_AJ_CREDITOS = 0;
        $this->VL_AJ_DEBITOS = 0;
        $this->VL_ESTORNOS_CRED = 0;
        $this->VL_ESTORNOS_DEB = 0;
        $this->VL_SLD_CREDOR_ANT = 0;

        $c100 = $data['allRegs']->get('RegC100');
        $this->VL_TOT_DEBITOS = $c100->sum("VL_TOT_DEBITOS");
        $this->VL_TOT_CREDITOS = $c100->sum("VL_TOT_CREDITOS");
        $saldo_icms = $this->VL_TOT_DEBITOS - $this->VL_TOT_CREDITOS;

        if ($saldo_icms >= 0.0) {
            $this->VL_SLD_APURADO = Util::numberFormat($saldo_icms);
            $this->VL_SLD_CREDOR_TRANSPORTAR = 0;
            $this->VL_ICMS_RECOLHER = $this->VL_SLD_APURADO;
        } else {
            $this->VL_SLD_APURADO = 0;
            $this->VL_SLD_CREDOR_TRANSPORTAR = Util::numberFormat($saldo_icms * -1);
            $this->VL_ICMS_RECOLHER = '0,00';
        }
        $this->VL_TOT_DEBITOS = Util::numberFormat($this->VL_TOT_DEBITOS);
        $this->VL_TOT_CREDITOS = Util::numberFormat($this->VL_TOT_CREDITOS);

        $this->VL_TOT_AJ_CREDITOS = 0;
        $this->VL_TOT_AJ_DEBITOS = 0;
        $this->VL_TOT_DED = 0;
        $this->addChildren('RegE116', $this);

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'VL_TOT_DEBITOS'            => static::getBaseVR("Valor total dos débitos por \"Saídas e prestações com débito do imposto\""),
            'VL_AJ_DEBITOS'             => static::getBaseVR("Valor total dos ajustes a débito decorrentes do documento fiscal."),
            'VL_TOT_AJ_DEBITOS'         => static::getBaseVR("Valor total de \"Ajustes a débito\""),
            'VL_ESTORNOS_CRED'          => static::getBaseVR("Valor total de Ajustes \"Estornos de créditos\""),
            'VL_TOT_CREDITOS'           => static::getBaseVR("Valor total dos créditos por \"Entradas e aquisições com crédito do imposto\""),
            'VL_AJ_CREDITOS'            => static::getBaseVR("Valor total dos ajustes a crédito decorrentes do documento fiscal."),
            'VL_TOT_AJ_CREDITOS'        => static::getBaseVR("Valor total de \"Ajustes a crédito\""),
            'VL_ESTORNOS_DEB'           => static::getBaseVR("Valor total de Ajustes “Estornos de Débitos\”"),
            'VL_SLD_CREDOR_ANT'         => static::getBaseVR("Valor total de \"Saldo credor do período anterior\""),
            'VL_SLD_APURADO'            => static::getBaseVR("Valor do saldo devedor apurado"),
            'VL_TOT_DED'                => static::getBaseVR("Valor total de \"Deduções\""),
            'VL_ICMS_RECOLHER'          => static::getBaseVR("Valor total de ICMS a recolher (11-12)"),
            'VL_SLD_CREDOR_TRANSPORTAR' => static::getBaseVR("Valor total de \"Saldo credor a transportar para o período seguinte\”"),
            'DEB_ESP'                   => static::getBaseVR("Valores recolhidos ou a recolher, extraapuração."),
        ];
    }

}
