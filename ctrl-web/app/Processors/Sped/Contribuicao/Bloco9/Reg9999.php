<?php
namespace App\Processors\Sped\Contribuicao\Bloco9;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO 9999: ENCERRAMENTO DO ARQUIVO
class Reg9999 extends AbstractReg
{
    // Quantidade total de linhas do Arquivo
    protected $QTD_LIN_TOTAL;

    protected function setAttributes($data = []){
        $total = 0;
        foreach ($data['blocos'] as $bloco) 
            $total += $bloco->count;
        $this->QTD_LIN_TOTAL = $total + 1;
        
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_TOTAL' => static::getBaseVR("Quantidade total de linhas do arquivo digital", false)
        ];
    }
}
