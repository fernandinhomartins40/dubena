<?php

namespace App\Processors\Sped\Fiscal\BlocoE;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegE116 extends AbstractReg
{
    protected $COD_OR;
    protected $VL_OR;
    protected $DT_VCTO;
    protected $COD_REC;
    protected $NUM_PROC;
    protected $IND_PROC;
    protected $PROC;
    protected $TXT_COMPL;
    protected $MES_REF;

    protected function setAttributes($data = [])
    {
        $this->COD_OR = '000';
        $this->VL_OR = $data->VL_TOT_CREDITOS;
        $this->DT_VCTO = '';
        $this->COD_REC = '';
        $this->NUM_PROC = '';
        $this->IND_PROC = '';
        $this->PROC = '';
        $this->TXT_COMPL = '';
        $this->MES_REF = '';

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'COD_OR'    => static::getBaseVR("Código da obrigação a recolher", 3, true),
            'VL_OR'     => static::getBaseVR("Valor da obrigação a recolher"),
            'DT_VCTO'   => static::getBaseVR("Data de vencimento da obrigação", 8, true),
            'COD_REC'   => static::getBaseVR("Código de receita referente à obrigação"),
            'NUM_PROC'  => static::getBaseVR("Número do processo ou auto de infração ao qual a obrigação está vinculada, se houver", 15, false, "OC"),
            'IND_PROC'  => static::getBaseVR("Indicador da origem do processo", 1, true, "OC", [0, 1, 2, 9]),
            'PROC'      => static::getBaseVR("Descrição resumida do processo que embasou o lançamento", false, false, "OC"),
            'TXT_COMPL' => static::getBaseVR("Descrição complementar das obrigações a recolher", false, false, "OC"),
            'MES_REF'   => static::getBaseVR("Informe o mês de referência no formato \"mmaaaa\"", 6, true),
        ];
    }

}
