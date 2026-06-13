<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;

class RegC001 extends AbstractReg
{

    /**
     * Indicador de movimento:
     * 0 - Bloco com dados informados;
     * 1 - Bloco sem dados informados
     * @var string
     */
    protected $IND_MOV;

    protected function getValidationArray()
    {
        return [
            'IND_MOV' => static::getBaseVR("Indicador de movimento", 1, true, ['0', '1'])
        ];
    }

    protected function setAttributes($data = [])
    {
        $this->IND_MOV = "0";

        return $this;
    }

}
