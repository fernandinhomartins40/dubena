<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

/**
 * Description of RegC170
 *
 * @author Jeferson
 */
class RegC176 extends AbstractReg
{

    protected $COD_MOD_ULT_E;
    protected $NUM_DOC_ULT_E;
    protected $SER_ULT_E;
    protected $DT_ULT_E;
    protected $COD_PART_ULT_E;
    protected $QUANT_ULT_E;
    protected $VL_UNIT_ULT_E;
    protected $VL_UNIT_BC_ST;
    protected $CHAVE_NFE_ULT_E;
    protected $NUM_ITEM_ULT_E;
    protected $VL_UNIT_BC_ICMS_ULT_E;
    protected $ALIQ_ICMS_ULT_E;
    protected $VL_UNIT_LIMITE_BC_ICMS_ULT_E;
    protected $VL_UNIT_ICMS_ULT_E;
    protected $ALIQ_ST_ULT_E;
    protected $VL_UNIT_RES;
    protected $COD_RESP_RET;
    protected $COD_MOT_RES;
    protected $CHAVE_NFE_RET;
    protected $COD_PART_NFE_RET;
    protected $SER_NFE_RET;
    protected $NUM_NFE_RET;
    protected $ITEM_NFE_RET;
    protected $COD_DA;
    protected $NUM_DA;

    protected function getValidationArray()
    {
        return [
            'COD_MOD_ULT_E'                => static::getBaseVR("Código do modelo do documento fiscal relativa a última entrada", 2, true),
            'NUM_DOC_ULT_E'                => static::getBaseVR("Número do documento fiscal relativa a última entrada", 9),
            'SER_ULT_E'                    => static::getBaseVR("Série do documento fiscal relativa a última entrada", 3, false, "OC"),
            'DT_ULT_E'                     => static::getBaseVR("Data relativa a última entrada da mercadoria", 8, true),
            'COD_PART_ULT_E'               => static::getBaseVR("Código do participante relativo a ultima entrada", 60),
            'QUANT_ULT_E'                  => static::getBaseVR("Quantidade do item relativa a última entrada"),
            'VL_UNIT_ULT_E'                => static::getBaseVR("Valor unitário da mercadoria constante na NF relativa a última entrada inclusive despesas acessórias"),
            'VL_UNIT_BC_ST'                => static::getBaseVR("Valor unitário da base de cálculo do imposto pago por substituição"),
            'CHAVE_NFE_ULT_E'              => static::getBaseVR("Número completo da chave da NFe relativo à última entrada", 44, true, "OC"),
            'NUM_ITEM_ULT_E'               => static::getBaseVR("Número sequencial do item na NF entrada que corresponde à mercadoria objeto de pedido de ressarcimento", 3, false, "OC"),
            'VL_UNIT_BC_ICMS_ULT_E'        => static::getBaseVR("Valor unitário da base de cálculo da operação própria do remetente sob o regime comum de tributação", 10, false, "OC"),
            'ALIQ_ICMS_ULT_E'              => static::getBaseVR("Alíquota do ICMS aplicável à última entrada da mercadoria", false, false, "OC"),
            'VL_UNIT_LIMITE_BC_ICMS_ULT_E' => static::getBaseVR("Valor unitário da base de cálculo do ICMS relativo à última entrada da mercadoria, limitado ao valor da BC da retenção", 6, false, "OC"),
            'VL_UNIT_ICMS_ULT_E'           => static::getBaseVR("Valor unitário do crédito de ICMS sobre operações próprias do remetente, relativo à última entrada da mercadoria, decorrente da quebra da ST", false, false, "OC"),
            'ALIQ_ST_ULT_E'                => static::getBaseVR("Alíquota do ICMS ST relativa à última entrada da mercadoria", false, false, "OC"),
            'VL_UNIT_RES'                  => static::getBaseVR("Valor unitário do ressarcimento", false, false, "OC"),
            'COD_RESP_RET'                 => static::getBaseVR("Código que indica o responsável pela retenção do ICMS-ST", 1, false, "OC", [1, 2, 3]),
            'COD_MOT_RES'                  => static::getBaseVR("Código do motivo do ressarcimento", 1, true, "OC", [1, 2, 3, 4, 5, 9]),
            'CHAVE_NFE_RET'                => static::getBaseVR("Número completo da chave da NF-e emitida pelo substituto, na qual consta o valor do ICMS-ST Retido", 44, true, "OC"),
            'COD_PART_NFE_RET'             => static::getBaseVR("Código do participante do emitente da NF-e em que houve a retenção do ICMS-ST", 60, true, "OC"),
            'SER_NFE_RET'                  => static::getBaseVR("Série da NF-e em que houve a retenção do ICMS ST", 3, false, "OC"),
            'NUM_NFE_RET'                  => static::getBaseVR("Número da NF-e em que houve a retenção do ICMS ST", 9, false, "OC"),
            'ITEM_NFE_RET'                 => static::getBaseVR("Número sequencial do item na NF-e em que houve a retenção do ICMS-ST, que corresponde à mercadoria objeto de pedido de ressarcimento", 3, false, "OC"),
            'COD_DA'                       => static::getBaseVR("Código do modelo do documento de arrecadação", 1, true, "OC"),
            'NUM_DA'                       => static::getBaseVR("Número do documento de arrecadação estadual", false, false, "OC")
        ];
    }

    protected function setAttributes($item = array())
    {

        $this->COD_MOD_ULT_E = $item->nfmodelo;
        $this->NUM_DOC_ULT_E = $item->nfnumero;
        $this->SER_ULT_E = $item->nfserie;
        $this->DT_ULT_E = Util::dateFormat($item->datahoraentradasaida);
        $this->COD_PART_ULT_E = $item->cliente_id;
        $this->QUANT_ULT_E = $item->qcom;
        $this->VL_UNIT_ULT_E = Util::numberFormat($item->vuncom);
        $this->VL_UNIT_BC_ST = Util::numberFormat($item->vbc);
        $this->CHAVE_NFE_ULT_E;
        $this->NUM_ITEM_ULT_E;
        $this->VL_UNIT_BC_ICMS_ULT_E;
        $this->ALIQ_ICMS_ULT_E;
        $this->VL_UNIT_LIMITE_BC_ICMS_ULT_E;
        $this->VL_UNIT_ICMS_ULT_E;
        $this->ALIQ_ST_ULT_E;
        $this->VL_UNIT_RES;
        $this->COD_RESP_RET;
        $this->COD_MOT_RES;
        $this->CHAVE_NFE_RET;
        $this->COD_PART_NFE_RET;
        $this->SER_NFE_RET;
        $this->NUM_NFE_RET;
        $this->ITEM_NFE_RET;
        $this->COD_DA;
        $this->NUM_DA;
        $this->setGenericError("Produto " . $item->produto);
        return $this;
    }

}
