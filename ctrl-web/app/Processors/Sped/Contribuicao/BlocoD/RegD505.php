<?php
namespace App\Processors\Sped\Contribuicao\BlocoD;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO D505 NOTA FISCAL/CONTA DE ENERGIA ELÉTRICA (CÓDIGO 06),
// NOTA FISCAL/CONTA DE FORNECIMENTO D'ÁGUA CANALIZADA (CÓDIGO 29)
// E NOTA FISCAL CONSUMO FORNECIMENTO DE GÁS (CÓDIGO 28)
class RegD505 extends AbstractReg
{
    // Código da Situação Tributária referente ao COFINS, conforme a Tabela indicada no item 4.3.1
    protected $CST_COFINS;
    // Valor total dos itens
    protected $VL_ITEM;
    // Código da Código da Base de Cálculo do Crédito, conforme a Tabela indicada no item 4.3.7
    protected $NAT_BC_CRED;
    // Valor total da BC COFINS
    protected $VL_BC_COFINS;
    // Aliquota COFINS
    protected $ALIQ_COFINS;
    // Valor total do COFINS
    protected $VL_COFINS;
    // Código da conta analítica contábil debitada/creditada
    protected $COD_CTA;


    protected function setAttributes($data = [])
    {
        $this->CST_COFINS = Util::fillStrWith($data->cstcofins, 2, "0");
        $this->VL_ITEM = Util::numberFormat($data->vprod);
        $this->NAT_BC_CRED = $data->piscofinstipobccredito;
        $this->VL_BC_COFINS = Util::numberFormat($data->vbccofins);
        $this->ALIQ_COFINS = Util::numberFormat($data->pcofins);
        $this->VL_COFINS = Util::numberFormat($data->vcofins);
        $this->COD_CTA =  $data->planoconta_id;
        $this->setGenericError("Bloco D RegD505 Item " . $data->vprod);
        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'CST_COFINS'    => static::getBaseVR("Código da Situação Tributária referente a COFINS", 2, true),
            'VL_ITEM'       => static::getBaseVR("Valor Total dos Itens", false),
            'NAT_BC_CRED'   => static::getBaseVR("Código da Base de Cálculo do Crédito", 2, true, "N"),
            'VL_BC_COFINS'  => static::getBaseVR("Valor da base de cálculo da COFINS", false, false, "N"),
            'ALIQ_COFINS'   => static::getBaseVR("Alíquota da COFINS (em percentual)", 8, false, "N"),
            'VL_COFINS'     => static::getBaseVR("Valor da COFINS", false, false, "N"),
            'COD_CTA'       => static::getBaseVR("Código da conta analítica contábil debitada/creditada", 255, false, "N"),
        ];
    }
}

