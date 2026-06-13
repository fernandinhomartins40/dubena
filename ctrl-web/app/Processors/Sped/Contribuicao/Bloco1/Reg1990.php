<?php
namespace App\Processors\Sped\Contribuicao\Bloco1;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;

// REGISTRO 1990: ENCERRAMENTO DO BLOCO 1
class Reg1990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco 1
    protected $QTD_LIN_1;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_1 = $data['blocos']->$bloco->count + 1;
        
        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_1' => static::getBaseVR("Encerramento Bloco 1", false)
        ];
    }
}
