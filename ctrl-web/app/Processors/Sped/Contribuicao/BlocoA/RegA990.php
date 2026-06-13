<?php
namespace App\Processors\Sped\Contribuicao\BlocoA;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO A990: ENCERRAMENTO DO BLOCO A
class RegA990 extends AbstractReg
{
    // Quantidade total de linhas do Bloco A
    protected $QTD_LIN_A;

    protected function setAttributes($data = [])
    {
        $bloco = $this->bloco;
        $this->QTD_LIN_A = $data['blocos']->$bloco->count + 1;
        
        return $this;
    }


    protected function getValidationArray()
    {
    }

    protected function layout()
    {
        $this->line = parent::setLine([
            $this->numReg,
            $this->QTD_LIN_A
        ]);

        return $this;
    }
}
