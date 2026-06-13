<?php

namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\Util;

class Reg0001 extends AbstractReg
{

    protected $IND_MOV;

    protected function setAttributes($data = [])
    {
        $this->line = parent::setLine(["0001", "0"]);
        $this->IND_MOV = "0";

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'IND_MOV'   => static::getBaseVR("Indicador de movimento", 1, false, "0", ['0', '1']),
        ];
    }
}
