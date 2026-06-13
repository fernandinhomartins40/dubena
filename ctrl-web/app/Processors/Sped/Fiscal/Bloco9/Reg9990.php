<?php
namespace App\Processors\Sped\Fiscal\Bloco9;

use App\Processors\Sped\AbstractReg;

// REGISTRO 9990: ENCERRAMENTO DO BLOCO 9
class Reg9990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco 9
    protected $QTD_LIN_9;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        
        $this->QTD_LIN_9 = $data['blocos']->$bloco->count + 2;
        
        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_9' => static::getBaseVR("Quantidade total de linhas do Bloco 9", false)
        ];
    }
}
