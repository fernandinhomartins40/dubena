<?php
namespace App\Processors\Sped\Contribuicao\BlocoM;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO M990: ENCERRAMENTO DO BLOCO M
class RegM990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco M
    protected $QTD_LIN_M;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_M = $data['blocos']->$bloco->count + 1;
        
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'QTD_LIN_M' => static::getBaseVR("Encerramento Bloco M", false)
        ];
    }
}
