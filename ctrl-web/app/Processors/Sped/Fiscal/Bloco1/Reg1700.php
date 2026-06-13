<?php

namespace App\Processors\Sped\Fiscal\Bloco1;

use App\Processors\Sped\AbstractReg;
use App\Processors\Sped\Util;
use App\Helpers\Utils\NfUtil;
use Illuminate\Support\Collection;

class Reg1700 extends AbstractReg
{

    /// Código dispositivo autorizado:
    /// 00 - Formulário de Segurança – impressor autônomo
    /// 01 - FS-DA – Formulário de Segurança para Impressão de DANFE
    /// 02 – Formulário de segurança - NF-e
    /// 03 - Formulário Contínuo
    /// 04 – Blocos
    /// 05 - Jogos Soltos
    public $COD_DISP;
    /// Código do modelo do documento fiscal, conforme a Tabela 4.1.1
    public $COD_MOD;
    /// Série do documento fiscal
    public $SER;
    /// SubSérie do documento fiscal
    public $SUB;
    /// Número do dispositivo autorizado (utilizado) inicial
    public $NUM_DOC_INI;
    /// Número do dispositivo autorizado (utilizado) final
    public $NUM_DOC_FIN;
    /// Número da autorização, conforme dispositivo autorizado
    public $NUM_AUT;
    private $denied;

    protected function setAttributes($data = [])
    {
        $notas = $this->filter($data['nf'])->unique('nf_id')->sortBy('nfnumero');
        $nfEmit = $notas->where('tiponf', 'emitida');
        $this->add($nfEmit, $nfEmit->unique(function ($nf) {
            return $nf->nfserie . $nf->nfsubserie . $nf->nfmodelo;
        }));
        $nfRec = $notas->where('tiponf', 'recebida');
        $this->add($nfRec, $nfEmit->unique(function ($nf) {
            return $nf->nfserie . $nf->nfsubserie . $nf->nfmodelo;
        }));
        return $this;
    }

    protected function add($nfs, $uniqueNfs)
    {
        foreach ($uniqueNfs as $nf) {
            $nfFiltered = $nfs->filter(function ($nfFilter) use ($nf) {
                $nfFilter->nfnumero = (int)$nfFilter->nfnumero;
                return $nf->nfmodelo === $nfFilter->nfmodelo
                    && $nf->nfserie === $nfFilter->nfserie
                    && $nf->nfsubserie === $nfFilter->nfsubserie;
            })->sortBy('nfnumero');
            $this->addMultiple($nfFiltered, function ($ini, $final, $nf) {
                $this->setBasic($nf);
                $this->NUM_DOC_INI = $ini;
                $this->NUM_DOC_FIN = $final;

                if (!is_null($this->denied)) {
                    $this->addMultiple($this->denied, function ($ini, $final, $den) {
                        $den = new \stdClass();
                        $den->ini = $ini;
                        $den->fin = $final;
                        $this->addChildren('Reg1710', $den);
                    }, false);
                }
                $this->addReg($this);
            });
        }
    }

    private function addMultiple($nfs, $callback, $addChildren = true)
    {
        $firstNf = 0;
        $lastNf = 0;
        $count = count($nfs);
        $i = 0;
        foreach ($nfs as $nf) {
            $i++;
            if (!$firstNf) {
                $firstNf = $nf->nfnumero;
            }
            if (!$lastNf && $count === $i) {
                $lastNf = $nf->nfnumero;
            }

            if (($nf->nfnumero !== $lastNf + 1 && $lastNf) || $count === $i) {
                call_user_func_array($callback, [$firstNf, $lastNf, $nf]);
                if ($addChildren) {
                    $this->denied = collect([]);
                }
                $firstNf = $nf->nfnumero;
            }
            if (NfUtil::isCanceled($nf->nfsituacao_id) || NfUtil::isInutilized($nf->nfsituacao_id) && $addChildren) {
                if (is_null($this->denied)) {
                    $this->denied = collect([]);
                }
                $den = new \stdClass();
                $den->nfnumero = $nf->nfnumero;
                $den->nfsituacao_id = $nf->nfsituacao_id;
                $this->denied->push($den);
            }
            $lastNf = $nf->nfnumero;
        }
    }

    protected function setBasic($nf)
    {
        $this->COD_DISP = "05";
        if ($nf->nftipoemissao == 2 || $nf->nftipoemissao == 4) {
            $this->COD_DISP = "02";
        }
        $this->COD_MOD = $nf->nfmodelo;
        $this->SER = $nf->nfserie;
        $this->SUB = $nf->nfsubserie;
        $this->NUM_AUT = "0";
    }

    /**
     * @param Collection $nf
     * @return Collection
     */
    private function filter($nf)
    {
        return $nf->filter(function ($nf) {
            return ($nf->tiponf == 'emitida' && NfUtil::isValid($nf->nfsituacao_id)) ||
                $nf->tiponf == 'recebida';
        })->sortBy('nfnumero', SORT_NATURAL)->unique('nf_id');
    }

    protected function getValidationArray()
    {
        return [
            'COD_DISP'    => static::getBaseVR("Código dispositivo autorizado", 2, true, 'O', ['00', '01', '02', '03', '04', '05']),
            'COD_MOD'     => static::getBaseVR("Código do modelo do dispositivo autorizado", 2, true, "O"),
            'SER'         => static::getBaseVR("Série do dispositivo autorizado", 4, false, "OC"),
            'SUB'         => static::getBaseVR("Subsérie do dispositivo autorizado", 3, false, "OC"),
            'NUM_DOC_INI' => static::getBaseVR("Número do dispositivo autorizado utilizado (inicial)", 12, false),
            'NUM_DOC_FIN' => static::getBaseVR("Número do dispositivo autorizado utilizado (final)", 12, false),
            'NUM_AUT'     => static::getBaseVR("Número da autorização, conforme dispositivo autorizado ", 60, false)
        ];
    }

}
