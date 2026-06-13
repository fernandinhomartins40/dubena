<?php

namespace App\Processors\Sped\Fiscal\BlocoD;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegD500 extends AbstractReg
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
    /// Número do documento fiscal ou documento internacional equivalente
    public $NUM_DOC;
    /// Data da emissão do documento fiscal
    public $DT_DOC;
    /// Data da entrada ou da saída
    public $DT_A_P;
    /// Valor total do documento
    public $VL_DOC;
    /// Valor total do desconto
    public $VL_DESC;
    /// Valor total da prestação de serviço
    public $VL_SERV;
    /// Valor total da prestação de serviço não tributado pelo ICMS
    public $VL_SERV_NT;
    /// Serviços cobrados em nome de terceiros
    public $VL_TERC;
    /// Outras despesas
    public $VL_DA;
    /// Valor da base de cálculo do ICMS
    public $VL_BC_ICMS;
    /// Valor do ICMS
    public $VL_ICMS;
    /// Código da informação complementar do documento fiscal (campo 02 do Registro 0450)
    public $COD_INF;
    /// Valor do PIS
    public $VL_PIS;
    /// Valor do Cofins
    public $VL_COFINS;
    /// Código da conta analítica contábil debitada/creditada
    public $COD_CTA;
    /// Código do Tipo de Assinante:
    /// 1 - Comercial/Industrial
    /// 2 - Poder Público
    /// 3 - Residencial/Pessoa física
    /// 4 - Público
    /// 5 - Semi-Público
    /// 6 - Outros
    public $TP_ASSINANTE;
    protected $models = ['21', '22'];
    protected $exceptions = [
        [
            'field'   => 'COD_SIT',
            'value'   => ["02", "03"],
            'remains' => ['IND_OPER', 'IND_EMIT', 'COD_MOD', 'COD_SIT', 'SER', 'NUM_DOC', 'DT_DOC']
        ],
        [
            'field'   => 'COD_SIT',
            'value'   => ["06", "07"],
            'remains' => ['IND_OPER', 'IND_EMIT', 'COD_PART', 'COD_MOD', 'COD_SIT', 'SER', 'NUM_DOC', 'DT_DOC']
        ]
    ];

    protected function setAttributes($data = [])
    {
        $notas = $data['nf']->filter(function ($nf) {
            return ($nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, $this->models));
        });

        if ($notas->count() === 0) {
            $this->none = true;
        }

        foreach ($notas->unique('nf_id') as $nf) {
            $this->COD_MOD = $nf->nfmodelo;
            $this->COD_PART = $nf->cliente_id;

            $this->COD_SIT = $this->getCodSit($nf);
            if ($this->COD_SIT === false) {
                continue;
            }

            $this->DT_DOC = Util::dateFormat($nf->datahoraemissao);
            $this->DT_A_P = Util::dateFormat($nf->datahoraentradasaida);
            $this->IND_OPER = $nf->tiponf !== 'emitida' ? '0' : $nf->tipo;
            // se este campo tiver valor igual a “1” (um), o campo IND_OPER deve ser igual a “0” (zero).
            $this->IND_EMIT = $nf->tiponf === 'emitida' ? '0' : '1';
            $this->NUM_DOC = $nf->nfnumero;
            $this->SER = $nf->nfserie;
            $this->SUB = $nf->nfsubserie;
            $this->VL_SERV = Util::numberFormat($nf->vprodnf);
            $this->VL_BC_ICMS = Util::numberFormat($nf->vbcnf);
            $this->VL_SERV_NT = 0;
            $this->VL_TERC = 0;
            $this->VL_DA = 0;
            $this->VL_DESC = Util::numberFormat($nf->vdescnf);
            $this->VL_DOC = Util::numberFormat($nf->vnf);
            $this->VL_ICMS = Util::numberFormat($nf->vicmsnf);
            $this->VL_PIS = Util::numberFormat($nf->vpisnf);
            $this->VL_COFINS = Util::numberFormat($nf->vcofinsnf);
            $this->COD_INF = "";
            $this->COD_CTA = "";
            $this->TP_ASSINANTE = "1";

            $i = 1;
            $items = $notas->where('nf_id', $nf->nf_id);
            foreach ($items as $item) {
                $item->index = $i++;
                $this->addChildren('RegD590', $item);
                $this->setValuesOfCredDeb($item, $this->COD_SIT);
            }
            $this->addReg($this);
        }

        return $this;
    }

    protected function getValidationArray()
    {
        $aCodSit = ['00', '01', '02', '03', '04', '05', '06', '07', '08'];

        return [
            'IND_OPER'     => static::getBaseVR("Indicador do tipo de operação", 1, true, "O", [0, 1]),
            'IND_EMIT'     => static::getBaseVR("Indicador do emitente do documento fiscal", 1, true, "O", [0, 1]),
            'COD_PART'     => static::getBaseVR("Código do participante", 60, false, "O"),
            'COD_MOD'      => static::getBaseVR("Código do modelo do documento fiscal", 2, true, "O", $this->models),
            'COD_SIT'      => static::getBaseVR("Código da situação do documento fiscal", 2, true, "O", $aCodSit),
            'SER'          => static::getBaseVR("Série do documento fiscal", 3, false, "OC"),
            'SUB'          => static::getBaseVR("Subsérie do documento fiscal", 3, false, "OC"),
            'NUM_DOC'      => static::getBaseVR("Número do documento fiscal ", 9),
            'DT_DOC'       => static::getBaseVR("Data da emissão do documento fiscal", 8, true),
            'DT_A_P'       => static::getBaseVR("Data da entrada ou da saída", 8, true, "OC"),
            'VL_DOC'       => static::getBaseVR("Valor total do documento fiscal", false),
            'VL_DESC'      => static::getBaseVR("Valor total do desconto", false, false, "OC"),
            'VL_SERV'      => static::getBaseVR("Valor total da prestação de serviço", false, false, "O"),
            'VL_SERV_NT'   => static::getBaseVR("Valor total não tributado", false, false, "O"),
            'VL_TERC'      => static::getBaseVR("Valor total da prestação de serviço", false, false, "O"),
            'VL_DA'        => static::getBaseVR("Valor de outras despesas indicadas no documento fiscal", false, false, "O"),
            'VL_BC_ICMS'   => static::getBaseVR("Valor da Base de Cálculo do ICMS", false, false, "OC"),
            'VL_ICMS'      => static::getBaseVR("Valor do ICMS", false, false, "OC"),
            'COD_INF'      => static::getBaseVR("Código da informação complementar do documento fiscal", 6, false, "OC"),
            'VL_PIS'       => static::getBaseVR("Valor do PIS", false, false, "OC"),
            'VL_COFINS'    => static::getBaseVR("Valor do COFINS", false, false, "OC"),
            'COD_CTA'      => static::getBaseVR("Código da conta analítica contábil debitada/creditada", false, false, "OC"),
            'TP_ASSINANTE' => static::getBaseVR(" Código do Tipo de Assinante", 1, true, "O", [1, 2, 3, 4, 5, 6])
        ];
    }

}
