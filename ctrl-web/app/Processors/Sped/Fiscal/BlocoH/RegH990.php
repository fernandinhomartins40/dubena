<?php
namespace App\Processors\Sped\Fiscal\BlocoH;

use App\Processors\Sped\AbstractReg;

// REGISTRO D990: ENCERRAMENTO DO BLOCO D
class RegH990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco D
    protected $QTD_LIN_H;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_H = $data['blocos']->$bloco->count + 1;
        $this->setGenericError("Encerramento do Bloco H");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_H' => static::getBaseVR("Encerramento do Bloco H", false)
        ];
    }
}

