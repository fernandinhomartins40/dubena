<?php
namespace App\Processors\Sped\Contribuicao\BlocoC;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO C010: IDENTIFICAÇÃO DO ESTABELECIMENTO
class RegC010 extends AbstractReg
{
    // Número de inscrição do estabelecimento no CNPJ
    protected $CNPJ;
    // Indicador da apuração das contribuições e créditos, na escrituração das operações por NF-e e ECF, no período:
    //     1 – Apuração com base nos registros de consolidação das operações por NF-e (C180 e C190) e por ECF (C490);
    //     2 – Apuração com base no registro individualizado de NF-e (C100 e C170) e de ECF (C400)
    protected $IND_ESCRI;


    protected function setAttributes($data = [])
    {
        $this->CNPJ = Util::pregReplaceCnpjCpf(Session::get('empresa_padrao')->cnpj);
        $this->IND_ESCRI = "2";
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'CNPJ'      => static::getBaseVR("Número de inscrição do estabelecimento no CNPJ", 14, true),
            'IND_ESCRI' => static::getBaseVR("Indicador da apuração das contribuições e créditos", 1, true, "N", ['1', '2'])
        ];
    }
}
