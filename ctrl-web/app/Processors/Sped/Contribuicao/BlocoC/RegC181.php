<?php
namespace App\Processors\Sped\Contribuicao\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO C181: DETALHAMENTO DA CONSOLIDAÇÃO – OPERAÇÕES DE VENDAS – PIS/PASEP
class RegC181 extends AbstractReg
{
    // Código da Situação Tributária referente ao PIS/PASEP, conforme a Tabela indicada no item 4.3.3.
    protected $CST_PIS;
    // Código fiscal de operação e prestação
    protected $CFOP;
    // Valor do item
    protected $VL_ITEM;
    // Valor do desconto comercial / Exclusão
    protected $VL_DESC;
    // Valor da base de cálculo do PIS/PASEP
    protected $VL_BC_PIS;
    // Alíquota do PIS/PASEP (em percentual)
    protected $ALIQ_PIS;
    // Quantidade – Base de cálculo PIS/PASEP
    protected $QUANT_BC_PIS;
    // Alíquota do PIS/PASEP (em reais)
    protected $ALIQ_PIS_QUANT;
    // Valor do PIS/PASEP
    protected $VL_PIS;
    // Código da conta analítica contábil debitada/creditada
   protected $COD_CTA;


    protected function setAttributes($data = [])
    {
        $this->CST_PIS = "";
        $this->CFOP = "";
        $this->VL_ITEM = "";
        $this->VL_DESC = "";
        $this->VL_BC_PIS = "";
        $this->ALIQ_PIS = "";
        $this->QUANT_BC_PIS = "";
        $this->ALIQ_PIS_QUANT = "";
        $this->VL_PIS = "";
        $this->COD_CTA =  $data->planoconta_id;
        return $this;
    } 
    protected function validaRegistro()
    {
        if (Util::isNullOrEmpty($this->CST_PIS))
            $this->addError("C181 - Código da Situação Tributária referente ao PIS/PASEP não informada.");

        if (Util::isNullOrEmpty($this->CFOP))
            $this->addError("C181 - Código fiscal de operação e prestação não informado.");


        return $this;
    }

    protected function getValidationArray()
    {

    }

    protected function layout()
    {
        $this->line = parent::setLine([
            $this->numReg,
            $this->CST_PIS,
            $this->CFOP,
            Util::numberFormat($this->VL_ITEM),
            Util::numberFormat($this->VL_DESC),
            Util::numberFormat($this->VL_BC_PIS),
            Util::numberFormat($this->ALIQ_PIS),
            Util::numberFormat($this->QUANT_BC_PIS),
            Util::numberFormat($this->ALIQ_PIS_QUANT),
            Util::numberFormat($this->VL_PIS),
            $this->COD_CTA
        ]);

        return $this;
    }
}
