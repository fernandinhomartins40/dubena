<?php
namespace App\Processors\Sped\Contribuicao\BlocoD;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO D990: ENCERRAMENTO DO BLOCO D
class RegD990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco D
    protected $QTD_LIN_D;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_D = $data['blocos']->$bloco->count + 1;
        $this->setGenericError("Encerramento do Bloco D");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_D' => static::getBaseVR("Encerramento do Bloco D", false)
        ];
    }
}

