<?php
namespace App\Processors\Sped\Contribuicao\BlocoF;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO F990: ENCERRAMENTO DO BLOCO F
class RegF990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco F
    protected $QTD_LIN_F;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_F = $data['blocos']->$bloco->count + 1;
        $this->setGenericError("Encerramento do Bloco F");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_F' => static::getBaseVR("Encerramento do Bloco F", false)
        ];
    }
}
