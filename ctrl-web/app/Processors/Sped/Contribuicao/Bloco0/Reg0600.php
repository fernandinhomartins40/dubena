<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class Reg0600 extends AbstractReg
{
    // Data da inclusão/alteração
    protected $DT_ALT;
    // Código do centro de custos. 
    protected $COD_CCUS;
    // Nome do centro de custos
    protected $CCUS;

    protected function validaRegistro()
    {
        if (Util::isNullOrEmpty($this->COD_CCUS))
            $this->addError("Código do centro de custos não informado.");
        
        if (Util::isNullOrEmpty($this->CCUS))
            $this->addError("Nome do centro de custos não informado.");

        return $this;
    }

    protected function layout()
    {
        $this->line = parent::setLine([
            $this->numReg,
            Util::dateFormat($this->DT_ALT),
            Util::replaceSpecialChars($this->COD_CCUS),
            Util::replaceAccent($this->CCUS)
        ]);

        return $this;
    }

    protected function getValidationArray()
    {
    }

    protected function setAttributes($data = [])
    {
        $this->DT_ALT = "05/05/2017 00:00";
        $this->COD_CCUS = "";
        $this->CCUS = "";

        return $this;
    }
}
