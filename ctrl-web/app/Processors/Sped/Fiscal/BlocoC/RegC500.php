<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegC500 extends AbstractReg
{

    public $vlrTotICMSCredito;
    public $vlrTotICMSDebito;
    public $vlrTotICMSCreditoST;
    public $vlrTotICMSDebitoST;
    /// Indicador do tipo de operação:
    ///     0 - Serviço Contratado pelo Estabelecimento;
    ///     1 - Serviço Prestado pelo Estabelecimento.
    public $IND_OPER;
    /// Indicador do emitente do documento fiscal:
    ///     0 - Emissão própria;
    ///     1 - Emissão de Terceiros
    public $IND_EMIT;
    /// Código do participante (campo 02 do Registro 0150):
    ///     - do emitente do documento, no caso de emissão de terceiros;
    ///     - do adquirente, no caso de serviços prestados.
    public $COD_PART;
    /// Código do modelo do documento fiscal, conforme a Tabela 4.1.1
    public $COD_MOD;
    /// Código da situação do documento fiscal:
    ///     00 – Documento regular
    ///     02 – Documento cancelado
    public $COD_SIT;
    /// Série do documento fiscal
    public $SER;
    /// Sub Série do documento fiscal
    public $SUB;
    /// - Código de classe de consumo de energia
    /// elétrica ou gás:
    /// 01 - Comercial
    /// 02 - Consumo Próprio
    /// 03 - Iluminação Pública
    /// 04 - Industrial
    /// 05 - Poder Público
    /// 06 - Residencial
    /// 07 - Rural
    /// 08 -Serviço Público.
    public $COD_CONS;
    /// Número do documento fiscal ou documento internacional equivalente
    public $NUM_DOC;
    /// Data da emissão do documento fiscal
    public $DT_DOC;
    /// Data da entrada ou da saída
    public $DT_E_S;
    /// Valor total do documento
    public $VL_DOC;
    /// Valor total do desconto
    public $VL_DESC;
    /// Valor total fornecido/consumido
    public $VL_FORN;
    /// Valor total dos serviços não-tributados pelo ICMS
    public $VL_SERV_NT;
    /// Valor total cobrado em nome de terceiros
    public $VL_TERC;
    /// Valor total de despesas acessórias
    public $VL_DA;
    /// Valor da base de cálculo do ICMS
    public $VL_BC_ICMS;
    /// Valor do ICMS
    public $VL_ICMS;
    /// Valor da base de cálculo do ICMS substituição tributária
    public $VL_BC_ICMS_ST;
    /// Valor do ICMS retido por substituição tributária
    public $VL_ICMS_ST;
    /// Código da informação complementar do documento fiscal (campo 02 do Registro 0450)
    public $COD_INF;
    /// Valor total do PIS
    public $VL_PIS;
    /// Valor total da COFINS
    public $VL_COFINS;
    /// Código de tipo de Ligação
    /// 1 - Monofásico
    /// 2 - Bifásico
    /// 3 - Trifásico
    public $TP_LIGACAO;
    /// Código de grupo de tensão:
    /// 01 - A1 - Alta Tensão (230kV ou mais)
    /// 02 - A2 - Alta Tensão (88 a 138kV)
    /// 03 - A3 - Alta Tensão (69kV)
    /// 04 - A3a - Alta Tensão (30kV a 44kV)
    /// 05 - A4 - Alta Tensão (2,3kV a 25kV)
    /// 06 - AS - Alta Tensão Subterrâneo 06
    /// 07 - B1 - Residencial 07
    /// 08 - B1 - Residencial Baixa Renda 08
    /// 09 - B2 - Rural 09
    /// 10 - B2 - Cooperativa de Eletrificação Rural
    /// 11 - B2 - Serviço Público de Irrigação
    /// 12 - B3 - Demais Classes
    /// 13 - B4a - Iluminação Pública - rede de distribuição
    /// 14 - B4b - Iluminação Pública - bulbo de
    public $COD_GRUPO_TENSAO;
    protected $models = ['06', '28', '29'];
    protected $exceptions = [
        [
            'field'   => 'COD_SIT',
            'value'   => ["02", "03"],
            'remains' => ['IND_OPER', 'IND_EMIT', 'COD_MOD', 'COD_SIT', 'SER', 'NUM_DOC', 'DT_DOC']
        ]
    ];

    protected function setAttributes($data = [])
    {
        $notas = $data['nf']->filter(function ($nf) {
            return $nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, ['06', '28', '29']);
        });

        $this->none = $notas->count() === 0;

        foreach ($notas->unique('nf_id') as $nf) {

            // se este campo tiver valor igual a “1” (um), o campo IND_OPER deve ser igual a “0” (zero).
            $this->IND_EMIT = $nf->tiponf !== 'emitida' ? '1' : '0';
            $this->IND_OPER = $this->IND_EMIT ? 0 : $nf->tipo;
            $this->COD_PART = $nf->cliente_id;
            $this->COD_MOD = $nf->nfmodelo;

            $this->COD_SIT = $this->getCodSit($nf);
            if ($this->COD_SIT === false) {
                continue;
            }

            $this->SER = $nf->nfserie;
            $this->SUB = $nf->nfsubserie;
            $this->COD_CONS = "04";
            $this->NUM_DOC = $nf->nfnumero;
            $this->DT_DOC = Util::dateFormat($nf->datahoraemissao);
            $this->DT_E_S = Util::dateFormat($nf->datahoraentradasaida);
            $this->VL_DOC = Util::numberFormat($nf->vnf);
            $this->VL_DESC = Util::numberFormat($nf->vdescnf);
            $this->VL_FORN = Util::numberFormat($nf->vprodnf);
            $this->VL_SERV_NT = 0;
            $this->VL_TERC = 0;
            $this->VL_DA = 0;
            $this->VL_BC_ICMS = Util::numberFormat($nf->vbcnf);
            $this->VL_ICMS = Util::numberFormat($nf->vicmsnf);
            $this->VL_BC_ICMS_ST = Util::numberFormat($nf->vbcst);
            $this->VL_ICMS_ST = Util::numberFormat($nf->vicmsst);
            $this->COD_INF = "";
            $this->VL_PIS = Util::numberFormat($nf->vpisnf);
            $this->VL_COFINS = Util::numberFormat($nf->vcofinsnf);
            $this->TP_LIGACAO = "";
            $this->COD_GRUPO_TENSAO = "";

            $i = 1;
            $items = $notas->where('nf_id', $nf->nf_id);

            if (!in_array($this->COD_SIT, ["02", "03"])) {
                foreach ($items as $item) {
                    $item->index = $i++;
                    $this->addChildren('RegC590', $item);
                    $this->setValuesOfCredDeb($item, $this->COD_SIT);
                }
            }
            $this->addReg($this);
        }

        return $this;
    }

    protected function getValidationArray()
    {
        $aCodSit = ['00', '01', '02', '03', '04', '05', '06', '07', '08'];

        return [
            'IND_OPER'         => static::getBaseVR("Indicador do tipo de operação", 1, true, "O", [0, 1]),
            'IND_EMIT'         => static::getBaseVR("Indicador do emitente do documento fiscal", 1, true, "O", [0, 1]),
            'COD_PART'         => static::getBaseVR("Código do participante", 60),
            'COD_MOD'          => static::getBaseVR("Código do modelo do documento fiscal", 2, true, "O", $this->models),
            'COD_SIT'          => static::getBaseVR("Código da situação do documento fiscal", 2, true, "O", $aCodSit),
            'SER'              => static::getBaseVR("Série do documento fiscal", 3, false, "OC"),
            'SUB'              => static::getBaseVR("Subsérie do documento fiscal", 3, false, "OC"),
            'COD_CONS'         => static::getBaseVR("Código de classe de consumo de energia elétrica ou gás", 2, true, "OC"),
            'NUM_DOC'          => static::getBaseVR("Número do documento fiscal ", 9),
            'DT_DOC'           => static::getBaseVR("Data da emissão do documento fiscal", 8, true),
            'DT_E_S'           => static::getBaseVR("Data da entrada ou da saída", 8, true, "OC"),
            'VL_DOC'           => static::getBaseVR("Valor total do documento fiscal", false),
            'VL_DESC'          => static::getBaseVR("Valor total do desconto", false, false, "OC"),
            'VL_FORN'          => static::getBaseVR("Valor total fornecido/consumido", false, false, "OC"),
            'VL_SERV_NT'       => static::getBaseVR("Valor total dos serviços não-tributados pelo ICMS", false, false, "OC"),
            'VL_TERC'          => static::getBaseVR("Valor total cobrado em nome de terceiros", false, false, "OC"),
            'VL_DA'            => static::getBaseVR("Valor total de despesas acessórias indicadas no documento fiscal", false, false, "OC"),
            'VL_BC_ICMS'       => static::getBaseVR("Valor da Base de Cálculo do ICMS", false, false, "OC"),
            'VL_ICMS'          => static::getBaseVR("Valor do ICMS", false, false, "OC"),
            'VL_BC_ICMS_ST'    => static::getBaseVR("Valor da Base de Cálculo do ICMS retido por substituição tributária", false, false, "OC"),
            'VL_ICMS_ST'       => static::getBaseVR("Valor do ICMS retido por substituição tributária", false, false, "OC"),
            'COD_INF'          => static::getBaseVR("Código da informação complementar do documento fiscal", 6, false, "OC"),
            'VL_PIS'           => static::getBaseVR("Valor total do PIS", false, false, "OC"),
            'VL_COFINS'        => static::getBaseVR("Valor total do COFINS", false, false, "OC"),
            'TP_LIGACAO'       => static::getBaseVR("Código de tipo de Ligação", 1, true, "OC"),
            'COD_GRUPO_TENSAO' => static::getBaseVR("Código de grupo de tensão", 2, true, "OC"),
        ];
    }

}
