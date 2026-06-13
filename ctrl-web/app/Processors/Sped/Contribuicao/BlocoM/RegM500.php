<?php

namespace App\Processors\Sped\Contribuicao\BlocoM;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;

/**
 * REGISTRO M500: CRÉDITO DE Cofins RELATIVO AO PERÍODO
 */
class RegM500 extends AbstractReg
{
    /**
     * Código de Tipo de Crédito apurado no período, conforme a Tabela 4.3.6.
     */
    protected $COD_CRED;
    /**
     * Indicador de Crédito Oriundo de:
     * 0 – Operações próprias
     * 1 – Evento de incorporação, cisão ou fusão
     */
    protected $IND_CRED_ORI;
    /**
     * Valor da Base de Cálculo do Crédito
     */
    protected $VL_BC_COFINS;
    /**
     * Alíquota do Cofins (em percentual)
     */
    protected $ALIQ_COFINS;
    /**
     * Quantidade – Base de cálculo COFINS
     */
    protected $QUANT_BC_COFINS;
    /**
     * Alíquota do COFINS (em reais)
     */
    protected $ALIQ_COFINS_QUANT;
    /**
     * Valor total do crédito apurado no período
     */
    protected $VL_CRED;
    /**
     * Valor total dos ajustes de acréscimo
     */
    protected $VL_AJUS_ACRES;
    /**
     * Valor total dos ajustes de redução
     */
    protected $VL_AJUS_REDUC;
    /**
     * Valor total do crédito diferido no período
     */
    protected $VL_CRED_DIFER;
    /**
     * Valor Total do Crédito Disponível relativo ao Período (08 + 09 – 10 – 11)
     */
    protected $VL_CRED_DISP;
    /**
     * Indicador de opção de utilização do crédito disponível no período:
     * 0 – Utilização do valor total para desconto da contribuição apurada no período, no Registro M200;
     * 1 – Utilização de valor parcial para desconto da contribuição apurada no período, no Registro M200.
     */
    protected $IND_DESC_CRED;
    /**
     * Valor do Crédito disponível, descontado da contribuição apurada no próprio período.
     * Se IND_DESC_CRED=0, informar o valor total do Campo 12;
     * Se IND_DESC_CRED=1, informar o valor parcial do Campo 12.
     */
    protected $VL_CRED_DESC;
    /**
     * Saldo de créditos a utilizar em períodos futuros (12 – 14)
     */
    protected $SLD_CRED;

    protected function setAttributes($data = [])
    {
        $arr = ['50', '51', '52', '53', '54', '55', '56', '60', '61', '62', '63', '64', '65', '66'];
        $new_data = clone $data['nf'];
        $items = $new_data->filter(function ($i) use($arr) {
            if($i->nfcofinsaliqcred != 0 && !is_null($i->nfcofinsaliqcred)){
                $i->pcofins = $i->pcofins * $i->nfcofinsaliqcred / 100;
                $i->vcofins = $i->vcofins * $i->nfcofinsaliqcred / 100;
            }
            $authorized = NfUtil::isAuthorized($i->nfsituacao_id);
            $hasIn = Util::hasIn($i->cstcofins, $arr);
            return $authorized && $hasIn;
        });

        $uniqueItems = $items->unique(function ($i) {
            return $i->pcofins . $i->piscofinstipocredito . $i->cstcofins;
        })->sortBy('item_id');

        if($uniqueItems->isEmpty())
            $this->none = true;

        foreach ($uniqueItems as $uItem) {
            $itemFilter = $items->filter(function ($i) use($uItem) {
                return $i->pcofins === $uItem->pcofins 
                && $i->piscofinstipocredito === $uItem->piscofinstipocredito;
            });
            $sumVc = $itemFilter->sum('vcofins');
            $sumVbc = $itemFilter->sum('vbccofins');
            $this->COD_CRED = $uItem->piscofinstipocredito !== null ? Util::fillStrWith($uItem->piscofinstipocredito, 3, "0") : "";
            $this->IND_CRED_ORI = "0";
            $this->VL_BC_COFINS = Util::numberFormat($sumVbc);
            $this->ALIQ_COFINS = Util::numberFormat($uItem->pcofins);
            $this->QUANT_BC_COFINS = "";
            $this->ALIQ_COFINS_QUANT = "";
            $this->VL_CRED = Util::numberFormat($sumVc);
            $this->VL_AJUS_ACRES = 0;
            $this->VL_AJUS_REDUC = 0;
            $this->VL_CRED_DIFER = 0;
            $val_cred_disp = $sumVc + $this->VL_AJUS_ACRES - $this->VL_AJUS_REDUC - $this->VL_CRED_DIFER;
            $this->VL_CRED_DISP = Util::numberFormat($val_cred_disp);
            $this->IND_DESC_CRED = "1";
            $this->VL_CRED_DESC = Util::numberFormat(0);
            $this->SLD_CRED = Util::numberFormat($val_cred_disp);

            $itemsCst = $data['nf']->filter(function ($i) use($uItem, $arr) {
                if($i->nfcofinsaliqcred != 0 && !is_null($i->nfcofinsaliqcred))  
                    $i->vcofins = $i->vcofins * $i->nfcofinsaliqcred / 100;

                $authorized = NfUtil::isAuthorized($i->nfsituacao_id);
                $equilty = $i->piscofinstipocredito == $uItem->piscofinstipocredito;
                $cst_exist = Util::hasIn($i->cstcofins, $arr);
                return $authorized && $equilty && $cst_exist;
            });

            $uniqueItemsCst = $itemsCst->unique(function ($i) {
                return $i->cstcofins . $i->piscofinstipobccredito . $i->pcofins;
            })->sortBy('item_id');

            //buscando itens e agrupando por cst
            foreach ($uniqueItemsCst as $uItemCst) {
                $itemu = $itemsCst->filter(function ($i) use($uItemCst) {
                    return $i->cstcofins === $uItemCst->cstcofins 
                        && $i->pcofins === $uItemCst->pcofins
                        && $i->piscofinstipobccredito === $uItemCst->piscofinstipobccredito;
                });

                $reg505 = (object) array();
                $reg505->piscofinstipobccredito = $uItemCst->piscofinstipobccredito;
                $reg505->cstcofins = $uItemCst->cstcofins;
                $reg505->vbccofins = $itemu->sum('vbccofins');
                $reg505->vcofins = $itemu->sum('vcofins');
                $this->addChildren("RegM505", $reg505);
            }
            $this->setGenericError("Crédito NF id: $uItem->nf_id");
            $this->addReg($this);
        }
        return $this;
    } 
    protected function getValidationArray()
    {
        return [
            'COD_CRED'          => static::getBaseVR("Código de Tipo de Crédito apurado no período", 3, true),
            'IND_CRED_ORI'      => static::getBaseVR("Indicador de Crédito Oriundo", 1, true, "O", ['0', '1']),
            'VL_BC_COFINS'      => static::getBaseVR("Valor da Base de Cálculo do Crédito", false, false, "N"),
            'ALIQ_COFINS'       => static::getBaseVR("Alíquota da COFINS (em percentual)", 8, false, "N"),
            'QUANT_BC_COFINS'   => static::getBaseVR("Quantidade – Base de cálculo COFINS", false, false, "N"),
            'ALIQ_COFINS_QUANT' => static::getBaseVR("Alíquota da COFINS (em reais)", false, false, "N"),
            'VL_CRED'           => static::getBaseVR("Valor total do crédito apurado no período", false),
            'VL_AJUS_ACRES'     => static::getBaseVR("Valor total dos ajustes de acréscimo", false),
            'VL_AJUS_REDUC'     => static::getBaseVR("Valor total dos ajustes de redução", false),
            'VL_CRED_DIFER'     => static::getBaseVR("Valor total do crédito diferido no período", false),
            'VL_CRED_DISP'      => static::getBaseVR("Valor Total do Crédito Disponível relativo ao Período", false),
            'IND_DESC_CRED'     => static::getBaseVR("Indicador de utilização do crédito disponível no período", 1, true),
            'VL_CRED_DESC'      => static::getBaseVR("Valor do Crédito disponível, descontado da contribuição apurada no próprio período", false, false, "N"),
            'SLD_CRED'          => static::getBaseVR("Saldo de créditos a utilizar em períodos futuros", false)
        ];
    }
}