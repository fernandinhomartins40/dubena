<?php

namespace App\Processors\Sped\Contribuicao\BlocoM;

use App\Processors\Sped\Util;
use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

/**
 * REGISTRO M810: DDETALHAMENTO DAS RECEITAS ISENTAS, NÃO ALCANÇADAS PELA
 * INCIDÊNCIA DA CONTRIBUIÇÃO, SUJEITAS A ALÍQUOTA ZERO OU DE VENDAS COM
 * SUSPENSÃO – COFINS
 */
class RegM810 extends AbstractReg
{
    /**
     * Código da Situação Tributária referente ao PIS/PASEP – Tabela 4.3.3.
     */
    protected $NAT_REC;
    /**
     * Valor total da receita bruta no período
     */
    protected $VL_REC;
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
        $this->NAT_REC = $data->piscofinsnatreceita;
        $this->VL_REC = Util::numberFormat($data->vprodntrib);
        $this->COD_CTA = $data->planoconta_id;
        $this->DESC_COMPL = "";
        $this->setGenericError("NF id: $data->nf_id");

        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'NAT_REC'       => static::getBaseVR("Natureza da Receita", 3, true),
            'VL_REC'        => static::getBaseVR("Valor da receita bruta no período", false),
            'COD_CTA'       => static::getBaseVR("Código da conta analítica contábil debitada/creditada", 255, false, "N"),
            'DESC_COMPL'    => static::getBaseVR("Descrição Complementar da Natureza da Receita", false, false, "N"),
        ];
    }
}