<?php

namespace App\Processors\Sped\Contribuicao\BlocoM;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;
use App\Helpers\Utils\NfUtil;
use App\Processors\Sped\Util;

/**
 * REGISTRO M800: RECEITAS ISENTAS, NÃO ALCANÇADAS PELA INCIDÊNCIA DA CONTRIBUIÇÃO, SUJEITAS A ALÍQUOTA ZERO OU
 * DE VENDAS COM SUSPENSÃO – COFINS
 */
class RegM800 extends AbstractReg
{
    /**
     * Código da Situação Tributária referente ao COFINS – Tabela 4.3.3. 
     */
    protected $CST_COFINS;
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
            $cred = $i->vcofins == 0 && Util::hasIn($i->cstcofins, $arr);
            return $authorized && $cred;
        });

        $uniqueItems = $items->unique(function ($i) {
            return $i->cstcofins;
        })->sortBy('item_id');

        if($uniqueItems->count() === 0)
            $this->none = true;

        foreach ($uniqueItems as $uItem) {
            $itemFilter = $items->filter(function ($i) use($uItem) {
                return $i->cstcofins === $uItem->cstcofins;
            });

            $this->CST_COFINS = strlen($uItem->cstcofins) == 1 ? "0" . $uItem->cstcofins : $uItem->cstcofins;
            $this->VL_TOT_REC = Util::numberFormat($itemFilter->sum('vprod'));
            $this->DESC_COMPL = "";
            $this->COD_CTA =  $uItem->planoconta_id;

            $itemsCst = $data['nf']->filter(function ($i) use($uItem, $arr) {
                $equalty = $i->cstcofins === $uItem->cstcofins;
                $authorized = NfUtil::isAuthorized($i->nfsituacao_id);
                $cred = $i->vcofins == 0 && Util::hasIn($i->cstcofins, $arr);
                return $equalty && $authorized && $cred;
            });

            $uniqueItemsCst = $itemsCst->unique(function ($i) {
                return $i->piscofinsnatreceita;
            })->sortBy('item_id');

            foreach ($uniqueItemsCst as $uItemCst) {
                $itemFilter = $items->filter(function ($i) use($uItemCst) {
                    return $i->cstcofins === $uItemCst->cstcofins 
                        && $i->piscofinsnatreceita === $uItemCst->piscofinsnatreceita;
                });

                $uItemCst->vprodntrib = $itemFilter->sum('vprod');
                $this->addChildren("RegM810", $uItemCst);
            }

            $this->addReg($this);
        }

        return $this;
    } 
    protected function getValidationArray()
    {
        return [
            'CST_COFINS'    => static::getBaseVR("Código de Situação Tributária – CST", 2, true, "O", ['04', '05', '06', '07', '08', '09']),
            'VL_TOT_REC'    => static::getBaseVR("Valor total da receita bruta no período", false),
            'COD_CTA'       => static::getBaseVR("Código da conta analítica contábil debitada/creditada", 255, false, "N"),
            'DESC_COMPL'    => static::getBaseVR("Descrição Complementar da Natureza da Receita", false, false, "N"),
        ];
    }
}