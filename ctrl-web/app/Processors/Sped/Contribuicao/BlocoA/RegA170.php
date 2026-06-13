<?php
namespace App\Processors\Sped\Contribuicao\BlocoA;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO A170: COMPLEMENTO DO DOCUMENTO - ITENS DO DOCUMENTO
class RegA170 extends AbstractReg
{
    // Número seqüencial do item no documento fiscal
    protected $NUM_ITEM;
    // Código do item (campo 02 do Registro 0200)
    protected $COD_ITEM;
    // Descrição complementar do item como adotado no documento fiscal
    protected $DESCR_COMPL;
    // Valor total do item (mercadorias ou serviços)
    protected $VL_ITEM;
    // Valor do desconto do item / Exclusão
    protected $VL_DESC;
    // Código da Base de Cálculo do Crédito, conforme a Tabela indicada no item 4.3.7, caso seja informado
    // código representativo de crédito no Campo 09 (CST_PIS) ou no Campo 13 (CST_COFINS).
    protected $NAT_BC_CRED;
    // Indicador da origem do crédito:
    //     0 – Operação no Mercado Interno
    //     1 – Operação de Importação
    protected $IND_ORIG_CRED;
    // Código da Situação Tributária referente ao PIS/PASEP – Tabela 4.3.3.
    protected $CST_PIS;
    // Valor da base de cálculo do PIS/PASEP.
    protected $VL_BC_PIS;
    // Alíquota do PIS/PASEP (em percentual)
    protected $ALIQ_PIS;
    // Valor do PIS/PASEP
    protected $VL_PIS;
    // Código da Situação Tributária referente ao COFINS – Tabela 4.3.4.
    protected $CST_COFINS;
    // Valor da base de cálculo da COFINS
    protected $VL_BC_COFINS;
    // Alíquota do COFINS (em percentual)
    protected $ALIQ_COFINS;
    // Valor da COFINS
    protected $VL_COFINS;
    // Código da conta analítica contábil debitada/creditada
    protected $COD_CTA;
    // Código do centro de custos
    protected $COD_CCUS;

    protected function getValidationArray()
    {
    }

    protected function setAttributes($data = [])
    {
        $this->NUM_ITEM = "";
        $this->COD_ITEM = "";
        $this->DESCR_COMPL = "";
        $this->VL_ITEM = "";
        $this->VL_DESC = "";
        $this->NAT_BC_CRED = "";
        $this->IND_ORIG_CRED = "";
        $this->CST_PIS = "";
        $this->VL_BC_PIS = "";
        $this->ALIQ_PIS = "";
        $this->VL_PIS = "";
        $this->CST_COFINS = "";
        $this->VL_BC_COFINS = "";
        $this->ALIQ_COFINS = "";
        $this->VL_COFINS = "";
        $this->COD_CTA = "";
        $this->COD_CCUS = "";

        return $this;
    } 

    protected function validaRegistro()
    {
        if (Util::isNullOrEmpty($this->NUM_ITEM) || (int) $this->NUM_ITEM <= 0)
            $this->addError("A170 - Número seqüencial do item no documento fiscal inválido.");

        if (Util::isNullOrEmpty($this->COD_ITEM))
            $this->addError("A170 - Código do item inválido.");

        if (!Util::hasIn($this->IND_ORIG_CRED, ["0", "1"]))
            $this->addError("A170 - Indicador da origem do crédito inválido.");

        if ((($this->VL_BC_PIS * $this->ALIQ_PIS) / 100) != $this->VL_PIS)
            $this->addError("A170 - Valor do PIS Difere do Cálculo VL_BC_PIS * ALIQ_PIS.");

        if ((($this->VL_BC_COFINS * $this->ALIQ_COFINS) / 100) != $this->VL_COFINS)
            $this->addError("A170 - Valor do Cofins Difere do Cálculo VL_BC_PIS * ALIQ_PIS.");

        return $this;
    }

    protected function layout()
    {
        $this->line = parent::setLine([
            $this->numReg,
            $this->NUM_ITEM,
            $this->COD_ITEM,
            Util::replaceAccent($this->DESCR_COMPL),
            Util::numberFormat($this->VL_ITEM),
            Util::numberFormat($this->VL_DESC),
            $this->NAT_BC_CRED,
            $this->IND_ORIG_CRED,
            $this->CST_PIS,
            Util::numberFormat($this->VL_BC_PIS),
            Util::numberFormat($this->ALIQ_PIS),
            Util::numberFormat($this->VL_PIS),
            $this->CST_COFINS,
            Util::numberFormat($this->VL_BC_COFINS),
            Util::numberFormat($this->ALIQ_COFINS),
            Util::numberFormat($this->VL_COFINS),
            $this->COD_CTA,
            $this->COD_CCUS
        ]);

        return $this;
    }
}
