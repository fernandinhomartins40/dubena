<?php

namespace App\Processors\Sped\Fiscal\BlocoE;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegE200 extends AbstractReg
{

    ///Sigla da unidade da federação a que se refere a apuração do ICMS ST
    public $UF;
    /// Data inicial das informações contidas no arquivo
    public $DT_INI;
    /// Data final das informações contidas no arquivo
    public $DT_FIN;

    protected function setAttributes($data = [])
    {
        $nfs = $data['nf']->filter(function ($nf) {
                    $allowedE = $nf->tiponf == 'emitida';
                    $allowedR = $nf->tiponf == 'recebida';
                    $hasRed = $nf->predbc > 0.00 && $nf->vicmsdeson > 0.00;
                    $hasST = $nf->vstnf > 0.00;
                    return ($allowedE || $allowedR) && ($hasRed || $hasST);
                })->unique(function ($nf) {
            return $nf->destuf;
        });

        foreach ($nfs as $nf) {
            $this->UF = $nf->destuf;
            $this->DT_INI = Util::dateFormat($data['datainicio']);
            $this->DT_FIN = Util::dateFormat($data['datafim']);
            $this->addChildren("RegE210", [$data]);
            $this->addReg($this);
        }
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'UF'     => static::getBaseVR("Sigla da unidade da federação a que se refere a apuração do ICMS ST", 2, true, 'O'),
            'DT_INI' => static::getBaseVR("Data inicial a que a apuração se refere", 8, true),
            'DT_FIN' => static::getBaseVR("Data final a que a apuração se refere", 8, true),
        ];
    }

}
