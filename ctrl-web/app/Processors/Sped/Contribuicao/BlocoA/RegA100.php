<?php
namespace App\Processors\Sped\Contribuicao\BlocoA;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
/**
* REGISTRO A100: DOCUMENTO - NOTA FISCAL DE SERVIÇO
*
* Childrens: RegA120, RegA170
*/
class RegA100 extends AbstractReg
{
    // Indicador do tipo de operação:
    //     0 - Serviço Contratado pelo Estabelecimento;
    //     1 - Serviço Prestado pelo Estabelecimento.
    protected $IND_OPER;
    // Indicador do emitente do documento fiscal:
    //     0 - Emissão própria;
    //     1 - Emissão de Terceiros
    protected $IND_EMIT;
    // Código do participante (campo 02 do Registro 0150):
    //     - do emitente do documento, no caso de emissão de terceiros;
    //     - do adquirente, no caso de serviços prestados.
    protected $COD_PART;
    // Código da situação do documento fiscal:
    //     00 – Documento regular
    //     02 – Documento cancelado
    protected $COD_SIT;
    // Série do documento fiscal
    protected $SER;
    // Subsérie do documento fiscal
    protected $SUB;
    // Número do documento fiscal ou documento internacional equivalente
    protected $NUM_DOC;
    // Chave/Código de Verificação da nota fiscal de serviço eletrônica
    protected $CHV_NFSE;
    // Data da emissão do documento fiscal
    protected $DT_DOC;
    // Data de Execução / Conclusão do Serviço
    protected $DT_EXE_SERV;
    // Valor total do documento
    protected $VL_DOC;
    // Indicador do tipo de pagamento:
    //     0- À vista;
    //     1- A prazo;
    //     9- Sem pagamento
    protected $IND_PGTO;
    // Valor total do desconto
    protected $VL_DESC;
    // Valor da base de cálculo do PIS/PASEP
    protected $VL_BC_PIS;
    // Valor total do PIS
    protected $VL_PIS;
    // Valor da base de cálculo da COFINS
    protected $VL_BC_COFINS;
    // Valor total da COFINS
    protected $VL_COFINS;
    // Valor total do PIS retido na fonte
    protected $VL_PIS_RET;
    // Valor total da COFINS retido na fonte
    protected $VL_COFINS_RET;
    // Valor do ISS
    protected $VL_ISS;

    protected function setAttributes($data = [])
    {
        $this->IND_OPER = "";
        $this->IND_EMIT = "";
        $this->COD_PART = "";
        $this->COD_SIT = "";
        $this->SER = "";
        $this->SUB = "";
        $this->NUM_DOC = "";
        $this->CHV_NFSE = "";
        $this->DT_DOC = "01/11/2017";
        $this->DT_EXE_SERV = "01/11/2017";
        $this->VL_DOC = "";
        $this->IND_PGT = "";
        $this->VL_DESC = "";
        $this->VL_BC_PIS = "";
        $this->VL_PIS = "";
        $this->VL_BC_COFINS = "";
        $this->VL_COFINS = "";
        $this->VL_PIS_RET = "";
        $this->VL_COFINS_RET = "";
        $this->VL_ISS = "";

        return $this;
    }

    protected function getValidationArray()
    {
    }

    protected function validaRegistro()
    {
        if (Util::isNullOrEmpty($this->IND_OPER) || !Util::hasIn($this->IND_OPER, "0", "1"))
            $this->addError("Indicador do tipo de operação inválido.");

        if (Util::isNullOrEmpty($this->IND_EMIT) || !Util::hasIn($this->IND_EMIT, "0", "1"))
            $this->addError("Indicador do emitente do documento fiscal inválido.");

        if (Util::isNullOrEmpty($this->COD_PART))
            $this->addError("Código do Participante não informado.");

        if (Util::isNullOrEmpty($this->COD_SIT) || !Util::hasIn($this->IND_EMIT, "00", "02"))
            $this->addError("Indicador do emitente do documento fiscal inválido.");

        if (Util::isNullOrEmpty($this->NUM_DOC))
            $this->addError("Número do Documento não informado.");

        if (Util::isNullOrEmpty($this->IND_PGTO) || !Util::hasIn($this->IND_PGTO, "0", "1", "9"))
            $this->addError("Indicador do tipo de pagamento inválido.");

        return $this;
    }

    protected function layout()
    {
        $this->line = parent::setLine([
            $this->numReg,
            $this->IND_OPER,
            $this->IND_EMIT,
            $this->COD_PART,
            $this->COD_SIT,
            $this->SER,
            $this->SUB,
            $this->NUM_DOC,
            $this->CHV_NFSE,
            Util::dateFormat($this->DT_DOC),
            Util::dateFormat($this->DT_EXE_SERV),
            Util::numberFormat($this->VL_DOC),
            $this->IND_PGT,
            Util::numberFormat($this->VL_DESC),
            Util::numberFormat($this->VL_BC_PIS),
            Util::numberFormat($this->VL_PIS),
            Util::numberFormat($this->VL_BC_COFINS),
            Util::numberFormat($this->VL_COFINS),
            Util::numberFormat($this->VL_PIS_RET),
            Util::numberFormat($this->VL_COFINS_RET),
            Util::numberFormat($this->VL_ISS)
        ]);

        return $this;
    }
}
