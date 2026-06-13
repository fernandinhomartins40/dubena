<?php
namespace App\Processors\Sped\Fiscal\BlocoG;

use App\Processors\Sped\AbstractReg;

// REGISTRO D990: ENCERRAMENTO DO BLOCO D
class RegG990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco D
    protected $QTD_LIN_G;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_G = $data['blocos']->$bloco->count + 1;
        $this->setGenericError("Encerramento do Bloco G");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_G' => static::getBaseVR("Encerramento do Bloco G", false)
        ];
    }
}

