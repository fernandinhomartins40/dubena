<?php

namespace App\Processors\Sped\Fiscal\BlocoD;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use Session;

class RegD100 extends AbstractReg
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
    /// Chave da Nota Fiscal Eletrônica
    public $CHV_CTE;
    /// Data da emissão do documento fiscal
    public $DT_DOC;
    /// Data da entrada ou da saída
    public $DT_A_P;
    /// Tipo de Conhecimento de Transporte Eletrônico
    public $TP_CTE;
    /// Chave do CT-e de referência
    public $CHV_CTE_REF;
    /// Valor total do documento
    public $VL_DOC;
    /// Valor total do desconto
    public $VL_DESC;
    /// Indicador do tipo do frete:
    ///     0- Por conta de terceiros;
    ///     1- Por conta do emitente;
    ///     2- Por conta do destinatário;
    ///     9- Sem cobrança de frete.
    public $IND_FRT;
    /// Valor total da prestação de serviço
    public $VL_SERV;
    /// Valor da base de cálculo do ICMS
    public $VL_BC_ICMS;
    /// Valor do ICMS
    public $VL_ICMS;
    /// Valor não-tributado
    public $VL_NT;
    /// Código da informação complementar do documento fiscal (campo 02 do Registro 0450)
    public $COD_INF;
    /// Código da conta analítica contábil debitada/creditada
    public $COD_CTA;
    public $COD_MUN_ORIG;
    public $COD_MUN_DEST;
    protected $models = ['07', '08', '8B', '09', '10', '11', '26', '27', '57', '67', '63'];
    protected $exceptions = [
        [
            'field'   => 'COD_SIT',
            'value'   => ["02", "03", "04"],
            'remains' => ['IND_OPER', 'IND_EMIT', 'COD_MOD', 'COD_SIT', 'SER', 'NUM_DOC', 'CHV_CTE']
        ],
        [
            'field'   => 'COD_SIT',
            'value'   => ["05"],
            'remains' => ['IND_OPER', 'IND_EMIT', 'COD_MOD', 'COD_SIT', 'SER', 'NUM_DOC']
        ],
        [
            'field'   => 'COD_SIT',
            'value'   => ["06", "07"],
            'remains' => ['IND_OPER', 'IND_EMIT', 'COD_PART', 'COD_MOD', 'COD_SIT', 'SER', 'SUB', 'NUM_DOC', 'DT_DOC']
        ]
    ];

    protected function setAttributes($data = [])
    {
        $empresaconfig = Session::get('empresa_config');

        $notas = $data['nf']->filter(function ($nf) {
                    return ($nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, $this->models)); //($nf->tiponf == 'emitida' && $nf->nfsituacao_id != 102) || 
                })->unique('nf_id');

        if ($notas->count() === 0) {
            $this->none = true;
        }

        foreach ($notas as $nf) {
            $this->COD_MOD = $nf->nfmodelo;
            $this->CHV_CTE = $nf->chaveacesso;
            $this->TP_CTE = "";
            $this->CHV_CTE_REF = "";
            $this->COD_PART = $nf->cliente_id;

            $this->COD_SIT = $this->getCodSit($nf);
            if ($this->COD_SIT === false) {
                $this->clearToMultiple();
                continue;
            }

            $this->DT_DOC = Util::dateFormat($nf->datahoraemissao);
            $this->DT_A_P = Util::dateFormat($nf->datahoraentradasaida);
            // se este campo tiver valor igual a “1” (um), o campo IND_OPER deve ser igual a “0” (zero).
            $this->IND_EMIT = $nf->tiponf !== 'emitida' ? '1' : '0';
            $this->IND_OPER = $this->IND_EMIT ? 0 : $nf->tipo;
            $this->NUM_DOC = $nf->nfnumero;
            $this->SER = $nf->nfserie;
            $this->SUB = $nf->nfsubserie;
            $this->VL_SERV = Util::numberFormat($nf->vprodnf);
            $this->VL_BC_ICMS = Util::numberFormat($nf->vbcnf);
            $this->VL_NT = 0;
            $this->VL_DESC = Util::numberFormat($nf->vdescnf);
            $this->VL_DOC = Util::numberFormat($nf->vnf);
            $this->VL_ICMS = Util::numberFormat($nf->vicmsnf);
            $this->IND_FRT = $nf->fretemodalidade;
            $this->COD_INF = "";
            $this->COD_CTA = "";
            $this->COD_MUN_ORIG = $nf->emitcidadecodigoibge;
            $this->COD_MUN_DEST = $nf->destcidadecodigoibge;

            $i = 1;
            $items = $data['nf']->where('nf_id', $nf->nf_id);
            foreach ($items as $item) {
                $item->index = $i++;
                $this->addChildren('RegD190', $item);
                $this->setValuesOfCredDeb($item, $this->COD_SIT);            }

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
            'SER'          => static::getBaseVR("Série do documento fiscal", 4, false, "OC"),
            'SUB'          => static::getBaseVR("Subsérie do documento fiscal", 3, false, "OC"),
            'NUM_DOC'      => static::getBaseVR("Número do documento fiscal ", 9),
            'CHV_CTE'      => static::getBaseVR("Chave da Nota Fiscal Eletrônica", 44, true, "OC"),
            'DT_DOC'       => static::getBaseVR("Data da emissão do documento fiscal", 8, true),
            'DT_A_P'       => static::getBaseVR("Data da entrada ou da saída", 8, true, "OC"),
            'TP_CTE'       => static::getBaseVR("Tipo de Conhecimento de Transporte Eletrônico", 1, true),
            'CHV_CTE_REF'  => static::getBaseVR("Chave do CT-e de referência cujos valores", 44, "OC"),
            'VL_DOC'       => static::getBaseVR("Valor total do documento fiscal", false),
            'VL_DESC'      => static::getBaseVR("Valor total do desconto", false, false, "OC"),
            'IND_FRT'      => static::getBaseVR("Indicador do tipo do frete", 1, true, "O", [0, 1, 2, 3, 4, 9]),
            'VL_SERV'      => static::getBaseVR("Valor total da prestação de serviço", false, false, "O"),
            'VL_BC_ICMS'   => static::getBaseVR("Valor da Base de Cálculo do ICMS", false, false, "OC"),
            'VL_ICMS'      => static::getBaseVR("Valor do ICMS", false, false, "OC"),
            'VL_NT'        => static::getBaseVR("Valor não-tributado", false, false, "OC"),
            'COD_INF'      => static::getBaseVR("Código da informação complementar do documento fiscal", 6, false, "OC"),
            'COD_CTA'      => static::getBaseVR("Código da conta analítica contábil debitada/creditada", false, false, "OC"),
            'COD_MUN_ORIG' => static::getBaseVR("Código do município de origem do serviço", 7, true, "OC"),
            'COD_MUN_DEST' => static::getBaseVR("Código do município de destino do serviço", 7, true, "OC")
        ];
    }

}
