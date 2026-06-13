<?php

namespace App\Processors\Sped\Fiscal\Bloco0;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;

/**
 * não desenvolvido até o momento
 */
class Reg0400 extends AbstractReg
{

    // Código da natureza da operação/prestação
    protected $COD_NAT;
    // Descrição da natureza da operação/prestação
    protected $DESCR_NAT;

    protected function setAttributes($data = [])
    {
        $unique = function ($nf) {
            return $nf->nfoperacao_id . $nf->descricaofiscal;
        };
        $filter = function ($nf) {
            $models = [
                '21', '22', '01', 'A1', '1B', '04', '55', '65'
            ];
            $allowedE = $nf->tiponf == 'emitida' && NfUtil::isAuthorized($nf->nfsituacao_id);
            $allowedR = $nf->tiponf == 'recebida' && Util::hasIn($nf->nfmodelo, $models);
            return ($allowedE || $allowedR) &&
                    $nf->tiponf == 'recebida'; //adicionado recentemente por conta do regc170
        };
        $operacoes = $data['nf']->filter($filter)->unique($unique);

        if ($operacoes->count() === 0)
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
