<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;

class Reg0990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco 0
    protected $QTD_LIN_0;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_0 = $data['blocos']->$bloco->count + 1;
        $this->setGenericError("Encerramento Bloco 0");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_0' => static::getBaseVR("Quantidade total de linhas do Bloco 0", false),
        ];
    }
}