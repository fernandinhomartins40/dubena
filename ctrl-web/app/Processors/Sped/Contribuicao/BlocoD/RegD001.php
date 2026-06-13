<?php
namespace App\Processors\Sped\Contribuicao\BlocoD;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
class RegD001 extends AbstractReg
{
    // Indicador de movimento:
    //     0 - Bloco com dados informados;
    //     1 - Bloco sem dados informados
    protected $IND_MOV;

    protected function setAttributes($data = [])
    {
        $this->IND_MOV = "0";
        $this->setGenericError("Inicio do Bloco D");
        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'IND_MOV'   => static::getBaseVR("Indicador de movimento", 1, false, "O", ['0', '1']),
        ];
    }
}

