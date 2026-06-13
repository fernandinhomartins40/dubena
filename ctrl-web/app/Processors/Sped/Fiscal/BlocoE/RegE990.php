<?php
namespace App\Processors\Sped\Fiscal\BlocoE;

use App\Processors\Sped\AbstractReg;

// REGISTRO D990: ENCERRAMENTO DO BLOCO D
class RegE990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco D
    protected $QTD_LIN_E;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_E = $data['blocos']->$bloco->count + 1;
        $this->setGenericError("Encerramento do Bloco E");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_E' => static::getBaseVR("Encerramento do Bloco E", false)
        ];
    }
}

