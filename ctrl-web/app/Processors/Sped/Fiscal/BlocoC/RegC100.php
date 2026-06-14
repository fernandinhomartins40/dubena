<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use DB;

class RegC100 extends AbstractReg
{
    /**
     * Indicador do tipo de operação
     * @var mixed
     */
    protected $IND_OPER;

    /**
     * Indicador do emitente do documento fiscal
     * @var mixed
     */
    protected $IND_EMIT;

    /**
     * Código do participante (campo 02 do Registro 0150)
     * @var mixed
     */
    protected $COD_PART;

    /**
     * Código do modelo do documento fiscal, conforme a Tabela 4.1.1
     * @var mixed
     */
    protected $COD_MOD;

    /**
     * Código da situação do documento fiscal
     * @var mixed
     */
    protected $COD_SIT;

    /**
     * Série do documento fiscal
     * @var mixed
     */
    protected $SER;

    /**
     * Número do documento fiscal ou documento internacional equivalente
     * @var mixed
     */
    protected $NUM_DOC;

    /**
     * Chave da Nota Fiscal Eletrônica
     * @var mixed
     */
    protected $CHV_NFE;

    /**
     * Data da emissão do documento fiscal
     * @var mixed
     */
    protected $DT_DOC;

    /**
     * Data da entrada ou da saída
     * @var mixed
     */
    protected $DT_E_S;

    /**
     * Valor total do documento
     * @var mixed
     */
    protected $VL_DOC;

    /**
     * Indicador do tipo de pagamento:
     * @var mixed
     */
    protected $IND_PGTO;

    /**
     * Valor total do desconto
     * @var mixed
     */
    protected $VL_DESC;

    /**
     * Abatimento não tributado e não comercial Ex. desconto ICMS nas remessas para ZFM.
     * @var mixed
     */
    protected $VL_ABAT_NT;

    /**
     * Valor total das mercadorias e serviços
     * @var mixed
     */
    protected $VL_MERC;

    /**
     * Indicador do tipo do frete:
     * @var mixed
     */
    protected $IND_FRT;

    /**
     * Valor do frete indicado no documento fiscal
     * @var mixed
     */
    protected $VL_FRT;

    /**
     * Valor do seguro indicado no documento fiscal
     * @var mixed
     */
    protected $VL_SEG;

    /**
     * Valor de outras despesas acessórias
     * @var mixed
     */
    protected $VL_OUT_DA;

    /**
     * Valor da base de cálculo do ICMS
     * @var mixed
     */
    protected $VL_BC_ICMS;

    /**
     * Valor do ICMS
     * @var mixed
     */
    protected $VL_ICMS;

    /**
     * Valor da base de cálculo do ICMS substituição tributária
     * @var mixed
     */
    protected $VL_BC_ICMS_ST;

    /**
     * Valor do ICMS retido por substituição tributária
     * @var mixed
     */
    protected $VL_ICMS_ST;

    /**
     * Valor total do IPI
     * @var mixed
     */
    protected $VL_IPI;

    /**
     * Valor total do PIS
     * @var mixed
     */
    protected $VL_PIS;

    /**
     * Valor total da COFINS
     * @var mixed
     */
    protected $VL_COFINS;

    /**
     * Valor total do PIS retido por substituição tributária
     * @var mixed
     */
    protected $VL_PIS_ST;

    /**
     * Valor total da COFINS retido por substituição tributária
     * @var mixed
     */
    protected $VL_COFINS_ST;

    /**
     * Modelos permitidos a gerar este registro
     * @var array
     */
    protected $models = ['01', 'A1', '1B', '04', '55', '65'];
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
    private $parcelas;

    /**
     * retorna o array com as validações genéricas dos campos
     * @return array
     */
    protected function getValidationArray()
    {
        $aCodSit = ['00', '01', '02', '03', '04', '05', '06', '07', '08'];

        return [
            'IND_OPER' => static::getBaseVR("Indicador do tipo de operação", 1, true, "O", [0, 1]),
            'IND_EMIT' => static::getBaseVR("Indicador do emitente do documento fiscal", 1, true, "O", [0, 1]),
            'COD_PART' => static::getBaseVR("Código do participante", 60, false, "OC", function ($value) {
                        if ($this->COD_MOD !== "65" && !$value) {
                            $this->addError("Código do participante e obrigatório");
                        }
                    }),
            'COD_MOD'       => static::getBaseVR("Código do modelo do documento fiscal", 2, true, "O", $this->models),
            'COD_SIT'       => static::getBaseVR("Código da situação do documento fiscal", 2, true, "O", $aCodSit),
            'SER'           => static::getBaseVR("Série do documento fiscal", 3, false, "OC"),
            'NUM_DOC'       => static::getBaseVR("Número do documento fiscal ", 9),
            'CHV_NFE'       => static::getBaseVR("Chave da Nota Fiscal Eletrônica", 44, true, "OC"),
            'DT_DOC'        => static::getBaseVR("Data da emissão do documento fiscal", 8, true),
            'DT_E_S'        => static::getBaseVR("Data da entrada ou da saída", 8, true, "OC"),
            'VL_DOC'        => static::getBaseVR("Valor total do documento fiscal", false),
            'IND_PGTO'      => static::getBaseVR('Indicador do tipo de pagamento', 1, true, "O", [0, 1, 2, 9]),
            'VL_DESC'       => static::getBaseVR("Valor total do desconto", false, false, "OC"),
            'VL_ABAT_NT'    => static::getBaseVR("Abatimento não tributado e não comercia", false, false, "OC"),
            'VL_MERC'       => static::getBaseVR("Valor total das mercadorias e serviços", false, false, "OC"),
            'IND_FRT'       => static::getBaseVR("Indicador do tipo do frete", 1, true, "OC", [0, 1, 2, 3, 4, 9]),
            'VL_FRT'        => static::getBaseVR("Valor do frete indicado no documento fiscal", false, false, "OC"),
            'VL_SEG'        => static::getBaseVR("Valor do seguro", false, false, "OC"),
            'VL_OUT_DA'     => static::getBaseVR("Valor de outras despesas acessórias", false, false, "OC"),
            'VL_BC_ICMS'    => static::getBaseVR("Valor da Base de Cálculo do ICMS", false, false, "OC"),
            'VL_ICMS'       => static::getBaseVR("Valor do ICMS", false, false, "OC"),
            'VL_BC_ICMS_ST' => static::getBaseVR("Valor da Base de Cálculo do ICMS retido por substituição tributária", false, false, "OC"),
            'VL_ICMS_ST'    => static::getBaseVR("Valor do ICMS retido por substituição tributária", false, false, "OC"),
            'VL_IPI'        => static::getBaseVR("Valor total do IPI", false, false, "OC"),
            'VL_PIS'        => static::getBaseVR("Valor total do PIS", false, false, "OC"),
            'VL_COFINS'     => static::getBaseVR("Valor total do COFINS", false, false, "OC"),
            'VL_PIS_ST'     => static::getBaseVR("Valor total do PIS retido por substituição tributária", false, false, "OC"),
            'VL_COFINS_ST'  => static::getBaseVR("Valor total do COFINS retido por substituição tributária", false, false, "OC")
        ];
    }

    protected function setAttributes($data = [])
    {
        $nfs = $data['nf']->filter(function ($nf) {
                    $allowedE = $nf->tiponf == 'emitida';
                    $allowedR = $nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, $this->models);
                    return $allowedE || $allowedR;
                })->unique('nf_id');

        if ($nfs->count() === 0) {
            $this->none = true;
        }

        $parcelasEmit = DB::table('nfemitidaparcelas as p')
                        ->selectRaw("p.numeroparcela, p.valororiginal as valor, 
                            p.datavencimento, p.id, p.numeroparcela, p.nfemitida_id as nf_id")
                        ->whereIn('p.nfemitida_id', $nfs->where('tiponf', 'emitida')->pluck('nf_id'))->get();
        $parcelasRec = DB::table('nfrecebidaparcelas as p')
                        ->selectRaw("p.numeroparcela, p.valororiginal as valor, 
                            p.datavencimento, p.id, p.numeroparcela, p.nfrecebida_id as nf_id")
                        ->whereIn('p.nfrecebida_id', $nfs->where('tiponf', 'recebida')->pluck('nf_id'))->get();

        $this->parcelas = $parcelasEmit->merge($parcelasRec);

        $this->add($nfs, $data);

        return $this;
    }

    private function add($nfs, $data)
    {
        foreach ($nfs as $nf) {
            $this->setGenericError("NF " . $nf->nfnumero . '');

            // se este campo tiver valor igual a “1” (um), o campo IND_OPER deve ser igual a “0” (zero).
            $this->IND_EMIT = $nf->tiponf !== 'emitida' ? '1' : '0';
            $this->IND_OPER = $this->IND_EMIT ? 0 : $nf->tipo;
            $this->COD_MOD = $nf->nfmodelo;
            $this->COD_SIT = $this->getCodSit($nf);

            if ($this->COD_SIT !== false) {
                $this->setAttrByModel($nf);

                $this->SER = $nf->nfserie;
                $this->NUM_DOC = $nf->nfnumero;
                $this->CHV_NFE = $nf->chaveacesso;
                $this->DT_DOC = Util::dateFormat($nf->datahoraemissao);
                $this->DT_E_S = Util::dateFormat($nf->datahoraentradasaida);
                $this->VL_DOC = Util::numberFormat($nf->vnf);
                $this->IND_PGTO = Util::getIndPgto($nf->tipopagamento);
                $this->VL_DESC = Util::numberFormat($nf->vdescnf);
                $this->VL_ABAT_NT = '0,00';
                $this->VL_MERC = Util::numberFormat($nf->vprodnf);
                $this->IND_FRT = $nf->fretemodalidade;
                $this->VL_FRT = Util::numberFormat($nf->vfretenf);
                $this->VL_SEG = Util::numberFormat($nf->vseg);
                $this->VL_OUT_DA = Util::numberFormat($nf->voutro);
                $this->VL_BC_ICMS = Util::numberFormat($nf->vbcnf);
                $this->VL_ICMS = Util::numberFormat($nf->vicmsnf);

                if (!in_array($this->COD_SIT, ["02", "03", "04"])) {
                    $this->createChildrens($nfs, $nf, $data);
                }

                $this->addReg($this->treatExceptions($this));
            } else {
                $this->clearToMultiple();
            }
        }
    }

    private function createChildrens($nfs, $nf, $data)
    {
        $items = $data['nf']->where('nf_id', $nf->nf_id);
        if ($this->IND_PGTO == "1" && Util::hasIn($this->COD_MOD, ["01", "A1"])) {
            $this->addRegPag($nf);
        }
        $addReg170 = ($this->IND_EMIT || $data["c170"]) && in_array($this->COD_MOD, ['01', 'A1', '1B', '04', '55']);

        $addReg190 = Util::hasIn($this->COD_MOD, $this->models);

        if ($addReg170) {
            $i = 1;
            foreach ($items as $item) {
                $item->NUM_ITEM = $i++;
                $this->addChildren('RegC170', $item);
            }
        }

        if ($addReg190) {
            $i = 1;
            $itemsU = $items->unique(function($item) {
                return $item->cst . $item->cfop . $item->picms;
            });
            foreach ($itemsU as $item) {
                $item->NUM_ITEM = $i++;
                $itemsEquals = $items->unique('item_id')->filter(function($filterItem) use ($item) {
                    return $item->cst == $filterItem->cst &&
                            $item->cfop == $filterItem->cfop &&
                            $item->picms == $filterItem->picms &&
                            $item->nf_id == $filterItem->nf_id;
                });
                $item->vprod = $itemsEquals->sum('vprod');
                $item->vbc = $itemsEquals->sum('vbc');
                $item->vicms = $itemsEquals->sum('vicms');
                $item->vbcst = $itemsEquals->sum('vbcst');
                $item->vicmsst = $itemsEquals->sum('vicmsst');
                $item->vbcst = $itemsEquals->sum('vbcst');
                $item->vipi = $itemsEquals->sum('vipi');
                $item->vicmsdeson = $itemsEquals->sum('vicmsdeson');
                $item->predbc = $itemsEquals->sum('predbc') / ($itemsEquals->count() ? $itemsEquals->count() : 1);

                if (floatval($item->predbc) > 0.00) {
                    $item->vred = Util::numberFormat($item->vicmsdeson, 2);
                } else {
                    $item->vred = 0.00;
                }
                $this->addChildren('RegC190', $item);

                $this->setValuesOfCredDeb($item, $this->COD_SIT);
            }
        }
    }

    private function setAttrByModel($nf)
    {
        if ($this->COD_MOD != "65") {
            $this->VL_BC_ICMS_ST = Util::numberFormat($nf->vbcstnf);
            $this->VL_ICMS_ST = Util::numberFormat($nf->vstnf);
            $this->VL_IPI = Util::numberFormat($nf->vipinf);
            $this->VL_PIS = Util::numberFormat($nf->vpisnf);
            $this->VL_COFINS = Util::numberFormat($nf->vcofinsnf);
            $this->VL_PIS_ST = Util::numberFormat(0);
            $this->VL_COFINS_ST = Util::numberFormat(0);
            $this->COD_PART = $nf->cliente_id;
        } else {
            $this->VL_BC_ICMS_ST = "";
            $this->VL_ICMS_ST = "";
            $this->VL_IPI = "";
            $this->VL_PIS = "";
            $this->VL_COFINS = "";
            $this->VL_PIS_ST = "";
            $this->VL_COFINS_ST = "";
            $this->COD_PART = "";
        }
    }

    private function addRegPag($nf)
    {
        $parcelas = $this->parcelas->where('nf_id', $nf->nf_id);

        $qtdeParc = count($parcelas);
        $vlTot = $parcelas->sum('valor');
        foreach ($parcelas as $par) {
            $par->qtde = $qtdeParc;
            $par->nfnumero = $nf->nfnumero;
            $par->valortotal = $vlTot;

            $this->addChildren(['RegC140', 'RegC141'], $par);
        }

        return $this;
    }

}
