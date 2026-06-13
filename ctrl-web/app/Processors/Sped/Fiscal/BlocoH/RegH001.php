<?php

namespace App\Processors\Sped\Fiscal\BlocoH;

use App\Processors\Sped\AbstractReg;

class RegH001 extends AbstractReg
{

    /// Indicador de movimento:
    ///     0 - Bloco com dados informados;
    ///     1 - Bloco sem dados informados
    public $IND_MOV;

    protected function setAttributes($data = [])
    {
        $this->IND_MOV = "0";

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'IND_MOV' => static::getBaseVR("Indicador de movimento", 1, true, ['0', '1'])
        ];
    }

}
