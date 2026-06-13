<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class Reg0450 extends AbstractReg
{
    // Código da informação complementar do documento fiscal
    protected $COD_INF;
    // Texto livre da informação complementar existente no documento
    // fiscal, inclusive espécie de normas legais, poder normativo, número,
    // capitulação, data e demais referências pertinentes com indicação
    // referentes ao tributo.
    protected $TXT;

    protected function getValidationArray()
    {
    }

    protected function validaRegistro()
    {

        if (Util::isNullOrEmpty($this->COD_INF))
            $this->addError("Código da informação complementar do documento fiscal não informada.");

        if (Util::isNullOrEmpty($this->TXT))
            $this->addError("Texto livre da informação complementar não informado.");

        return $this;
    }

    protected function layout()
    {
        $this->line = parent::setLine([
            $this->numReg,
            Util::replaceSpecialChars($this->COD_INF, true, true),
            Util::replaceAccent($this->TXT)
        ]);

        return $this;
    }

    protected function setAttributes($data = [])
    {
        $this->COD_INF = "";
        $this->TXT = "";

        return $this;
    }
}
