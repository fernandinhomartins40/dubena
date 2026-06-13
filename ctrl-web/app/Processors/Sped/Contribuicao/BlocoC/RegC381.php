<?php
namespace App\Processors\Sped\Contribuicao\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO C381: DETALHAMENTO DA CONSOLIDAÇÃO – OPERAÇÕES DE VENDAS – PIS/PASEP
class RegC381 extends AbstractReg
{
    // Código da Situação Tributária referente ao PIS/PASEP, conforme a Tabela indicada no item 4.3.3.
    protected $CST_PIS;
    // Código fiscal de operação e prestação
    protected $COD_ITEM;
    // Valor do item
    protected $VL_ITEM;
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
        $this->CST_PIS = Util::fillStrWith($data->cstpis, 2, "0");
        $this->COD_ITEM = $data->cprod;
        $this->VL_ITEM = $data->vprod;
        $this->VL_BC_PIS = 0;
        $this->ALIQ_PIS = $data->ppis;
        $this->QUANT_BC_PIS = $data->vbcpis;
        $this->ALIQ_PIS_QUANT = 0;
        $this->VL_PIS = $data->vpis;
        $this->COD_CTA = $data->planoconta_id;
        $this->setGenericError("NF Item " . $data->cprod);

        return $this;
    } 
    protected function getValidationArray()
    {
        return [
            'CST_PIS'           => static::getBaseVR("Código da Situação Tributária referente ao PIS/PASEP", 2, true),
            'COD_ITEM'          => static::getBaseVR("Código do item", 60),
            'VL_ITEM'           => static::getBaseVR("Valor total dos itens ", false),
            'VL_BC_PIS'         => static::getBaseVR("Valor da base de cálculo do PIS/PASEP", false, false, "N"),
            'ALIQ_PIS'          => static::getBaseVR("Alíquota do PIS/PASEP (em percentual)", 8, false, "N"),
            'QUANT_BC_PIS'      => static::getBaseVR("Quantidade – Base de cálculo do PIS/PASEP", false, false, "N"),
            'ALIQ_PIS_QUANT'    => static::getBaseVR("Alíquota do PIS/PASEP (em reais)", false, false, "N"),
            'VL_PIS'            => static::getBaseVR("Valor do PIS/PASEP", false),
            'COD_CTA'           => static::getBaseVR("Código da conta analítica contábil debitada/creditada", 255, false, "N"),
        ];
    }
}

