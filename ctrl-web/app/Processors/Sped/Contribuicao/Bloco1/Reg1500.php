<?php

namespace App\Processors\Sped\Contribuicao\Bloco1;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;
use Session;

/**
 * REGISTRO 1500: CONTROLE DE CRÉDITOS FISCAIS – COFINS
 */
class Reg1500 extends AbstractReg
{
    /**
     * Período de Apuração do Crédito (MM/AAAA)
     */
    protected $PER_APU_CRED;
    /**
     * Indicador da origem do crédito:
     * 01 – Crédito decorrente de operações próprias;
     * 02 – Crédito transferido por pessoa jurídica sucedida.
     */
    protected $ORIG_CRED;
    /**
     * CNPJ da pessoa jurídica cedente do crédito (se ORIG_CRED = 02).
     */
    protected $CNPJ_SUC;
    /**
     * Código do Tipo do Crédito, conforme Tabela 4.3.6
     */
    protected $COD_CRED;
    /**
     * Valor total do crédito apurado na Escrituração Fiscal Digital
     * (Registro M100) ou em demonstrativo DACON (Fichas 06A e
     * 06B) de período anterior.
     */
    protected $VL_CRED_APU;
    /**
     * Valor de Crédito Extemporâneo Apurado (Registro 1101),
     * referente a Período Anterior, Informado no Campo 02 – PER_APU_CRED
     */
    protected $VL_CRED_EXT_APU;
    /**
     * Valor Total do Crédito Apurado (06 + 07)
     */
    protected $VL_TOT_CRED_APU;
    /**
     * Valor do Crédito utilizado mediante Desconto, em Período(s) Anterior(es).
     */
    protected $VL_CRED_DESC_PA_ANT;
    /**
     * Valor do Crédito utilizado mediante Pedido de Ressarcimento,
     * em Período(s) Anterior(es).
     */
    protected $VL_CRED_PER_PA_ANT;
    /**
     * Valor do Crédito utilizado mediante Declaração de
     * Compensação Intermediária (Crédito de Exportação), em
     * Período(s) Anterior(es).
     */
    protected $VL_CRED_DCOMP_PA_ANT;
    /**
     * Saldo do Crédito Disponível para Utilização neste Período de
     * Escrituração (08 – 09 – 10 - 11).
     */
    protected $SD_CRED_DISP_EFD;
    /**
     * Valor do Crédito descontado neste período de escrituração.
     */
    protected $VL_CRED_DESC_EFD;
    /**
     * Valor do Crédito objeto de Pedido de Ressarcimento (PER)
     * neste período de escrituração.
     */
    protected $VL_CRED_PER_EFD;
    /**
     * Valor do Crédito utilizado mediante Declaração de
     * Compensação Intermediária neste período de escrituração.
     */
    protected $VL_CRED_DCOMP_EFD;
    /**
     * Valor do crédito transferido em evento de cisão, fusão ouincorporação
     */
    protected $VL_CRED_TRANS;
    /**
     * Valor do crédito utilizado por outras formas
     */
    protected $VL_CRED_OUT;
    /**
     * Saldo de créditos a utilizar em período de apuração futuro (12 – 13 – 14 – 15 – 16 - 17).
     */
    protected $SLD_CRED_FIM;

    protected function setAttributes($data = [])
    {
        //não muda
        $this->ORIG_CRED = "01";
        $this->CNPJ_SUC = "";
        $this->VL_CRED_EXT_APU = 0;
        $this->VL_CRED_DESC_PA_ANT = 0;
        $this->VL_CRED_PER_PA_ANT = 0;
        $this->VL_CRED_DCOMP_PA_ANT = 0;
        $this->VL_CRED_DESC_EFD = 0;
        $this->VL_CRED_PER_EFD = 0;
        $this->VL_CRED_DCOMP_EFD = 0;
        $this->VL_CRED_TRANS = 0;
        $this->VL_CRED_OUT = 0;

        $arr = ['50', '51', '52', '53', '54', '55', '56', '60', '61', '62', '63', '64', '65', '66'];
        $items = $data['nf']->filter(function ($i) use ($arr) {
            $mes = Util::fillStrWith(Util::getMonth($i->datahoraemissao), 2, "0");
            $ano = Util::getYear($i->datahoraemissao);
            $i->mesano = $mes.$ano;
            $i->anomes = $ano.$mes;
            $authorized = NfUtil::isAuthorized($i->nfsituacao_id);
            $hasIn = Util::hasIn($i->cstcofins, $arr);
            return $authorized && $hasIn;
        });

        $datainicio = insertDataOracle(explode(' ', $data['datainicio'])[0]);
        $datafim = insertDataOracle(explode(' ', $data['datafim'])[0]);
        $empresa_id = Session::get('empresa_padrao')->id;

        $whereRaw = "per_apu_cred BETWEEN TO_DATE('$datainicio', 'yyyy-mm-dd') AND TO_DATE('$datafim', 'yyyy-mm-dd') "
                . "AND registro = '1500' AND empresa_id = $empresa_id";

        $selectRaw = "TO_CHAR(per_apu_cred, 'yyyymm') as anomes, TO_CHAR(per_apu_cred, 'mmyyyy') as mesano, "
                        . "cod_cred as piscofinstipocredito, SUM(vl_cred_apu) as vpis, 0 AS vbcpis";

        $groupRaw = "TO_CHAR(per_apu_cred, 'yyyymm'), TO_CHAR(per_apu_cred, 'mmyyyy'), cod_cred";

        $creditos = \DB::table('spedcontribuicoescreditos')->whereRaw($whereRaw)
                ->select(\DB::raw($selectRaw))
                ->groupBy(\DB::raw($groupRaw))
                ->get();

        $uniqueItems = $items->merge($creditos)->sortBy(function ($i) {
            return $i->anomes.$i->piscofinstipocredito;
        })->unique(function ($i) {
            return $i->anomes.$i->piscofinstipocredito;
        });

        if($uniqueItems->count() === 0)
            $this->none = true;

        foreach ($uniqueItems as $uItem) {
            //filtrando todos os itens que possuem esses campos 
            //e somando para atribuir os campos dos registros
            $itemFilter = $items->filter(function ($i) use($uItem) {
                return $i->anomes === $uItem->anomes && $i->piscofinstipocredito === $uItem->piscofinstipocredito;
            });

            $sumVcofins = $itemFilter->sum('vcofins');

            $this->COD_CRED = $uItem->piscofinstipocredito;
            $this->VL_CRED_APU = Util::numberFormat($sumVcofins);
            $this->VL_TOT_CRED_APU = Util::numberFormat($sumVcofins);
            $this->SD_CRED_DISP_EFD = Util::numberFormat($sumVcofins);
            $this->SLD_CRED_FIM = Util::numberFormat($sumVcofins);
            $this->PER_APU_CRED = $uItem->mesano;

            $this->addReg($this);
        }

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'PER_APU_CRED'          => static::getBaseVR("Período de Apuração do Crédito", 6),
            'ORIG_CRED'             => static::getBaseVR("Indicador da origem do crédito", 2, true),
            'CNPJ_SUC'              => static::getBaseVR("CNPJ da pessoa jurídica cedente do crédito", 14, true, "N"),
            'COD_CRED'              => static::getBaseVR("Código do Tipo do Crédito", 3, true),
            'VL_CRED_APU'           => static::getBaseVR("Valor Total do crédito apurado na Escrituração Fiscal Digital", false),
            'VL_CRED_EXT_APU'       => static::getBaseVR("Valor de Crédito Extemporâneo Apurado", false, false, "N"),
            'VL_TOT_CRED_APU'       => static::getBaseVR("Valor Total do Crédito Apurado", false),
            'VL_CRED_DESC_PA_ANT'   => static::getBaseVR("Valor do Crédito utilizado mediante Desconto", false),
            'VL_CRED_PER_PA_ANT'    => static::getBaseVR("Valor do Crédito utilizado mediante Pedido de Ressarcimento", false, false, "N"),
            'VL_CRED_DCOMP_PA_ANT'  => static::getBaseVR("Valor do Crédito utilizado mediante Declaração de Compensação Intermediária", false, false, "N"),
            'SD_CRED_DISP_EFD'      => static::getBaseVR("Saldo do Crédito Disponível para Utilização neste Período de Escrituração", false),
            'VL_CRED_DESC_EFD'      => static::getBaseVR("Valor do Crédito descontado neste período de escrituração", false, false, "N"),
            'VL_CRED_PER_EFD'       => static::getBaseVR("Valor do Crédito objeto de Pedido de Ressarcimento", false, false, "N"),
            'VL_CRED_DCOMP_EFD'     => static::getBaseVR("Valor do Crédito utilizado mediante Declaração de Compensação Intermediária", false, false, "N"),
            'VL_CRED_TRANS'         => static::getBaseVR("Valor do crédito transferido em evento de cisão, fusão ou incorporação", false, false, "N"),
            'VL_CRED_OUT'           => static::getBaseVR("Valor do crédito utilizado por outras formas", false, false, "N"),
            'SLD_CRED_FIM'          => static::getBaseVR("Saldo de créditos a utilizar em período de apuração futuro", false)
        ];
    }
}