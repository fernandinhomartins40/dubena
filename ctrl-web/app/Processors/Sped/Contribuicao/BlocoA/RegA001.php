<?php
namespace App\Processors\Sped\Contribuicao\BlocoA;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
/**
* REGISTRO A001: ABERTURA DO BLOCO A
*
* Childrens: RegA010
*/
class RegA001 extends AbstractReg
{
    // Indicador de movimento:
    //     0 - Bloco com dados informados;
    //     1 - Bloco sem dados informados
    protected $IND_MOV;

    protected function setAttributes($data = [])
    {
        $this->IND_MOV = "0";
        
        return $this;
    } 
    protected function validaRegistro()
    {
        if(!Util::hasIn($this->IND_MOV, ["0", "1"]))
            $this->addError("Código de Indicador de movmento inválido.");
        
        return $this;
    }

    protected function getValidationArray()
    {
    }

    protected function layout()
    {
        $this->line = parent::setLine([
            $this->numReg,
            $this->IND_MOV
        ]);

        return $this;
    }
}
