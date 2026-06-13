<?php
namespace App\Processors\Sped\Contribuicao\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO C385: DETALHAMENTO DA CONSOLIDAÇÃO – OPERAÇÕES DE VENDAS – PIS/PASEP
class RegC385 extends AbstractReg
{
    // Código da Situação Tributária referente ao COFINS, conforme a Tabela indicada no item 4.3.3.
    protected $CST_COFINS;
    // Código fiscal de operação e prestação
    protected $COD_ITEM;
    // Valor do item
    protected $VL_ITEM;
    // Valor da base de cálculo do COFINS
    protected $VL_BC_COFINS;
    // Alíquota do COFINS (em percentual)
    protected $ALIQ_COFINS;
    // Quantidade – Base de cálculo COFINS
    protected $QUANT_BC_COFINS;
    // Alíquota do COFINS (em reais)
    protected $ALIQ_COFINS_QUANT;
    // Valor do COFINS
    protected $VL_COFINS;
    // Código da conta analítica contábil debitada/creditada
    protected $COD_CTA;


    protected function setAttributes($data = [])
    {
        $this->CST_COFINS = Util::fillStrWith($data->cstcofins, 2, "0");
        $this->COD_ITEM = $data->cprod;
        $this->VL_ITEM = $data->vprod;
        $this->VL_BC_COFINS = $data->vbccofins;
        $this->ALIQ_COFINS = $data->pcofins;
        $this->QUANT_BC_COFINS = 0;
        $this->ALIQ_COFINS_QUANT = 0;
        $this->VL_COFINS = $data->vcofins;
        $this->COD_CTA =  $data->planoconta_id;
        $this->setGenericError("NF Item" . $data->cprod);
        return $this;
    } 
    protected function getValidationArray()
    {
        return [
            'CST_COFINS'        => static::getBaseVR("Código da Situação Tributária referente a COFINS", 2, true),
            'COD_ITEM'          => static::getBaseVR("Código do item", 60),
            'VL_ITEM'           => static::getBaseVR("Valor total dos itens", false),
            'VL_BC_COFINS'      => static::getBaseVR("Valor da base de cálculo da COFINS", false, false, "N"),
            'ALIQ_COFINS'       => static::getBaseVR("Alíquota da COFINS (em percentual)", 8, false, "N"),
            'QUANT_BC_COFINS'   => static::getBaseVR("Quantidade – Base de cálculo da COFINS", false, false, "N"),
            'ALIQ_COFINS_QUANT' => static::getBaseVR("Alíquota da COFINS (em reais)", false, false, "N"),
            'VL_COFINS'         => static::getBaseVR("Valor da COFINS", false),
            'COD_CTA'           => static::getBaseVR("Código da conta analítica contábil debitada/creditada", 255, false, "N")
        ];
    }
}
