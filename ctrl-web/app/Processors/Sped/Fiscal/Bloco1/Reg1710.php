<?php

namespace App\Processors\Sped\Fiscal\Bloco1;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;

class Reg1710 extends AbstractReg
{

    /// Número do dispositivo autorizado (utilizado) inicial
    public $NUM_DOC_INI;
    /// Número do dispositivo autorizado (utilizado) final
    public $NUM_DOC_FIN;

    protected function setAttributes($nf = [])
    {
        $this->NUM_DOC_FIN = $nf->fin;
        $this->NUM_DOC_INI = $nf->ini;

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'NUM_DOC_INI' => static::getBaseVR("Número do documento fiscal inicial", 12),
            'NUM_DOC_FIN' => static::getBaseVR("Número do documento fiscal final inválido", 12)
        ];
    }

}
