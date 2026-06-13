<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegC370 extends AbstractReg
{

    /// Item
    public $NUM_ITEM;
    /// Código Item
    public $COD_ITEM;
    /// Quantidade
    public $QTD;
    /// Unidade Medida
    public $UNIDADE;
    /// Valor total do item
    public $VL_ITEM;
    /// Valor do desconto
    public $VL_DESC;

    protected function setAttributes($item = [])
    {
        $this->NUM_ITEM = $item->index;
        $this->COD_ITEM = $item->cprod;
        $this->QTD = Util::numberFormat($item->qcom, 3);
        $this->UNIDADE = $item->unidade_medida;
        $this->VL_ITEM = Util::numberFormat($item->vdesc, 2);
        $this->VL_DESC = Util::numberFormat($item->vprod, 2);

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            
        ];
    }

}
