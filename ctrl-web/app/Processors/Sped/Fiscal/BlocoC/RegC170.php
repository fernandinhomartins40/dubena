<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

/**
 * Description of RegC170
 *
 * @author Jeferson
 */
class RegC170 extends AbstractReg
{

    protected $NUM_ITEM;
    protected $COD_ITEM;
    protected $DESCR_COMPL;
    protected $QTD;
    protected $UNID;
    protected $VL_ITEM;
    protected $VL_DESC;
    protected $IND_MOV;
    protected $CST_ICMS;
    protected $CFOP;
    protected $COD_NAT;
    protected $VL_BC_ICMS;
    protected $ALIQ_ICMS;
    protected $VL_ICMS;
    protected $VL_BC_ICMS_ST;
    protected $ALIQ_ST;
    protected $VL_ICMS_ST;
    protected $IND_APUR;
    protected $CST_IPI;
    protected $COD_ENQ;
    protected $VL_BC_IPI;
    protected $ALIQ_IPI;
    protected $VL_IPI;
    protected $CST_PIS;
    protected $VL_BC_PIS;
    protected $ALIQ_PIS;
    protected $QUANT_BC_PIS;
    protected $ALIQ_PIS_R;
    protected $VL_PIS;
    protected $CST_COFINS;
    protected $VL_BC_COFINS;
    protected $ALIQ_COFINS;
    protected $QUANT_BC_COFINS;
    protected $ALIQ_COFINS_R;
    protected $VL_COFINS;
    protected $COD_CTA;
    protected $a;

    protected function getValidationArray()
    {
        return [
            'NUM_ITEM'      => static::getBaseVR("Número sequencial do item no documento fiscal", 3),
            'COD_ITEM'      => static::getBaseVR("Código do item", 60),
            'DESCR_COMPL'   => static::getBaseVR("Descrição complementar do item como adotado no documento fiscal", false, false, "OC"),
            'QTD'           => static::getBaseVR("Quantidade do item"),
            'UNID'          => static::getBaseVR("Unidade do item", 6),
            'VL_ITEM'       => static::getBaseVR("Valor total do item"),
            'VL_DESC'       => static::getBaseVR("Valor do desconto comercial", false, false, "OC"),
            'IND_MOV'       => static::getBaseVR("Movimentação física do ITEM/PRODUTO", 1, true, "O", [0, 1]),
            'CST_ICMS'      => static::getBaseVR("Código da Situação Tributária referente ao ICMS", 3, true),
            'CFOP'          => static::getBaseVR("Código Fiscal de Operação e Prestação", 4, true),
            'COD_NAT'       => static::getBaseVR("Código da natureza da operação", 10, false, "OC"),
            'VL_BC_ICMS'    => static::getBaseVR("Valor da base de cálculo do ICMS", false, false, "OC"),
            'ALIQ_ICMS'     => static::getBaseVR("Alíquota do ICMS", 6, false, "OC"),
            'VL_ICMS'       => static::getBaseVR("Valor do ICMS creditado/debitado", false, false, "OC"),
            'VL_BC_ICMS_ST' => static::getBaseVR("Valor da base de cálculo referente à substituição tributária", false, false, "OC"),
            'ALIQ_ST'       => static::getBaseVR("Alíquota do ICMS da substituição tributária na unidade da federação de destino", false, false, "OC"),
            'VL_ICMS_ST'    => static::getBaseVR("Valor do ICMS referente à substituição tributária", false, false, "OC"),
            'IND_APUR'      => static::getBaseVR("Indicador de período de apuração do IPI", 1, true, "OC", [0, 1]),
            'CST_IPI'       => static::getBaseVR("Código da Situação Tributária referente ao IPI", 2, true, "OC", function($value) {
                        if (in_array(substr($this->CFOP, 0, 1), [1, 2, 3]) && $value >= 50) {
                            $this->addError("Para as operações de entrada, CFOP iniciado "
                                    . "por 1, 2 ou 3, o CST do IPI precisa menor ser que 50");
                        } elseif (in_array(substr($this->CFOP, 0, 1), [5, 6, 7]) && $value < 50) {
                            $this->addError("Para as operações de entrada, CFOP iniciado "
                                    . "por 5, 6, 7, o CST do IPI precisa ser maior ou igual a 50");
                        }
                    }),
            'COD_ENQ'         => static::getBaseVR("Código de enquadramento legal do IPI", 3, true, "OC"),
            'VL_BC_IPI'       => static::getBaseVR("Valor da base de cálculo do IPI", false, false, "OC"),
            'ALIQ_IPI'        => static::getBaseVR("Alíquota do IPI", 6, false, "OC"),
            'VL_IPI'          => static::getBaseVR("Valor do IPI creditado/debitado", false, false, "OC"),
            'CST_PIS'         => static::getBaseVR("Código da Situação Tributária referente ao PIS", 2, true, "OC"),
            'VL_BC_PIS'       => static::getBaseVR("Valor da base de cálculo do PIS", false, false, "OC"),
            'ALIQ_PIS'        => static::getBaseVR("Alíquota do PIS (em percentual)", 8, false, "OC"),
            'QUANT_BC_PIS'    => static::getBaseVR("Quantidade – Base de cálculo PIS", false, false, "OC"),
            'ALIQ_PIS_R'      => static::getBaseVR("Alíquota do PIS (em reais) ", false, false, "OC"),
            'VL_PIS'          => static::getBaseVR("Valor do PIS", false, false, "OC"),
            'CST_COFINS'      => static::getBaseVR("Código da Situação Tributária referente ao COFINS", 2, true, "OC"),
            'VL_BC_COFINS'    => static::getBaseVR("Valor da base de cálculo da COFINS", false, false, "OC"),
            'ALIQ_COFINS'     => static::getBaseVR("Alíquota do COFINS (em percentual)", 8, false, "OC"),
            'QUANT_BC_COFINS' => static::getBaseVR("Quantidade – Base de cálculo COFINS", false, false, "OC"),
            'ALIQ_COFINS_R'   => static::getBaseVR("Alíquota do COFINS (em reais)", false, false, "OC"),
            'VL_COFINS'       => static::getBaseVR("Valor do COFINS", false, false, "OC"),
            'COD_CTA'         => static::getBaseVR("Código da conta analítica contábil debitada/creditada", false, false, "OC")
        ];
    }

    protected function setAttributes($item = array())
    {
        $this->a = $item->nf_id;
        $this->NUM_ITEM = $item->NUM_ITEM;
        $this->COD_ITEM = $item->cprod;
        $this->DESCR_COMPL = ""; //verificar a necessidade
        $this->QTD = $item->qcom;
        $this->UNID = $item->ucom;
        $this->VL_ITEM = Util::numberFormat($item->vprod, 2);
        $this->VL_DESC = Util::numberFormat($item->vdesc, 2);
        $this->IND_MOV = "1";
        $this->CST_ICMS = Util::fillStrWith($item->cst, 3, "0");
        $this->CFOP = $item->cfop;
        $this->COD_NAT = $item->nfoperacao_id;
        $this->VL_BC_ICMS = Util::numberFormat($item->vbc, 2);
        $this->ALIQ_ICMS = Util::numberFormat($item->picms, 2);
        $this->VL_ICMS = Util::numberFormat($item->vicms, 2);
        $this->VL_BC_ICMS_ST = Util::numberFormat($item->vbcstret, 2);
        $this->ALIQ_ST = 0; //****
        $this->VL_ICMS_ST = Util::numberFormat($item->vicmsstret, 2);
        $this->IND_APUR = "0";
        $this->CST_IPI = Util::fillStrWith($item->cstipi, 2, "0");
        $this->COD_ENQ = "";
        $this->VL_BC_IPI = Util::numberFormat($item->vbcipi, 2);
        $this->ALIQ_IPI = Util::numberFormat($item->pipi, 2);
        $this->VL_IPI = Util::numberFormat($item->vipi, 2);
        $this->CST_PIS = Util::fillStrWith($item->cstpis, 2, "0");
        $this->VL_BC_PIS = Util::numberFormat($item->vbcpis, 2);
        $this->ALIQ_PIS = Util::numberFormat($item->ppis, 2);
        $this->QUANT_BC_PIS = 0; // ****
        $this->ALIQ_PIS_R = 0;
        $this->VL_PIS = Util::numberFormat($item->vpis, 2);
        $this->CST_COFINS = Util::fillStrWith($item->cstcofins, 2, "0");
        $this->VL_BC_COFINS = Util::numberFormat($item->vbccofins, 2);
        $this->ALIQ_COFINS = Util::numberFormat($item->pcofins, 2);
        $this->QUANT_BC_COFINS = 0; // ****
        $this->ALIQ_COFINS_R = 0;
        $this->VL_COFINS = Util::numberFormat($item->vcofins, 2);
        $this->COD_CTA = "";

        if ($item->tiponf == 'emitida') {
            $tipo = 'Notas Fiscal';
        } else {
            $tipo = "Documento";
        }
//        if ($item->nfnumero == 7439)
//            dump($this);

        $this->setGenericError($tipo . " número " . $item->nfnumero . " e produto " . $item->produto);

        if ($item->nfmodelo === '55' && $item->tiponf == 'emitida') {
//        if ($item->nfmodelo === '55' && $item->tipo == 0 && $item->nfefinalidade == 4 && $item->chaveacessoref) {
//            $i = $item;
//            $i->nfmodelo = substr($item->chaveacessoref, 20, 2);
//            $i->nfserie = substr($item->chaveacessoref, 22, 3);
//            $i->nfnumero = substr($item->chaveacessoref, 25, 9);
//            $this->addChildren('RegC176', $item);
        }
        return $this;
    }

}
