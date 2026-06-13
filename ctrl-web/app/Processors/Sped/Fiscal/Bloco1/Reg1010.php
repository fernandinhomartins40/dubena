<?php

namespace App\Processors\Sped\Fiscal\Bloco1;

use App\Processors\Sped\AbstractReg;

// REGISTRO 1010: ABERTURA DO BLOCO C
class Reg1010 extends AbstractReg
{

    public $IND_EXP;
    public $IND_CCRF;
    public $IND_COMB;
    public $IND_USINA;
    public $IND_VA;
    public $IND_EE;
    public $IND_CART;
    public $IND_FORM;
    public $IND_AER;

    protected function setAttributes($data = [])
    {
        $sped1010 = $data['empresa']->spedregistro1010;

        $this->IND_EXP = substr($sped1010, 0, 1);
        $this->IND_CCRF = substr($sped1010, 1, 1);
        $this->IND_COMB = substr($sped1010, 2, 1);
        $this->IND_USINA = substr($sped1010, 3, 1);
        $this->IND_VA = substr($sped1010, 4, 1);
        $this->IND_EE = substr($sped1010, 5, 1);
        $this->IND_CART = substr($sped1010, 6, 1);
        $this->IND_FORM = substr($sped1010, 7, 1);
        $this->IND_AER = substr($sped1010, 8, 1);

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'IND_EXP'   => static::getBaseVR(""),
            'IND_CCRF'  => static::getBaseVR(""),
            'IND_COMB'  => static::getBaseVR(""),
            'IND_USINA' => static::getBaseVR(""),
            'IND_VA'    => static::getBaseVR(""),
            'IND_EE'    => static::getBaseVR(""),
            'IND_CART'  => static::getBaseVR(""),
            'IND_FORM'  => static::getBaseVR(""),
            'IND_AER'   => static::getBaseVR("")
        ];
    }

}
