<?php

namespace App\Processors\Sped\Fiscal\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;

/**
 * 
 */
class Reg0190 extends AbstractReg
{

    /**
     * Código da unidade de medida
     * @var mixed
     */
    public $UNID;

    /**
     * Descrição da unidade de medida
     * @var mixed
     */
    public $DESCR;

    /**
     * retorna o array com as validações genéricas dos campos
     * @return array
     */
    protected function getValidationArray()
    {
        return [
            'UNID'  => static::getBaseVR("Código da unidade de medida", 6),
            'DESCR' => static::getBaseVR("Descrição da unidade de medida", false)
        ];
    }

    /**
     * validações não genericas parciais
     * @return $this
     */
    protected function partialValidaRegistro($obj)
    {
        if (Util::isNullOrEmpty($obj->UNID))
            $obj->addError("Código da unidade de medida não informado.");

        if (Util::isNullOrEmpty($obj->DESCR))
            $obj->addError("Descrição da unidade de medida não informado.");

        return $obj;
    }

    /**
     * seta os atributos da classe
     * @param array $data
     * @return $this
     */
    protected function setAttributes($data = [])
    {
        $results = $data['nf']->filter(function ($nf) {
                    $models = [
                        '21', '22', '01', 'A1', '1B', '04', '55', '65'
                    ];
                    $allowedE = $nf->tiponf == 'emitida' && NfUtil::isAuthorized($nf->nfsituacao_id);
                    $allowedR = $nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, $models);
                    return ($allowedE || $allowedR) &&
                            $nf->tiponf == 'recebida'; //adicionado recentemente por conta do regc170
                })->unique('ucom');

        if (!$results->count()) {
            $this->none = true;
        }

        foreach ($results as $rowSped) {
            $reg = clone $this;
            $reg->UNID = $rowSped->ucom;
            $reg->DESCR = Util::replaceAccent($rowSped->unidade_medida);
            $this->addReg($reg);
        }

        return $this;
    }

}
