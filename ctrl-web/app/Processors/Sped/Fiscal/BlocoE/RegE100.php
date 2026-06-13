<?php
namespace App\Processors\Sped\Fiscal\BlocoE;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegE100 extends AbstractReg
{
    /// Data inicial das informações contidas no arquivo
    public $DT_INI;
    /// Data final das informações contidas no arquivo
    public $DT_FIN;

    protected function setAttributes($data = [])
    {
        $this->DT_INI = Util::dateFormat($data['datainicio']);
        $this->DT_FIN = Util::dateFormat($data['datafim']);

        return $this;
    } 
    
    protected function getValidationArray()
    {
        return [
            'DT_INI' => static::getBaseVR("Data inicial a que a apuração se refere", 8, true),
            'DT_FIN' => static::getBaseVR("Data final a que a apuração se refere", 8, true)
        ];
    }
}

