<?php

namespace App\Processors\Sped\Fiscal\Bloco0;

use App\Processors\Sped\AbstractReg;

class Reg0001 extends AbstractReg
{

    protected $IND_MOV;

    protected function setAttributes($data = [])
    {
        $this->IND_MOV = "1";
        
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'IND_MOV' => static::getBaseVR("Indicador de movimento")
        ];
    }

}
