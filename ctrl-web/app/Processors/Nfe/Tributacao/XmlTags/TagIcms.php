<?php

namespace App\Processors\Nfe\Tributacao\XmlTags;

use App\Processors\Nfe\Tributacao\ImpostoDB;
use Exception;
use App\Processors\Nfe\Tributacao\Base\IcmsBase;
use App\Helpers\Utils\NfUtil as Util;

/**
 * base class for ICMS tags
 */
class TagIcms extends IcmsBase
{

    /**
     * define se usa ou não a tag de ICMSUFDest
     * @var bool
     */
    private $usingICMSUfDest = false;

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
     * verifica se o regime é simples Nacional ou não
     * @var bool
     */
    private $isSimples;

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
     * TagIcms constructor.
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
        $this->isSimples = $isSimples;
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
        $this->modBC = $this->imposto->get('modalidadebcicms');
        $this->CSOSN = $this->imposto->get('codigocst');
        $this->CST = $this->imposto->get('codigocst');
        $this->pICMS = $this->imposto->get('nficmsaliq');
        $this->pICMSST = $this->imposto->get('aliqicmsst');
        $this->orig = $this->imposto->get('origemicms');
        $this->modBCST = $this->imposto->get('modalidadebcicmsst');
        $this->adRemICMSRet = $this->imposto->get('nficmsalimono');
    }

    /**
     * @return $this
     */
    public function calcREDBCST()
    {
        //tem predbcST = 10, 30, 70, 90, icmsPart
        if (Util::useREDBCST($this->CST)) {
            $pRed = 100 - $this->imposto->get('nficmsbasest');
            $this->pRedBCST = $pRed == 0 ? null : $pRed;
        } else {
            $this->pRedBCST = null;
        }

        return $this;
    }

    /**
     * @param $vLiqItem
     * @param $cred
     */
    public function calcCredSN($vLiqItem, $cred)
    {
        //Credito Simples do cadastro da Empresa
        $this->pCredSN = $cred;
        $this->vCredICMSSN = ($vLiqItem * $this->pCredSN) / 100;
    }

    /**
     * @return $this
     */
    public function calcFCP()
    {
        $vBCFCP = floatVal($this->vBC);
        $pFCP = floatVal($this->imposto->get('taxafecop'));
        $vFCP = Util::calcImpostoProp($vBCFCP, $pFCP);

        //FECOP com ST
        $vBCFCPST = floatVal($this->vBCST);
        $pFCPST = floatVal($this->imposto->get('taxafecop'));
        $vFCPST = Util::calcImpostoProp($vBCFCPST, $pFCPST) - $vFCP;

        $isInvalid = $vBCFCPST === 0.00 || $pFCPST === 0.00 || $vFCPST === 0.00;

        if (!Util::useFCPST($this->CST) || $isInvalid || ($this->usingICMSUfDest && $this->isConsumidorFinal)) {
            $vBCFCPST = null;
            $pFCPST = null;
            $vFCPST = null;
        }

        $isInvalid = $vBCFCP === 0.00 || $pFCP === 0.00 || $vFCP === 0.00;

        if (! Util::useFCPNormal($this->CST) || $isInvalid || ($this->usingICMSUfDest && $this->isConsumidorFinal)) {
            $vBCFCP = null;
            $pFCP = null;
            $vFCP = null;
        }

        $this->pFCP = $pFCP;
        $this->vFCP = $vFCP;
        $this->vBCFCP = $vBCFCP;

        $this->pFCPST = $pFCPST;
        $this->vBCFCPST = $vBCFCPST;
        $this->vFCPST = $vFCPST;

        return $this;
    }

    /**
     * @param $idDest
     * @return $this
     * @throws Exception
     */
    public function calcMVAST($idDest)
    {
        //vamos obrigar o usuário a informar o mva ajustado já no campo mva do imposto
        $pMVAST = $this->imposto->get('mva');

        //operação interna usa o mva reduzido
        if ((int) $idDest === 1) {
            //Se simples MVA Reduzido, se não o MVA
            $pMVAST = $this->isSimples ? $this->imposto->get('mvareduzido') : $pMVAST;
        }

        $this->pMVAST = $pMVAST;

        if ($this->pMVAST < 0) {
            $msg = "O cálculo do percentual do MVA gerou valores negativos, por favor confira seu cadastro do imposto código {$this->imposto->get('id')}";
            throw new Exception($msg);
        } elseif ($this->pMVAST == 0) {
            $this->pMVAST = null;
        }

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function calcICMS()
    {
        //Base de cálculo ICMS = (valortotal do produto - desconto) * % baseicms
        $this->vICMS = Util::calcImpostoProp($this->vBC, $this->pICMS, 5);

        if ($this->vICMS < 0) {
            throw new Exception("O valor do ICMS ficou negativo, verificar cadastro de imposto código \"{$this->imposto->get('id')}\"");
        }

        if ($this->vICMS <= 0) {
            $this->vICMS = '0.00';
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function calcICMSDif()
    {
        $pDif = floatVal($this->imposto->get('nfaliqdiferimento'));
        $pICMS = floatVal($this->imposto->get('nficmsaliq'));

        $vICMSOp = Util::calcImpostoProp($this->vBC, $pICMS, 5);

        //percentual de diferimento (campo novo)
        $this->pDif = $pDif === 0.0 ? null : $pDif;

        //(perc de icms normal)
        $this->pICMS = $pICMS === 0.0 ? null : $pICMS;

        //valor icms proprio
        $this->vICMSOp = $vICMSOp === 0.0 ? null : $vICMSOp;

        //valor Diferimento icms
        $vICMSDif = Util::calcImpostoProp($vICMSOp, $pDif, 5);
        $this->vICMSDif = floatVal($vICMSDif === 0.00) ? null : $vICMSDif;

        //valor do icms
        $vICMS = $vICMSOp - $vICMSDif;
        $this->vICMS = floatVal($vICMS) === 0.00 ? null : $vICMS;

        return $this;
    }

    /**
     * @param $usaImpostoPF
     * @param $vIPI
     * @param $vLiqItem
     * @return $this
     * @throws Exception
     */
    public function calcICMSST($usaImpostoPF, $vIPI, $vLiqItem)
    {
        $vLiq = $vLiqItem + $vIPI;
        $vBC = $vLiq - Util::calcImpostoProp($vLiq, $this->pRedBCST, 5);
        /**
         * Para o cálculo da BC ST, o contribuinte deverá aplicar a
         * alíquota interna sobre a BC/ST, prevista no artigo 22 da Lei nº 2657/96.
         * Do valor obtido deve ser abatido o ICMS incidente sobre a operação própria do contribuinte substituto,
         * resultando o ICMS/ST a ser pago em GNRE (no caso de operação interestadual) ou DARJ.
         * O valor do FECP é obtido aplicando 1% sobre a BC/ST e deve ser pago em DARJ, código de receita 750.1.
         */
        if (Util::useST($this->CST) && ! $usaImpostoPF) {
            if ($this->pMVAST) {
                $mva = calcPercent($this->pMVAST);
            } else {
                $mva = 1;
            }

            $this->vBCST = sprintf('%0.5f', ($vBC * $mva) + $vBC);
            $this->vICMSST = Util::calcImpostoProp($this->vBCST, $this->pICMSST, 5) - $this->vICMS;
        } else {
            $this->vBCST = sprintf('%0.5f', 0);
            $this->vICMSST = sprintf('%0.5f', 0);
        }

        $pST = $this->pICMS + $this->imposto->get('taxafecop');

        $this->pST = $pST == 0 ? null : $pST;

        if ($this->vICMSST < 0) {
            $msg = "O valor do ICMS ST ficou negativo, por favor confira seu cadastro "
                    . "de imposto código {$this->imposto->get('id')}";
            throw new Exception($msg);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function calcICMSRet()
    {
        //Se CST 60, zerar o vBC e vICMS
        if ($this->CST === '60') {
            $this->vBC = null;
            $this->vICMS = null;
        }

        $this->vBCSTRet = null;
        $this->vICMSSTRet = null;
        $this->vFCPSTRet = null;
        $this->pFCPSTRet = null;
        $this->vBCFCPSTRet = null;

        return $this;
    }

    /**
     *
     * Famigerado DIFAL (Diferencial de alíquotas)
     * <i>Usado quando o destino da operação é 2 - interestadual</i>
     *
     * @param $nf
     * @param $cProdANP
     * @param $tpNF
     * @param $idDest
     * @param $indIEDest
     * @return \stdClass
     * @throws Exception
     */
    public function calcICMSUFDest($nf, $cProdANP, $tpNF, $idDest, $indIEDest)
    {
        $objIcmsUfDest = new \stdClass();

        $ide = [
            "indFinal"  => (object) $this->isConsumidorFinal ? 1 : 0,
            "idDest"    => $idDest,
            "mod"       => $nf->nfmodelo,
            "tpNF"      => $tpNF
        ];
        if (! Util::generateICMSUFDest($this->CST, $ide, $this->cfop, $cProdANP, $indIEDest)) {
            return $objIcmsUfDest;
        }

        $this->usingICMSUfDest = true;

        if ($this->imposto->get('aliqicmsdest') < $this->imposto->get('nficmsaliq')) {
            throw new Exception("Aliq ICMS Dest do cadastro de imposto não pode ser menor que a Aliq ICMS Inter");
        }

        $aliq = $this->imposto->get('aliqicmsdest') - $this->imposto->get('nficmsaliq');

        $this->vBC = $this->vBC == "" ? 0 : $this->vBC;

        $difal = $this->vBC * ($aliq / 100);
        $fecop = $this->vBC * ($this->imposto->get('taxafecop') / 100);
        $objIcmsUfDest->vBCUFDest = sprintf('%0.2f', $this->vBC);
        $objIcmsUfDest->pICMSUFDest = sprintf('%0.2f', $this->imposto->get('aliqicmsdest'));

        $objIcmsUfDest->pICMSInter = sprintf('%0.2f', $this->imposto->get('nficmsaliq'));

        $year = explode('-', $nf->datahoraentradasaida)[0];
        $objIcmsUfDest->pICMSInterPart = $year === "2018" ? 80.00 : 100.00;

        $pOrigem = 100 - $objIcmsUfDest->pICMSInterPart;
        $pDest = $objIcmsUfDest->pICMSInterPart;
        $vRemet = $difal * ($pOrigem / 100);
        $vDest = $difal * ($pDest / 100);

        $objIcmsUfDest->pFCPUFDest = sprintf('%0.2f', $this->imposto->get('taxafecop'));
        $objIcmsUfDest->vBCFCPUFDest = sprintf('%0.2f', $this->vBC);
        $objIcmsUfDest->vFCPUFDest = sprintf('%0.2f', $fecop);
        $objIcmsUfDest->vICMSUFDest = sprintf('%0.2f', $vDest);
        $objIcmsUfDest->vICMSUFRemet = sprintf('%0.2f', $vRemet);

        return $objIcmsUfDest;
    }

    /**
     *
     */
    public function calcRedBC()
    {
        if (Util::useREDBC($this->CST)) {
            $pRed = 100 - $this->imposto->get('nficmsbase');
            $this->pRedBC = $pRed;
        } else {
            $this->pRedBC = null;
        }
    }

    /**
     * @param $vLiqItem
     */
    public function calcVBCICMS($vLiqItem)
    {
        $this->vBC = $vLiqItem - Util::calcImpostoProp($vLiqItem, $this->pRedBC, 5);
    }

    /**
     * @param $vLiqItem
     * @return $this
     */
    public function calcICMSDeson($vLiqItem)
    {
        if (Util::useDeson($this->CST)) {
            //Base de cálculo ICMS = (valortotal do produto - desconto) * % baseicms
            $vICMS = Util::calcImpostoProp($this->vBC, $this->pICMS, 5);

            $motDesICMS = $this->imposto->get('motdesonicms'); //pegar do imposto
            //Cálculo do valor do ICMS desonerado  (fonte: http://tdn.totvs.com/pages/releaseview.action?pageId=185737442):
            //((base sem redução) * aliqIcms) - vIcms
            if (!Util::useDesonGroup40($this->CST)) {
                $this->vICMSDeson = Util::calcImpostoProp($vLiqItem, $this->pICMS, 5) - $vICMS;
                $this->motDesICMS = $motDesICMS;
            } else {
                if ($this->pICMS > 0 && $this->imposto->get('nficmsbase') > 0) {
                    $this->vICMSDeson = $vICMS;
                    $this->motDesICMS = $motDesICMS;
                } else {
                    $this->vICMSDeson = null;
                    $this->motDesICMS = null;
                }
            }
            if (!$this->motDesICMS || !$this->vICMSDeson) {
                $this->vICMSDeson = null;
                $this->motDesICMS = null;
            }
        }
        return $this;
    }

    public function calcMono($qTrib) {
        if (!Util::isMono($this->CST)) return $this;

        $this->qBCMonoRet = $qTrib;
        $this->vICMSMonoRet = floatval($this->adRemICMSRet * $qTrib);

        return $this;
    }
}
