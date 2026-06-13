<?php
namespace App\Processors\Sped\Contribuicao\BlocoA;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO A120: INFORMAÇÃO COMPLEMENTAR - OPERAÇÕES DE IMPORTAÇÃO
class RegA120 extends AbstractReg
{
    // Valor total do serviço, prestado por pessoa física ou jurídica
    // domiciliada no exterior.
    protected $VL_TOT_SERV;
    // Valor da base de cálculo da Operação – PIS/PASEP – Importação
    protected $VL_BC_PIS;
    // Valor pago/recolhido de PIS/PASEP – Importação
    protected $VL_PIS_IMP;
    // Data de pagamento do PIS/PASEP – Importação
    protected $DT_PAG_PIS;
    // Valor da base de cálculo da Operação – COFINS – Importação
    protected $VL_BC_COFINS;
    // Valor pago/recolhido de COFINS – Importação
    protected $VL_COFINS_IMP;
    // Data de pagamento do COFINS – Importação
    protected $DT_PAG_COFINS;
    // Local da execução do serviço:
    //     0 – Executado no País;
    //     1 – Executado no Exterior, cujo resultado se verifique no País.
    protected $LOC_EXE_SERV;

    protected function getValidationArray()
    {
    }

    protected function setAttributes($data = [])
    {
        $this->VL_TOT_SERV = "";
        $this->VL_BC_PIS = "";
        $this->VL_PIS_IMP = "";
        $this->DT_PAG_PIS = "01/11/2017";
        $this->VL_BC_COFINS = "";
        $this->VL_COFINS_IMP = "";
        $this->DT_PAG_COFINS = "01/11/2017";
        $this->LOC_EXE_SERV = "";

        return $this;
    } 

    protected function validaRegistro()
    {
        if (!Util::hasIn($this->LOC_EXE_SERV, ["0", "1"]))
            $this->addError("0600 - Nome do centro de custos não informado.");

        return $this;
    }

    protected function layout()
    {
        $this->line = parent::setLine([
            $this->numReg,
            Util::numberFormat($this->VL_TOT_SERV),
            Util::numberFormat($this->VL_BC_PIS),
            Util::numberFormat($this->VL_PIS_IMP),
            Util::dateFormat($this->DT_PAG_PIS),
            Util::numberFormat($this->VL_BC_COFINS),
            Util::numberFormat($this->VL_COFINS_IMP),
            Util::dateFormat($this->DT_PAG_COFINS),
            $this->LOC_EXE_SERV
        ]);

        return $this;
    }
}
