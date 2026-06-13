<?php
namespace App\Processors\Sped\Contribuicao\BlocoD;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO D501 NOTA FISCAL/CONTA DE ENERGIA ELÉTRICA (CÓDIGO 06),
// NOTA FISCAL/CONTA DE FORNECIMENTO D'ÁGUA CANALIZADA (CÓDIGO 29)
// E NOTA FISCAL CONSUMO FORNECIMENTO DE GÁS (CÓDIGO 28)
class RegD501 extends AbstractReg
{
    // Código da Situação Tributária referente ao PIS, conforme a Tabela indicada no item 4.3.1
    protected $CST_PIS;
    // Valor total dos itens
    protected $VL_ITEM;
    // Código da Código da Base de Cálculo do Crédito, conforme a Tabela indicada no item 4.3.7
    protected $NAT_BC_CRED;
    // Valor total da BC PIS
    protected $VL_BC_PIS;
    // Aliquota PIS
    protected $ALIQ_PIS;
    // Valor total do PIS
    protected $VL_PIS;
    // Código da conta analítica contábil debitada/creditada
    protected $COD_CTA;


    protected function setAttributes($data = [])
    {
        $this->CST_PIS = Util::fillStrWith($data->cstpis, 2, "0");
        $this->VL_ITEM = Util::numberFormat($data->vprod);
        $this->NAT_BC_CRED = $data->piscofinstipobccredito;
        $this->VL_BC_PIS = Util::numberFormat($data->vbcpis);
        $this->ALIQ_PIS = Util::numberFormat($data->ppis);
        $this->VL_PIS = Util::numberFormat($data->vpis);
        $this->COD_CTA =  $data->planoconta_id;

        return $this;
    } 
    
    protected function getValidationArray()
    {
        return [
            'CST_PIS'       => static::getBaseVR("Código da Situação Tributária referente ao PIS/PASEP", 2, true),
            'VL_ITEM'       => static::getBaseVR("Valor Total dos Itens (Serviços)", false),
            'NAT_BC_CRED'   => static::getBaseVR("Código da Base de Cálculo do Crédito", 2, true, "N", ['03', '13']),
            'VL_BC_PIS'     => static::getBaseVR("Valor da base de cálculo do PIS/PASEP", false, false, "N"),
            'ALIQ_PIS'      => static::getBaseVR("Alíquota do PIS/PASEP (em percentual)", 8, false, "N"),
            'VL_PIS'        => static::getBaseVR("Valor do PIS/PASEP", false, false, "N"),
            'COD_CTA'       => static::getBaseVR("Código da conta analítica contábil debitada/creditada", 255, false, "N"),
        ];
    }
}
