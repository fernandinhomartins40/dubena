<?php

namespace App\Processors\Sped\Contribuicao\BlocoM;

use App\Services\CarbonCustom as Carbon;
use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

/**
 * REGISTRO M505: DETALHAMENTO DA BASE DE CALCULO DO CRÉDITO APURADO NO PERÍODO – COFINS
 */
class RegM505 extends AbstractReg
{
    /**
     * Código da Base de Cálculo do Crédito apurado no período, conforme a Tabela 4.3.7
     */
    protected $NAT_BC_CRED;
    /**
     * Código da Situação Tributária referente ao crédito de
     * Cofins (Tabela 4.3.3) vinculado ao tipo de crédito
     * escriturado em M100.
     */
    protected $CST_COFINS;
    /**
     * Valor Total da Base de Cálculo escriturada nos documentos
     * e operações (Blocos “A”, “C”, “D” e “F”), referente ao CST_COFINS informado no Campo 03.
     */
    protected $VL_BC_COFINS_TOT;
    /**
     * Parcela do Valor Total da Base de Cálculo informada no
     * Campo 04, vinculada a receitas com incidência cumulativa.
     * Campo de preenchimento específico para a pessoa
     * jurídica sujeita ao regime cumulativo e não-cumulativo da
     * contribuição (COD_INC_TRIB = 3 do Registro 0110)
     */
    protected $VL_BC_COFINS_CUM;
    /**
     * Valor Total da Base de Cálculo do Crédito, vinculada a
     * receitas com incidência não-cumulativa (Campo 04 – Campo 05).
     */
    protected $VL_BC_COFINS_NC;
    /**
     * Valor da Base de Cálculo do Crédito, vinculada ao tipo de
     * Crédito escriturado em M100.
     * - Para os CST_COFINS = “50”, “51”, “52”, “60”, “61” e “62”:
     * Informar o valor do Campo 06 (VL_BC_COFINS_NC);
     * - Para os CST_COFINS = “53”, “54”, “55”, “56”, “63”, “64”
     * “65” e “66” (Crédito sobre operações vinculadas a mais
     * de um tipo de receita): Informar a parcela do valor do
     * Campo 06 (VL_BC_COFINS_NC) vinculada especificamente
     * ao tipo de crédito escriturado em M100.
     * O valor deste campo será transportado para o Campo 04
     * (VL_BC_COFINS) do registro M100
     */
    protected $VL_BC_COFINS;
    /**
     * Quantidade Total da Base de Cálculo do Crédito apurado
     * em Unidade de Medida de Produto, escriturada nos
     * documentos e operações (Blocos “A”, “C”, “D” e “F”),
     * referente ao CST_COFINS informado no Campo 03
     */
    protected $QUANT_BC_COFINS_TOT;
    /**
     * Parcela da base de cálculo do crédito em quantidade
     * (campo 08) vinculada ao tipo de crédito escriturado em M100.
     * - Para os CST_COFINS = “50”, “51” e “52”: Informar o valor
     * do Campo 08 (QUANT_BC_COFINS);
     * - Para os CST_COFINS = “53”, “54”, “55” e “56” (crédito
     * vinculado a mais de um tipo de receita): Informar a
     * parcela do valor do Campo 08 (QUANT_BC_COFINS)
     * vinculada ao tipo de crédito escriturado em M100.
     * O valor deste campo será transportado para o Campo 06
     * (QUANT_BC_COFINS) do registro M100.
     */
    protected $QUANT_BC_COFINS;
    /**
     * Descrição do crédito
     */
    protected $DESC_CRED;

    protected function setAttributes($data = [])
    {
        $this->NAT_BC_CRED = $data->piscofinstipobccredito;
        $this->CST_COFINS = Util::fillStrWith($data->cstcofins, 2, "0");
        $this->VL_BC_COFINS_TOT = Util::numberFormat($data->vbccofins);
        $this->VL_BC_COFINS_CUM = Util::numberFormat(0);
        $this->VL_BC_COFINS_NC = Util::numberFormat($data->vbccofins);
        $this->VL_BC_COFINS = Util::numberFormat($data->vbccofins);
        $this->QUANT_BC_COFINS_TOT = "";
        $this->QUANT_BC_COFINS = "";
        $this->DESC_CRED = "";

        return $this;
    }

     protected function getValidationArray()
     {
         return [
            'NAT_BC_CRED'           => static::getBaseVR("Código da Base de Cálculo do Crédito apurado no período", 2, true),
            'CST_COFINS'            => static::getBaseVR("Código da Situação Tributária referente ao crédito de COFINS", 2, true),
            'VL_BC_COFINS_TOT'      => static::getBaseVR("Valor Total da Base de Cálculo escriturada nos documentos e operações", false, false, "N"),
            'VL_BC_COFINS_CUM'      => static::getBaseVR("Parcela do Valor Total da Base de Cálculo", false, false, "N"),
            'VL_BC_COFINS_NC'       => static::getBaseVR("Valor Total da Base de Cálculo do Crédito", false, false, "N"),
            'VL_BC_COFINS'          => static::getBaseVR("Valor da Base de Cálculo do Crédito", false, false, "N"),
            'QUANT_BC_COFINS_TOT'   => static::getBaseVR("Quantidade Total da Base de Cálculo do Crédito apurado em Unidade de Medida de Produto", false, false, "N"),
            'QUANT_BC_COFINS'       => static::getBaseVR("Parcela da base de cálculo do crédito em quantidade", false, false, "N"),
            'DESC_CRED'             => static::getBaseVR("Descrição do crédito", 60, false, "N")
         ];
     }
}