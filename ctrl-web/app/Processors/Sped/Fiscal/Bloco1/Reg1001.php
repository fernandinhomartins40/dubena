<?php

namespace App\Processors\Sped\Fiscal\Bloco1;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class Reg1001 extends AbstractReg
{

    // Indicador de movimento:
    //     0 - Bloco com dados informados;
    //     1 - Bloco sem dados informados
    protected $IND_MOV;

    protected function setAttributes($data = [])
    {
        $this->IND_MOV = "1";

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'IND_MOV'   => static::getBaseVR("Indicador de movimento", 1, true, ['0', '1'])
        ];
    }

}
