<?php
namespace App\Processors\Sped\Contribuicao\BlocoF;

use App\Processors\Sped\AbstractReg;
use App\Services\CarbonCustom as Carbon;

use App\Processors\Sped\Util;
use App\Empresa;
use Session;
// REGISTRO F010: IDENTIFICAÇÃO DO ESTABELECIMENTO
class RegF010 extends AbstractReg
{
    // Número de inscrição do estabelecimento no CNPJ
    protected $CNPJ;

    protected function setAttributes($data = [])
    {
        $this->CNPJ = Util::pregReplaceCnpjCpf(Session::get("empresa_padrao")->cnpj);
        
        return $this;
    } 

    protected function getValidationArray()
    {
        return [
            'CNPJ'  => static::getBaseVR("Número de inscrição do estabelecimento no CNPJ", 14, true)
        ];
    }
}
