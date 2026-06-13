<?php

namespace App\Processors\Nfe\Tributacao\XmlTags;

use App\Processors\Nfe\Tributacao\ImpostoDB;
use Exception;
use App\Processors\Nfe\Tributacao\Base\IbsCbsBase;
use App\Helpers\Utils\NfUtil as Util;

/**
 * base class for IBS tags
 */
class TagIbsCbs extends IbsCbsBase
{
    /**
     *
     * @var ImpostoDB
     */
    private $imposto;

    /**
     * Tipo de NF (emitida ou recebida)
     * @var string
     */
    private $tipoNf;

    /**
     * verifica se o cliente é consumidor final ou não
     * @var bool
     */
    private $isConsumidorFinal;

    /**
     * cfop em uso
     * @var bool
     */
    private $cfop;

    /**
     * TagIBS constructor.
     * @param ImpostoDB $imposto
     * @param $tipoNf
     * @param $isSimples
     * @param $isConsumidorFinal
     * @param $cfop
     */
    public function __construct(ImpostoDB $imposto, $tipoNf, $isSimples, $isConsumidorFinal, $cfop)
    {
        $this->imposto = $imposto;
        $this->tipoNf = $tipoNf;
        $this->cfop = $cfop;
        $this->isConsumidorFinal = $isConsumidorFinal;
        $this->setBasicFields();
        $this->toEmptyTags();
    }

    /**
     * Seta os campos básicos da tag, que não dependem de cálculos
     */
    private function setBasicFields()
    {
        $this->CST = $this->imposto->get('codigoibscbs');
        $this->cClassTrib = $this->imposto->get('classtribibscbs');
        $this->gIBSUF_pIBSUF = $this->imposto->get('nfibsufaliq');
        $this->gIBSMun_pIBSMun = $this->imposto->get('nfibsmunaliq');
        $this->adRemIBSRet = $this->imposto->get('nfibsufaliqmono');
        $this->gCBS_pCBS = $this->imposto->get('nfcbsaliq');
        $this->adRemCBSRet = $this->imposto->get('nfcbsaliqmono');
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function calcIBSUF()
    {
        //Base de cálculo IBS = (valortotal do produto - desconto) * % baseibs
        $this->gIBSUF_vIBSUF = Util::calcImpostoProp($this->vBC, (float) $this->gIBSUF_pIBSUF, 5);

        if ($this->gIBSUF_vIBSUF < 0) {
            throw new Exception("O valor do IBS ficou negativo, verificar cadastro de imposto código \"{$this->imposto->get('id')}\"");
        }

        if ($this->gIBSUF_vIBSUF <= 0) {
            $this->gIBSUF_vIBSUF = '0.00';
        }

        return $this;
    }

        /**
     * @return $this
     * @throws Exception
     */
    public function calcIBSMUN()
    {
        //Base de cálculo IBS = (valortotal do produto - desconto) * % baseibs
        $this->gIBSMun_vIBSMun = Util::calcImpostoProp($this->vBC, (float) $this->gIBSMun_pIBSMun, 5);

        if ($this->gIBSMun_vIBSMun < 0) {
            throw new Exception("O valor do IBS ficou negativo, verificar cadastro de imposto código \"{$this->imposto->get('id')}\"");
        }

        if ($this->gIBSMun_vIBSMun <= 0) {
            $this->gIBSMun_vIBSMun = '0.00';
        }

        return $this;
    }

    public function calcCbs()
    {
        //Base de cálculo Cbs = (valortotal do produto - desconto) * % baseCbs
        $this->gCBS_vCBS = Util::calcImpostoProp($this->vBC, (float) $this->gCBS_pCBS, 5);

        if ($this->gCBS_vCBS < 0) {
            throw new Exception("O valor do Cbs ficou negativo, verificar cadastro de imposto código \"{$this->imposto->get('id')}\"");
        }

        if ($this->gCBS_vCBS <= 0) {
            $this->gCBS_vCBS = '0.00';
        }

        return $this;
    }    

    /**
     *
     */
    public function calcRedBC()
    {
        if (Util::useREDBCIBS($this->CST)) {
            $pRed = 100 - $this->imposto->get('nfibsbase');
            $this->pRedBC = $pRed;
        } else {
            $this->pRedBC = null;
        }
    }

    /**
     * @param $vLiqItem
     */
    public function calcVBCIBSCBS($vLiqItem)
    {
        $this->vBC = $vLiqItem - Util::calcImpostoProp($vLiqItem, $this->pRedBC, 5);
    }

    public function calcMono($qTrib) {
        if (!Util::isMonoIBSCBS($this->CST)) return $this;
        $this->qBCMonoRet = floatval($qTrib);
        $this->vIBSMonoRet = floatval((float) $this->adRemIBSRet * $qTrib);
        $this->vCBSMonoRet = floatval((float) $this->adRemCBSRet * $qTrib);

        $this->qBCMono = null; //floatval($qTrib);
        $this->adRemIBS = null; //0;
        $this->adRemCBS = null; //0;
        $this->vIBSMono = null; //$this->vIBSMonoRet;
        $this->vCBSMono = null; //$this->vCBSMonoRet;

        return $this;
    }
}
