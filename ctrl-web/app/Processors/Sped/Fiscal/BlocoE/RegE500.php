<?php

namespace App\Processors\Sped\Fiscal\BlocoE;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegE500 extends AbstractReg
{

    /// Indicador de período de apuração do IPI:
    /// 0 - Mensal;
    /// 1 - Decendial
    public $IND_APUR;
    /// Data inicial das informações contidas no arquivo
    public $DT_INI;
    /// Data final das informações contidas no arquivo
    public $DT_FIN;

    protected function setAttributes($data = [])
    {
        $this->IND_APUR = 0;
        $this->DT_INI = Util::dateFormat($data['datainicio']);
        $this->DT_FIN = Util::dateFormat($data['datafim']);

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'IND_APUR' => static::getBaseVR("Indicador de período de apuração do IPI", 1, true, 'O', [0, 1]),
            'DT_INI'   => static::getBaseVR("Data inicial a que a apuração se refere", 8, true),
            'DT_FIN'   => static::getBaseVR("Data final a que a apuração se refere", 8, true),
        ];
    }

}
