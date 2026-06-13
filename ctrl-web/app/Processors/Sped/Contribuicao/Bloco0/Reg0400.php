<?php
namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class Reg0400 extends AbstractReg
{
    // Código da natureza da operação/prestação
    protected $COD_NAT;
    // Descrição da natureza da operação/prestação
    protected $DESCR_NAT;

    protected function setAttributes($data = [])
    {
        $operacoes = $data['nf']->filter(function ($nf) {
            return $nf->nfsituacao_id == 100 || ($nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, ['01','1B','04','55','65']));
        })->unique(function ($nf) {
            return $nf->nfoperacao_id.$nf->descricaofiscal;
        });

        if($operacoes->count() === 0)
            $this->none = true;

        foreach ($operacoes as $ope) {
            $this->COD_NAT = $ope->nfoperacao_id;
            $this->DESCR_NAT = $ope->descricaofiscal;
            $this->setGenericError("Código de Natureza " . $this->COD_NAT);
            $this->addReg($this);
        }

        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'COD_NAT'   => static::getBaseVR("Código da natureza da operação/prestação", 10),
            'DESCR_NAT' => static::getBaseVR("Descrição da natureza da operação/prestação", false)
        ];
    }

}
