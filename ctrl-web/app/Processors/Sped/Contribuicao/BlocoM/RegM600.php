<?php

namespace App\Processors\Sped\Contribuicao\BlocoM;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\Util;

/**
 * REGISTRO M600: CONSOLIDAÇÃO DA CONTRIBUIÇÃO PARA O PIS/PASEP DO PERÍODO.
 */
class RegM600 extends AbstractReg
{
    /**
     * Valor Total da Contribuição Não Cumulativa do Período (recuperado do campo 13 do Registro M210, quando o
     * campo “COD_CONT” = 01, 02, 03, 04, 32 e 71)
     */
    protected $VL_TOT_CONT_NC_PER;
    /**
     * Valor do Crédito Descontado, Apurado no Próprio
     * Período da Escrituração (recuperado do campo 14 do Registro M100)
     */
    protected $VL_TOT_CRED_DESC;
    /**
     * Valor do Crédito Descontado, Apurado em Período de
     * Apuração Anterior (recuperado do campo 13 do Registro 1100)
     */
    protected $VL_TOT_CRED_DESC_ANT;
    /**
     * Valor Total da Contribuição Não Cumulativa Devida (02 – 03 - 04)
     */
    protected $VL_TOT_CONT_NC_DEV;
    /**
     * Valor Retido na Fonte Deduzido no Período
     */
    protected $VL_RET_NC;
    /**
     * Outras Deduções no Período
     */
    protected $VL_OUT_DED_NC;
    /**
     * Valor da Contribuição Não Cumulativa a Recolher/Pagar (05 – 06 - 07)
     */
    protected $VL_CONT_NC_REC;
    /**
     * Valor Total da Contribuição Cumulativa do Período
     * (recuperado do campo 13 do Registro M210, quando o
     * campo “COD_CONT” = 31, 32, 51, 52, 53, 54 e 72)
     */
    protected $VL_TOT_CONT_CUM_PER;
    /**
     * Valor Retido na Fonte Deduzido no Período
     */
    protected $VL_RET_CUM;
    /**
     * Outras Deduções no Período
     */
    protected $VL_OUT_DED_CUM;
    /**
     * Valor da Contribuição Cumulativa a Recolher/Pagar (09 - 10 – 11)
     */
    protected $VL_CONT_CUM_REC;
    /**
     * Valor Total da Contribuição a Recolher/Pagar no Período (08 + 12)
     */
    protected $VL_TOT_CONT_REC;

    protected function setAttributes($data = [])
    {
        $this->VL_TOT_CONT_NC_PER = Util::numberFormat(0);
        $this->VL_TOT_CRED_DESC = Util::numberFormat(0);
        $this->VL_TOT_CRED_DESC_ANT = Util::numberFormat(0);
        $this->VL_TOT_CONT_NC_DEV = Util::numberFormat(0);
        $this->VL_RET_NC = Util::numberFormat(0);
        $this->VL_OUT_DED_NC = Util::numberFormat(0);
        $this->VL_CONT_NC_REC = Util::numberFormat(0);
        $this->VL_TOT_CONT_CUM_PER = Util::numberFormat(0);
        $this->VL_RET_CUM = Util::numberFormat(0);
        $this->VL_OUT_DED_CUM = Util::numberFormat(0);
        $this->VL_CONT_CUM_REC = Util::numberFormat(0);
        $this->VL_TOT_CONT_REC = Util::numberFormat(0);

        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'VL_TOT_CONT_NC_PER'    => static::getBaseVR("Valor Total da Contribuição Não Cumulativa do Período", false),
            'VL_TOT_CRED_DESC'      => static::getBaseVR("Valor do Crédito Descontado, Apurado no Próprio Período da Escrituração", false),
            'VL_TOT_CRED_DESC_ANT'  => static::getBaseVR("Valor do Crédito Descontado, Apurado em Período de Apuração Anterior", false),
            'VL_TOT_CONT_NC_DEV'    => static::getBaseVR("Valor Total da Contribuição Não Cumulativa", false),
            'VL_RET_NC'             => static::getBaseVR("Valor Retido na Fonte Deduzido no Período", false),
            'VL_OUT_DED_NC'         => static::getBaseVR("Outras Deduções no Período", false),
            'VL_CONT_NC_REC'        => static::getBaseVR("Valor da Contribuição Não Cumulativa a Recolher/Pagar", false),
            'VL_TOT_CONT_CUM_PER'   => static::getBaseVR("Valor Total da Contribuição Cumulativa do Período", false),
            'VL_RET_CUM'            => static::getBaseVR("Valor Retido na Fonte Deduzido no Período", false),
            'VL_OUT_DED_CUM'        => static::getBaseVR("Outras Deduções no Período", false),
            'VL_CONT_CUM_REC'       => static::getBaseVR("Valor da Contribuição Cumulativa a Recolher/Pagar", false),
            'VL_TOT_CONT_REC'       => static::getBaseVR("Valor Total da Contribuição a Recolher/Pagar no Período", false)
        ];
    }
}