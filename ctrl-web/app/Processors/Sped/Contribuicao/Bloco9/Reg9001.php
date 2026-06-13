<?php
namespace App\Processors\Sped\Contribuicao\Bloco9;

use App\Processors\Sped\AbstractReg;

use App\Processors\Sped\Util;
// REGISTRO 9001: ABERTURA DO BLOCO 9
class Reg9001 extends AbstractReg
{
    // Indicador de movimento:
    // 0 - Bloco com dados informados;
    // 1 - Bloco sem dados informados
   protected $IND_MOV;

    protected function setAttributes($data = [])
    {
        $this->IND_MOV = "0";

        return $this;
    }

    /**
     * 
     * @return $this
     */
    protected function getValidationArray()
    {
        return [
            'IND_MOV'   => static::getBaseVR("Indicador de movimento", 1, true, "O", ['0', '1'])
        ];
    }
}
