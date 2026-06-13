<?php

namespace App\Processors\Sped\Contribuicao\BlocoC;

use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;

/**
 * Classe com problema, negando nota de devolução (1411) por problemas de cst e aliquotas
 * Notas com modalidade de frete 3 e 4 não estão sendo validadas, 
 * Notas de modelo 65 com modalidade de frete diferente de 9 também não estão sendo validadas.
 * 
 * REGISTRO C100: DOCUMENTO - NOTA FISCAL (CÓDIGO 01), NOTA FISCAL AVULSA
 * (CÓDIGO 1B), NOTA FISCAL DE PRODUTOR (CÓDIGO 04) e NF-e (CÓDIGO 55)
 */
class RegC100 extends AbstractReg
{
    /**
     * Indicador do tipo de operação:
     * 0 - Serviço Contratado pelo Estabelecimento;
     * 1 - Serviço Prestado pelo Estabelecimento.
     */
    protected $IND_OPER;
    /**
     * Indicador do emitente do documento fiscal:
     * 0 - Emissão própria;
     * 1 - Emissão de Terceiros
     */
    protected $IND_EMIT;
    /**
     * Código do participante (campo 02 do Registro 0150):
     * - do emitente do documento, no caso de emissão de terceiros;
     * - do adquirente, no caso de serviços prestados.
     */
    protected $COD_PART;
    /**
     * Código do modelo do documento fiscal, conforme a Tabela 4.1.1
     */
    protected $COD_MOD;
    /**
     * Código da situação do documento fiscal:
     * 00 – Documento regular
     * 02 – Documento cancelado
     */
    protected $COD_SIT;
    /**
     * Série do documento fiscal
     */
    protected $SER;
    /**
     * Número do documento fiscal ou documento internacional equivalente
     */
    protected $NUM_DOC;
    /**
     * Chave da Nota Fiscal Eletrônica
     */
    protected $CHV_NFE;
    /**
     * Data da emissão do documento fiscal
     */
    protected $DT_DOC;
    /**
     * Data da entrada ou da saída
     */
    protected $DT_E_S;
    /**
     * Valor total do documento
     */
    protected $VL_DOC;
    /**
     * Indicador do tipo de pagamento:
     * 0- À vista;
     * 1- A prazo;
     * 9- Sem pagamento
     */
    protected $IND_PGTO;
    /**
     * Valor total do desconto
     */
    protected $VL_DESC;
    /**
     * Abatimento não tributado e não comercial Ex. desconto ICMS nas remessas para ZFM.
     */
    protected $VL_ABAT_NT;
    /**
     * Valor total das mercadorias e serviços
     */
    protected $VL_MERC;
    /**
     * Indicador do tipo do frete:
     * 0- Por conta de terceiros;
     * 1- Por conta do emitente;
     * 2- Por conta do destinatário;
     * 9- Sem cobrança de frete.
     */
    protected $IND_FRT;
    /**
     * Valor do frete indicado no documento fiscal
     */
    protected $VL_FRT;
    /**
     * Valor do seguro indicado no documento fiscal
     */
    protected $VL_SEG;
    /**
     * Valor de outras despesas acessórias
     */
    protected $VL_OUT_DA;
    // Valor da base de cálculo do ICMS
    protected $VL_BC_ICMS;
    /**
     * Valor do ICMS
     */
    protected $VL_ICMS;
    /**
     * Valor da base de cálculo do ICMS substituição tributária
     */
    protected $VL_BC_ICMS_ST;
    /**
     * Valor do ICMS retido por substituição tributária
     */
    protected $VL_ICMS_ST;
    /**
     * Valor total do IPI
     */
    protected $VL_IPI;
    /**
     * Valor total do PIS
     */
    protected $VL_PIS;
    /**
     * Valor total da COFINS
     */
    protected $VL_COFINS;
    /**
     * Valor total do PIS retido por substituição tributária
     */
    protected $VL_PIS_ST;
    /**
     * Valor total da COFINS retido por substituição tributária
     */
    protected $VL_COFINS_ST;
    /**
     * Validações de exeções para notas canceladas, denegadas e inutilizadas
     */
    protected $exceptions = [
        [
            'field'   => 'COD_SIT',
            'value'   => ["02", "03", "04"],
            'remains' => ['IND_OPER', 'IND_EMIT', 'COD_MOD', 'COD_SIT', 'SER', 'NUM_DOC', 'CHV_NFE']
        ],
        [
            'field'   => 'COD_SIT',
            'value'   => ["05"],
            'remains' => ['IND_OPER', 'IND_EMIT', 'COD_MOD', 'COD_SIT', 'SER', 'NUM_DOC']
        ]
    ];

    protected function setAttributes($data = [])
    {
        $notas = $data['nf']->filter(function ($nf) {
            $authorized = NfUtil::isAuthorized($nf->nfsituacao_id) && Util::hasIn($nf->nfmodelo, ['01','1B','04','55','65']);
            return $authorized;
        })->unique('nf_id');
        
        $arrSit = ["02", "03", "04"];

        if($notas->count() === 0)
            $this->none = true;

        foreach ($notas as $nf) {
            $this->CHV_NFE = $nf->chaveacesso;
            $this->COD_MOD = $nf->nfmodelo;
            $this->insertByModel($nf);
            if ($this->getCodSit($nf) !== false) {
                $this->COD_SIT = $this->getCodSit($nf);
            } else {
                $this->clearToMultiple();
                continue;
            }
            $this->DT_DOC = Util::dateFormat($nf->datahoraemissao);
            $this->DT_E_S = Util::dateFormat($nf->datahoraentradasaida);
            $this->IND_EMIT = $nf->tiponf == 'emitida' ? 0 : 1;
            $this->IND_OPER = $nf->tipo;
            $this->IND_PGTO = Util::getIndPgto($nf->tipopagamento);
            $this->NUM_DOC = $nf->nfnumero;
            $this->SER = $nf->nfserie;
            $this->VL_ABAT_NT = '0,00';
            $this->VL_BC_ICMS = Util::numberFormat($nf->vbcnf);
            $this->VL_DESC = Util::numberFormat($nf->vdescnf);
            $this->VL_DOC = Util::numberFormat($nf->vnf); #vnf agora é o valor liquido da nota fiscal.
            $this->VL_ICMS = Util::numberFormat($nf->vicmsnf);
            $this->VL_MERC = Util::numberFormat($nf->vprodnf);
            $this->VL_OUT_DA = Util::numberFormat($nf->voutro);
            $this->VL_SEG = Util::numberFormat($nf->vseg);

            $items = $data['nf']->where('nf_id', $nf->nf_id)->unique('item_id');

            if (! in_array($this->COD_SIT, $arrSit)) $this->createChild($items, $nf);

            $this->addReg($this);
        }
        return $this;
    }

    protected function getValidationArray()
    {
        $arrCodSit = ['00', '01', '02', '03', '04', '05', '06', '07', '08'];
        return [
            'IND_OPER'      => static::getBaseVR("Indicador do tipo de operação", 1, true, "O", ['0', '1']),
            'IND_EMIT'      => static::getBaseVR("Indicador do emitente do documento fiscal", 1, true, "O", ['0','1'], $this->calls('IND_EMIT')),
            'COD_PART'      => static::getBaseVR("Código do participante", 60, false, "OC", $this->calls('COD_PART')),
            'COD_MOD'       => static::getBaseVR("Código do modelo do documento fiscal", 2, true),
            'COD_SIT'       => static::getBaseVR("Código da situação do documento fiscal", 2, true, "O", $arrCodSit),
            'SER'           => static::getBaseVR("Série do documento fiscal", 3, false, "N"),
            'NUM_DOC'       => static::getBaseVR("Número do documento fiscal", 9),
            'CHV_NFE'       => static::getBaseVR("Chave da Nota Fiscal Eletrônica ou da NFC-e", 44, true, "N"),
            'DT_DOC'        => static::getBaseVR("Data da emissão do documento fiscal", 8, true),
            'DT_E_S'        => static::getBaseVR("Data da entrada ou da saída", 8, true, "N"),
            'VL_DOC'        => static::getBaseVR("Valor total do documento fiscal", false),
            'IND_PGTO'      => static::getBaseVR("Indicador do tipo de pagamento", 1, true, "O", ['0', '1', '2']),
            'VL_DESC'       => static::getBaseVR("Valor total do desconto", false, false, "N"),
            'VL_ABAT_NT'    => static::getBaseVR("Abatimento não tributado e não comercial", false, false, "N"),
            'VL_MERC'       => static::getBaseVR("Valor total das mercadorias e serviços", false, false, "N"),
            'IND_FRT'       => static::getBaseVR("Indicador do tipo do frete", 1, true, "O", ['0', '1', '2', '3', '4', '9']),
            'VL_FRT'        => static::getBaseVR("Valor do frete indicado no documento fiscal", false, false, "N"),
            'VL_SEG'        => static::getBaseVR("Valor do seguro indicado no documento fiscal", false, false, "N"),
            'VL_OUT_DA'     => static::getBaseVR("Valor de outras despesas acessórias", false, false, "N"),
            'VL_BC_ICMS'    => static::getBaseVR("Valor da base de cálculo do ICMS", false, false, "N"),
            'VL_ICMS'       => static::getBaseVR("Valor do ICMS", false, false, "N"),
            'VL_BC_ICMS_ST' => static::getBaseVR("Valor da base de cálculo do ICMS substituição tributária", false, false, "N"),
            'VL_ICMS_ST'    => static::getBaseVR("Valor do ICMS retido por substituição tributária", false, false, "N"),
            'VL_IPI'        => static::getBaseVR("Valor total do IPI", false, false, "N"),
            'VL_PIS'        => static::getBaseVR("Valor total do PIS", false, false, "N"),
            'VL_COFINS'     => static::getBaseVR("Valor total da COFINS", false, false, "N"),
            'VL_PIS_ST'     => static::getBaseVR("Valor total do PIS retido por substituição tributária", false, false, "N"),
            'VL_COFINS_ST'  => static::getBaseVR("Valor total da COFINS retido por substituição tributária", false, false, "N"),
        ];
    }

    private function calls($index)
    {
        $col = collect([
            'IND_EMIT'  => function ($value) {
                if ($value == '1' && $this->IND_OPER != '0') {
                    $this->addError("O Indicador do tipo de Operação deve ser 0 - Entrada quando o Indicador do Emitente for 1 - Teceiros.");
                }
            },
            'CHV_NFE'   => function ($value) {
                if ($this->IND_EMIT == '0' && ($this->COD_MOD == '55' || $this->COD_MOD == '65') && !$value) {
                    $this->addError("A chave de acesso é obrigatória para notas 55 e 65 de emissão própria.");
                }
            },
            'COD_PART'  => function ($value) {
                if ($this->COD_MOD !== "65" && !$value) {
                    $this->addError("Código do participante e obrigatório");
                }
            }
        ]);

        return $col->get($index);
    }

    private function createChild($items, $nf)
    {
        $i = 1;
        if($nf->nfmodelo === "65") {
            $i = $this->insertC175($items, $i);
        } else {
            foreach ($items->sortBy('item_id') as $item){
                $item->NUM_ITEM = $i;  
                $this->addChildren('RegC170', $item);
                $i++;
            }
        }
    }

    private function insertC175 ($items, $i)
    {
        $uniqueItems = $items->unique(function ($i) {
            $val = $i->cfop . $i->cstpis . $i->cstcofins . $i->ppis . $i->pcofins;
            return $val;
        })->sortBy('item_id')
        ->uniqueStrict('cfop');

        foreach ($uniqueItems as $item) {
            $it = (object) [];
            $itemsEquals = $items->unique('item_id')->filter(function($filterItem) use ($item) {
                return $item->cst == $filterItem->cst &&
                        $item->cfop == $filterItem->cfop &&
                        $item->picms == $filterItem->picms &&
                        $item->nf_id == $filterItem->nf_id;
            });
            $it->cprod = $item->cprod;
            $it->planoconta_id = $item->planoconta_id;
            $it->cfop = $item->cfop;
            $it->cstpis = $item->cstpis;
            $it->cstcofins = $item->cstcofins;
            $it->ppis = $item->ppis;
            $it->pcofins = $item->pcofins;
            $it->nf_id = $item->nf_id;
            $it->vprod = Util::numberFormat($itemsEquals->sum('vprod'));
            $it->vdesc = Util::numberFormat($itemsEquals->sum('vdesc'));
            $it->vpis = Util::numberFormat($itemsEquals->sum('vpis'));
            $it->vbcpis = Util::numberFormat($itemsEquals->sum('vbcpis'));
            $it->vcofins = Util::numberFormat($itemsEquals->sum('vcofins'));
            $it->vbccofins = Util::numberFormat($itemsEquals->sum('vbccofins'));
            $it->NUM_ITEM = $i;
            $i++;
            $this->addChildren('RegC175', $it);
        }
        return $i;
    }

    private function insertByModel($nf)
    {
        $arr = ['3', '4'];
        if ($this->COD_MOD !== "65") {
            $this->COD_PART = $nf->cliente_id;
            $this->VL_BC_ICMS_ST = Util::numberFormat($nf->vbcst);
            $this->VL_ICMS_ST = Util::numberFormat($nf->vstnf);
            $this->VL_IPI = Util::numberFormat($nf->vipinf);
            $this->VL_PIS = Util::numberFormat($nf->vpisnf);
            $this->VL_COFINS = Util::numberFormat($nf->vcofinsnf);
            $this->VL_PIS_ST = 0;
            $this->VL_COFINS_ST = 0;
        } else {
            $this->COD_PART = "";
            $this->VL_BC_ICMS_ST = "";
            $this->VL_ICMS_ST = "";
            $this->VL_IPI = "";
            $this->VL_PIS = Util::numberFormat($nf->vpisnf);
            $this->VL_COFINS = Util::numberFormat($nf->vcofinsnf);
            $this->VL_PIS_ST = "";
            $this->VL_COFINS_ST = "";
        }
        if ($this->COD_MOD === "65" || Util::hasIn($nf->fretemodalidade, $arr)) {
            $this->IND_FRT = "9";
            $this->VL_FRT = "";
        } else {
            $this->IND_FRT = $nf->fretemodalidade;
            $this->VL_FRT = Util::numberFormat($nf->vfretenf);
        }
    }
}
