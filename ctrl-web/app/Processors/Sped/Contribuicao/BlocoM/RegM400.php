<?php

namespace App\Processors\Sped\Contribuicao\BlocoM;

use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;

/**
 * REGISTRO M400: RECEITAS ISENTAS, NÃO ALCANÇADAS PELA INCIDÊNCIA DA CONTRIBUIÇÃO, SUJEITAS A ALÍQUOTA ZERO OU DE        
 * VENDAS COM SUSPENSÃO – PIS/PASEP
 */
class RegM400 extends AbstractReg
{

    /**
     * Código da Situação Tributária referente ao PIS/PASEP – Tabela 4.3.3
     */
    protected $CST_PIS;
    /**
     * Valor total da receita bruta no período
     */
    protected $VL_TOT_REC;
    /**
     * Código da conta analítica contábil debitada/creditada
     */
    protected $COD_CTA;
    /**
     * Descrição Complementar da Natureza da Receita
     */
    protected $DESC_COMPL;

    protected function setAttributes($data = [])
    {
        $arr = ['04', '05', '06', '07', '08', '09'];
        $items = $data['nf']->filter(function ($i) use($arr) {
            $authorized = NfUtil::isAuthorized($i->nfsituacao_id);
            $cred = $i->vpis == 0 && $i->ppis == 0 && Util::hasIn($i->cstpis, $arr);
            return $authorized && $cred && $i->nfmodelo != '55';
        });

        $uniqueItems = $items->unique(function ($i) {
            return $i->cstpis;
        })->sortBy('item_id');

        if ($uniqueItems->isEmpty())
            $this->none = true;

        foreach ($uniqueItems as $uItem) {
            $itemFilter = $items->filter(function ($i) use($uItem) {
                return $i->cstpis === $uItem->cstpis;
            });

            $this->CST_PIS = Util::fillStrWith($uItem->cstpis, 2, '0');
            $this->VL_TOT_REC = Util::numberFormat($itemFilter->sum('vprod'));
            $this->DESC_COMPL = "";
            $this->COD_CTA = $uItem->planoconta_id;

            $itemsCst = $data['nf']->filter(function ($i) use ($uItem, $arr) {
                $equalty = $i->cstpis == $uItem->cstpis;
                $authorized = NfUtil::isAuthorized($i->nfsituacao_id);
                $cred = $i->vpis == 0 && Util::hasIn($i->cstpis, $arr);
                return $equalty && $authorized && $cred;
            });

            $uniqueNatureza = $itemsCst->unique(function ($i) {
                return $i->cstpis . $i->piscofinsnatreceita;
            })->sortBy('item_id');

            //buscando itens e agrupando por cst
            foreach ($uniqueNatureza as $uNat) {
                $itemFilter = $items->filter(function ($i) use($uNat) {
                    return $i->cstpis === $uNat->cstpis && $i->piscofinsnatreceita === $uNat->piscofinsnatreceita;
                });
                $uNat->vprodntrib = $itemFilter->sum('vprod');
                $this->addChildren("RegM410", $uNat);
            }
            $this->addReg($this);
        }
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'CST_PIS'       => static::getBaseVR("Código de Situação Tributária", 2, true, "O", ['04', '05', '06', '07', '08', '09']),
            'VL_TOT_REC'    => static::getBaseVR("Valor total da receita bruta no período", false),
            'COD_CTA'       => static::getBaseVR("Código da conta analítica contábil debitada/creditada", 255, false, "N"),
            'DESC_COMPL'    => static::getBaseVR("Descrição Complementar da Natureza da Receita", false, false, "N")
        ];
    }

}
