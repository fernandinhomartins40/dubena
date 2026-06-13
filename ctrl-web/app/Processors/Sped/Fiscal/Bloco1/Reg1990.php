<?php
namespace App\Processors\Sped\Fiscal\Bloco1;

use App\Processors\Sped\AbstractReg;

// REGISTRO 1990: ENCERRAMENTO DO BLOCO 1
class Reg1990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco 1
    protected $QTD_LIN_1;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_1 = $data['blocos']->$bloco->count + 1;
//        dd($this, $data['blocos']->$bloco);
        
        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_1' => static::getBaseVR("Encerramento Bloco 1", false)
        ];
    }
}
