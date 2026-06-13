<?php

namespace App\Processors\Sped\Contribuicao\BlocoM;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;

/**
 * REGISTRO M100: CRÉDITO DE PIS/PASEP RELATIVO AO PERÍODO
 */
class RegM100 extends AbstractReg
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
    protected $VL_BC_PIS;
    /**
     * Alíquota do PIS/PASEP (em percentual)
     */
    protected $ALIQ_PIS;
    /**
     * Quantidade – Base de cálculo PIS
     */
    protected $QUANT_BC_PIS;
    /**
     * Alíquota do PIS (em reais)
     */
    protected $ALIQ_PIS_QUANT;
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
    protected $VL_CRED_DIF;
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
        $items = $data['nf']->filter(function ($i) use ($arr) {
            if($i->nfpisaliqcred != 0 && !is_null($i->nfpisaliqcred)){
                $i->ppis = $i->ppis * $i->nfpisaliqcred / 100;
                $i->vpis = $i->vpis * $i->nfpisaliqcred / 100;
            }
            $authorized = NfUtil::isAuthorized($i->nfsituacao_id);
            $cst_exist = Util::hasIn($i->cstpis, $arr);
            return $authorized && $cst_exist;
        });

        $uniqueItems = $items->unique(function ($i) {
            return $i->piscofinstipocredito . $i->cstpis;
        })->sortBy('item_id');

        if($uniqueItems->count() === 0)
            $this->none = true;

        foreach ($uniqueItems as $uItem) {
            $itemFilter = $items->filter(function ($i) use($uItem) {
                return $i->piscofinstipocredito === $uItem->piscofinstipocredito && $i->ppis === $uItem->ppis;
            });

            $sumVpis = $itemFilter->sum('vpis');
            $sumVbcpis = $itemFilter->sum('vbcpis');

            $this->COD_CRED = $uItem->piscofinstipocredito !== null ? Util::fillStrWith($uItem->piscofinstipocredito, 3, "0") : "";
            $this->IND_CRED_ORI = "0";
            $this->VL_BC_PIS = Util::numberFormat($sumVbcpis);
            $this->ALIQ_PIS = Util::numberFormat($uItem->ppis);
            $this->QUANT_BC_PIS = "";
            $this->ALIQ_PIS_QUANT = "";
            $this->VL_CRED = Util::numberFormat($sumVpis);
            $this->VL_AJUS_ACRES = 0;
            $this->VL_AJUS_REDUC = 0;
            $this->VL_CRED_DIF = 0;
            $val_cred_disp = $sumVpis + $this->VL_AJUS_ACRES - $this->VL_AJUS_REDUC - $this->VL_CRED_DIF;
            $this->VL_CRED_DISP = Util::numberFormat($val_cred_disp);
            $this->IND_DESC_CRED = "1";
            $this->VL_CRED_DESC = Util::numberFormat(0);
            $this->SLD_CRED = Util::numberFormat($val_cred_disp);

            $itemsCst = $data['nf']->filter(function ($i) use($uItem, $arr) {
                if($i->nfpisaliqcred != 0 && !is_null($i->nfpisaliqcred))
                    $i->vpis = $i->vpis * $i->nfpisaliqcred / 100;

                $equilty = $i->piscofinstipocredito == $uItem->piscofinstipocredito;
                $authorized = NfUtil::isAuthorized($i->nfsituacao_id);
                $cst_exist = Util::hasIn($i->cstpis, $arr);
                return $authorized && $cst_exist && $equilty;
            });

            $uniqueItemsCst = $itemsCst->unique(function ($i) {
                return $i->piscofinstipobccredito . $i->cstpis . $i->ppis;
            })->sortBy('item_id');

            //buscando itens e agrupando por cst
            foreach ($uniqueItemsCst as $uItemCst) {
                $itemFilter = $itemsCst->filter(function ($i) use($uItemCst) {
                    $tipo = $i->piscofinstipobccredito === $uItemCst->piscofinstipobccredito;
                    $pis = $i->cstpis === $uItemCst->cstpis;
                    $per = $i->ppis === $uItemCst->ppis;
                    return $tipo && $pis && $per;
                });
                $reg105 = (object) array();
                $reg105->nf_id = $uItem->nf_id;
                $reg105->cstpis = $uItemCst->cstpis;
                $reg105->piscofinstipobccredito = $uItemCst->piscofinstipobccredito;
                $reg105->vpis = $itemFilter->sum('vpis');
                $reg105->vbcpis = $itemFilter->sum('vbcpis');
                $this->addChildren("RegM105", $reg105);
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
            'VL_BC_PIS'         => static::getBaseVR("Valor da Base de Cálculo do Crédito", false, false, "N"),
            'ALIQ_PIS'          => static::getBaseVR("Alíquota do PIS/PASEP (em percentual)", 8, false, "N"),
            'QUANT_BC_PIS'      => static::getBaseVR("Quantidade – Base de cálculo PIS", false, false, "N"),
            'ALIQ_PIS_QUANT'    => static::getBaseVR("Alíquota do PIS (em reais)", false, false, "N"),
            'VL_CRED'           => static::getBaseVR("Valor total do crédito apurado no período", false),
            'VL_AJUS_ACRES'     => static::getBaseVR("Valor total dos ajustes de acréscimo", false),
            'VL_AJUS_REDUC'     => static::getBaseVR("Valor total dos ajustes de redução", false),
            'VL_CRED_DIF'       => static::getBaseVR("Valor total do crédito diferido no período", false),
            'VL_CRED_DISP'      => static::getBaseVR("Valor Total do Crédito Disponível relativo ao Período", false),
            'IND_DESC_CRED'     => static::getBaseVR("Indicador de opção de utilização do crédito disponível no período", 1, true, "O", ['0', '1']),
            'VL_CRED_DESC'      => static::getBaseVR("Valor do Crédito disponível, descontado da contribuição apurada no próprio período", false, false, "N"),
            'SLD_CRED'          => static::getBaseVR("Saldo de créditos a utilizar em períodos futuros", false)
        ];
    }
}
