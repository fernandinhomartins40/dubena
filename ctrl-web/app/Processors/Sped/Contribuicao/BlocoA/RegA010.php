<?php
namespace App\Processors\Sped\Contribuicao\BlocoA;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
/**
* REGISTRO A010: IDENTIFICAÇÃO DO ESTABELECIMENTO
*
* Childrens: RegA100
*/
class RegA010 extends AbstractReg
{
    // Número de inscrição do estabelecimento no CNPJ
    protected $CNPJ;

    protected function setAttributes($data = [])
    {
        $this->CNPJ = "";

        return $this;
    }

    protected function getValidationArray()
    {
    }

    protected function validaRegistro()
    {
        if (Util::isNullOrEmpty($this->CNPJ))
            $this->addError("CNPJ não informado.");

        return $this;  
    }

    protected function layout()
    {
        $this->line = parent::setLine([
            $this->numReg,
            Util::replaceSpecialChars($this->CNPJ)
        ]);

        return $this;
    }
}
