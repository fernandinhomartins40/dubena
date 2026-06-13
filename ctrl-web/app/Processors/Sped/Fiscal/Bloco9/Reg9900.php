<?php

namespace App\Processors\Sped\Fiscal\Bloco9;

use App\Processors\Sped\AbstractReg;

// REGISTRO 9900: ENCERRAMENTO DO BLOCO 9
class Reg9900 extends AbstractReg
{

    // Registro que será totalizado
    protected $REG_BLC;
    // Total de registros do tipo informado
    protected $QTD_REG_BLC;

    protected function setAttributes($data = [])
    {
        $this->generateBloco9($data);
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'REG_BLC'     => static::getBaseVR("Registro que será totalizado no próximo campo", 4),
            'QTD_REG_BLC' => static::getBaseVR("Total de registros do tipo informado no campo anterior", false)
        ];
    }

}
