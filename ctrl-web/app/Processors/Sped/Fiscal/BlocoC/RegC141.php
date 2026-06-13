<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegC141 extends AbstractReg
{

    /// Fatura do Documento
    public $NUM_PARC;
    /// Número da parcela a receber/pagar

    public $DT_VCTO;
    /// Data de vencimento da parcela

    public $VL_PARC;

    /// Valor da parcela a receber/pagar

    protected function setAttributes($par = [])
    {
        $this->NUM_PARC = $par->numeroparcela;
        $this->DT_VCTO = Util::dateFormat($par->datavencimento);
        $this->VL_PARC = Util::numberFormat($par->valor, 2);
        
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'NUM_PARC' => static::getBaseVR("Número da parcela a receber/pagar", 4),
            'DT_VCTO' => static::getBaseVR("Data de vencimento da parcela", 8, true),
            'VL_PARC' => static::getBaseVR("Valor da parcela a receber/pagar"),
        ];
    }

}
