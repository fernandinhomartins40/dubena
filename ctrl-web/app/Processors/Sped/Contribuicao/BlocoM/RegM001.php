<?php
namespace App\Processors\Sped\Contribuicao\BlocoM;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;

class RegM001 extends AbstractReg
{
    // Indicador de movimento:
    //     0 - Bloco com dados informados;
    //     1 - Bloco sem dados informados
    protected $IND_MOV;

    protected function setAttributes($data = [])
    {
        $this->IND_MOV = "0";

        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'IND_MOV'   => static::getBaseVR("Indicado de movimento", 1, true, "O", ['0', '1'])
        ];
    }
}

