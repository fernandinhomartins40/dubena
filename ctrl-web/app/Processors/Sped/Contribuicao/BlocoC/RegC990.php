<?php
namespace App\Processors\Sped\Contribuicao\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO C990: ENCERRAMENTO DO BLOCO C
class RegC990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco C
    protected $QTD_LIN_C;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_C = $data['blocos']->$bloco->count + 1;
        $this->setGenericError("Encerramento Bloco C");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_C' => static::getBaseVR("Encerramento Bloco C", false),
        ];
    }
}

