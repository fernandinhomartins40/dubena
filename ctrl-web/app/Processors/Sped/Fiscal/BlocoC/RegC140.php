<?php

namespace App\Processors\Sped\Fiscal\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class RegC140 extends AbstractReg
{

    /// Fatura do Documento
    public $IND_EMIT;
    /// Indicador do emitente do título:
    ///  0- Emissão própria;
    ///  1- Terceiros
    public $IND_TIT;
    /// Indicador do tipo de título de crédito:
    /// 00- Duplicata;
    /// 01- Cheque;
    /// 02- Promissória;
    /// 03- Recibo;
    /// 99- Outros (descrever)
    public $DESC_TIT;
    /// Descrição complementar do título de crédito
    public $NUM_TIT;
    /// Número ou código identificador do título de crédito
    public $QTD_PARC;
    /// Quantidade de parcelas a receber/pagar
    public $VL_TIT;

    /// Valor total dos títulos de créditos

    protected function setAttributes($par = [])
    {
        // se este campo tiver valor igual a “1” (um), o campo IND_OPER deve ser igual a “0” (zero).
        $this->IND_EMIT = $par->tiponf !== 'emitida' ? '1' : '0';
        $this->IND_TIT = "00";
        $this->DESC_TIT = "";
        $this->NUM_TIT = $par->nfnumero;
        $this->QTD_PARC = $par->qtde;
        $this->VL_TIT = Util::numberFormat($par->valortotal, 2);

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'IND_EMIT' => static::getBaseVR("Indicador do emitente do título", 1, true, "O", [0, 1]),
            'IND_TIT'  => static::getBaseVR("Indicador do tipo de título de crédito", 2, true, "O", ['00', '01', '02', '03', '99']),
            'DESC_TIT' => static::getBaseVR("Descrição complementar do título de crédito", false, false, "OC"),
            'NUM_TIT'  => static::getBaseVR("Número ou código identificador do título de crédito"),
            'QTD_PARC' => static::getBaseVR("Quantidade de parcelas a receber/pagar", 2),
            'VL_TIT'   => static::getBaseVR("Valor total dos títulos de créditos")
        ];
    }

}
