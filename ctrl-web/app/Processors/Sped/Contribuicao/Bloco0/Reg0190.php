<?php

namespace App\Processors\Sped\Contribuicao\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;

class Reg0190 extends AbstractReg
{
    // Código da unidade de medida
    protected $UNID;
    // Descrição da unidade de medida
    protected $DESCR;

    protected function setAttributes($data = [])
    {
        $unidades = $data['nf']->filter(function ($nf) {
            return $nf->nfsituacao_id == 100 || ($nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, ['01','1B','04','55','65']));
        })->unique('ucom');

        if($unidades->count() === 0)
            $this->none = true;

        foreach ($unidades as $un) {
            $this->UNID = $un->ucom;
            $this->DESCR = $un->unidade_medida;

            $this->addReg($this);
        }
        $this->setGenericError("Unidades de Medida");
        return $this;
    }

    protected function getValidationArray()
    {
        return [
            'UNID'  => static::getBaseVR("Código da unidade de medida", 6),
            'DESCR' => static::getBaseVR("Descrição da unidade de medida", false),
        ];
    }
}
