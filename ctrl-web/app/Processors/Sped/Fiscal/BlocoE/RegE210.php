<?php

namespace App\Processors\Sped\Fiscal\BlocoE;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegE210 extends AbstractReg
{

    protected $IND_MOV_ST;
    protected $VL_SLD_CRED_ANT_ST;
    protected $VL_DEVOL_ST;
    protected $VL_RESSARC_ST;
    protected $VL_OUT_CRED_ST;
    protected $VL_AJ_CREDITOS_ST;
    protected $VL_RETENCAO_ST;
    protected $VL_OUT_DEB_ST;
    protected $VL_AJ_DEBITOS_ST;
    protected $VL_SLD_DEV_ANT_ST;
    protected $VL_DEDUCOES_ST;
    protected $VL_ICMS_RECOL_ST;
    protected $VL_SLD_CRED_ST_TRANSPORTAR;
    protected $DEB_ESP_ST;

    protected function setAttributes($data = [])
    {
        $c100 = $data['allRegs']->get('RegC100');
        $this->IND_MOV_ST = 1;
        $this->VL_SLD_CRED_ANT_ST = Util::numberFormat($c100->sum("VL_TOT_RETENCAO_ST"));
        $this->VL_DEVOL_ST = Util::numberFormat($c100->sum("VL_TOT_DEVOL_ST"));
        $this->VL_RESSARC_ST = Util::numberFormat($c100->sum("VL_TOT_RESSARC_ST"));
        $this->VL_OUT_CRED_ST = 0;
        $this->VL_AJ_CREDITOS_ST = 0;
        $this->VL_RETENCAO_ST = Util::numberFormat($c100->sum('VL_TOT_RETENCAO_ST'));
        $this->VL_OUT_DEB_ST = 0;
        $this->VL_AJ_DEBITOS_ST = 0;
        $this->VL_SLD_DEV_ANT_ST = 0;
        $this->VL_DEDUÇÕES_ST = 0;
        $this->VL_ICMS_RECOL_ST = 0;
        $this->VL_SLD_CRED_ST_TRANSPORTAR = 0;
        $this->DEB_ESP_ST = 0;

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'IND_MOV_ST'                 => static::getBaseVR("Indicador de movimento", 1, true, "O"),
            'VL_SLD_CRED_ANT_ST'         => static::getBaseVR("Valor do Saldo credor de período anterior - ST"),
            'VL_DEVOL_ST'                => static::getBaseVR("Valor total do ICMS ST de devolução de mercadorias"),
            'VL_RESSARC_ST'              => static::getBaseVR("Valor total do ICMS ST de ressarcimentos"),
            'VL_OUT_CRED_ST'             => static::getBaseVR("Valor total de Ajustes Outros créditos ST e Estorno de débitos ST"),
            'VL_AJ_CREDITOS_ST'          => static::getBaseVR("Valor total dos ajustes a crédito de ICMS ST, provenientes de ajustes do documento fiscal"),
            'VL_RETENCAO_ST'             => static::getBaseVR("Valor Total do ICMS retido por Substituição Tributária"),
            'VL_OUT_DEB_ST'              => static::getBaseVR("Valor Total dos ajustes Outros débitos ST e Estorno de créditos ST"),
            'VL_AJ_DEBITOS_ST'           => static::getBaseVR("Valor total dos ajustes a débito de ICMS ST, provenientes de ajustes do documento fisca"),
            'VL_SLD_DEV_ANT_ST'          => static::getBaseVR("Valor total de Saldo devedor antes das deduções"),
            'VL_DEDUCOES_ST'             => static::getBaseVR("Valor total dos ajustes Deduções ST"),
            'VL_ICMS_RECOL_ST'           => static::getBaseVR("Imposto a recolher ST"),
            'VL_SLD_CRED_ST_TRANSPORTAR' => static::getBaseVR("Saldo credor de ST a transportar para o período seguinte [(03+04+05+06+07)– (08+09+10)]"),
            'DEB_ESP_ST'                 => static::getBaseVR("Valores recolhidos ou a recolher, extraapuração")
        ];
    }

}
