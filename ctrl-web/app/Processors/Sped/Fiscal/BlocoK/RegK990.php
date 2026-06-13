<?php
namespace App\Processors\Sped\Fiscal\BlocoK;

use App\Processors\Sped\AbstractReg;

// REGISTRO D990: ENCERRAMENTO DO BLOCO D
class RegK990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco D
    protected $QTD_LIN_K;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_K = $data['blocos']->$bloco->count + 1;
        $this->setGenericError("Encerramento do Bloco K");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_K' => static::getBaseVR("Encerramento do Bloco K", false)
        ];
    }
}

