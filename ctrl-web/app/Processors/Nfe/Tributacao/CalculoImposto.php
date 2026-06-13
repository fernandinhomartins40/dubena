<?php

namespace App\Processors\Nfe\Tributacao;

use App\Processors\Nfe\Tributacao\Base\CofinsBase;
use App\Processors\Nfe\Tributacao\Base\DetBase;
use App\Processors\Nfe\Tributacao\Base\IpiBase;
use App\Processors\Nfe\Tributacao\Base\PisBase;
use App\Processors\Nfe\Tributacao\XmlTags\TagIcms;
use App\Processors\Nfe\Tributacao\XmlTags\TagProd;
use DB;
use StdClass;
use Exception;
use Illuminate\Support\Collection;
use App\Helpers\Utils\NfUtil as Util;
use App\Processors\Nfe\Tributacao\Base\ValidationBase;
use App\Processors\Nfe\Tributacao\XmlTags\TagIbsCbs;

/**
 * Description of CalculoTributacao
 *
 * @author Jeferson
 */
class CalculoImposto extends ValidationBase
{

    /**
     * valor dos items para fins de cálculo quando a Nf é complementar
     * @var float
     */
    private $vProdComplementar;

    /**
     * Std com os valores da tag ICMSUFDest
     * @var stdClass
     */
    private $stdICMSUfDest;

    /**
     * Armazena o valor líquido do item
     * @var float
     */
    private $vLiqItem;

    /**
     * Armazena os produtos do banco de dados
     *
     * @var \App\Produto|DB
     */
    private $produtoDB;

    /**
     * Armazena o Desttinatário
     *
     * @var \App\Cliente|DB
     */
    private $destinatario;

    /**
     *
     * @var array
     */
    private $produto;

    /**
     * Indicador de operação (1 - interna, 2 - interestadual, 3 - exterior)
     * @var int|string
     */
    private $idDest;

    /**
     * Indica se é uma venda para consumidor final
     * @var bool
     */
    private $isConsumidorFinal;

    /**
     * Armazena o valor total dos itens para fins de cálculos
     * <i>Obs.: Criado para NF complementar que pode ter valor = 0
     * mas não pode calcular os impostos com zero</i>
     * @var float
     */
    private $vTotalToCalculo;

    /**
     * Armazena o imposto relacionado ao grupo fiscal do produto
     * e a operação do item
     * @var \App\Nfimposto|DB
     */
    private $nfimposto;

    /**
     * UF do emitente
     * @var string
     */
    private $emituf;

    /**
     *  UF do destinatário
     * @var string
     */
    private $destuf;

    /**
     * valor com a "index" do item referente à NF
     * @var int|string
     */
    private $nItem;

    /**
     * Armazena a Classe NfeImposto
     * @var ImpostoDB
     */
    private $imposto;

    /**
     * Armazena a operação de venda do item
     * @var \App\Nfoperacao|DB
     */
    private $nfoperacao;

    /**
     * Quantidade de itens vendida
     * @var int
     */
    private $qCom;

    /**
     * Valor unitário do item
     * @var float
     */
    private $vUnCom;

    /**
     * Quantidade do item para fins de cálculo,
     * <i> Obs.: Auando a NF é
     * complementar e o valor é menor ou igual a zero,
     * esse valor vai para 1 </i>
     * @var int
     */
    private $qComToCalculo;

    /**
     *  Quantidade total de items da venda
     * @var int
     */
    private $nItems;

    /**
     * Armazena a Nota gravada no banco
     * @var \App\Nfemitida|\App\Nfrecebida|\DB
     */
    private $nf;

    /**
     *  Armazena a Model da Empresa da NF
     * @var \App\Empresa|\DB
     */
    private $empresa;

    /**
     * Armazena os valores da lei "De olho no Imposto" de acordo com a ibpt
     * @var \App\Produtoleiimposto|\DB
     */
    private $prodLeiImposto;

    /**
     * Objeto que contém os valores totais somado dos itens anteriores
     * @var StdClass
     */
    private $total;

    /**
     * Valor de desconto Unitário total somado dos itens anteriores
     * @var float
     */
    private $vDescUntTotal;

    /**
     * indica se é uma nota complementar ou não
     * @var bool
     */
    private $isComplementar;

    /**
     * valor da alíquota nacinal da lei "De Olho no Imposto"
     * @var float
     */
    public $aliqnac;

    /**
     * valor da alíquota estadual da lei "De Olho no Imposto"
     * @var float
     */
    public $aliqest;

    /**
     * valor da alíquota municipal da lei "De Olho no Imposto"
     * @var float
     */
    public $aliqmun;

    /**
     * Armazena a Classe DetBase
     * @var DetBase
     */
    public $det;

    /**
     * Armazena a classe TagIcms
     * @var TagIcms
     */
    public $icms;

    /**
     * Armazena o tipo de NF se é Emitida ou Recebida
     * @var string
     */
    public $tipoNf;

    /**
     * indica se está usando o ICMS Desonerado ou não
     * @var bool
     */
    public $usingDeson;

    /**
     * Armazena os dados da Tag do item
     * @var TagProd
     */
    private $tagProd;

    /**
     * valores do IPI
     * @var IpiBase
     */
    private $objIPI;

    /**
     * se é uma importação de XML
     * @var bool
     */
    private $isImporting = false;

    /**
     * conteúdo original de um xml importado
     * @var array|object
     */
    private $originalContent;

    public $ibscbs;

    /**
     * NfImpostoItem constructor.
     * chama função setOptions para preencher os atributos da classe
     * chama a função init para realizar os calculos
     * @param array|StdClass $options
     * @throws Exception
     */
    public function __construct($options)
    {
        $this->setOptions($options);
        $this->init();
    }

    /**
     * seta as variáveis passados pelo array $options
     * @param array $options
     */
    private function setOptions($options)
    {
        $vars = get_object_vars($this);
        foreach ($options as $key => $opt) {
            if (array_key_exists($key, $vars)) {
                $this->{$key} = $opt;
            }
        }
    }

    /**
     * faz os calculos de icms, ipi, pis, cofins, etc..
     *
     * @throws Exception
     */
    public function init()
    {
        $this->setClasses();
        
        $this->setValuesProd();

        $this->setTagProd();

        $this->icms->calcMVAST($this->idDest);

        $this->vLiqItem = $this->getVLiqItem();

        $this->icms->calcRedBC();

        $this->usingDeson = Util::useDeson($this->icms->CST);

        $this->icms->calcVBCICMS($this->vLiqItem);

        $this->icms->calcICMSDeson($this->vLiqItem);

        if (Util::useDiferimento($this->icms->CST)) {
            $this->icms->calcICMSDif();
        } else {
            $this->icms->calcICMS();
        }

        $this->ipi();

        $this->icms->calcREDBCST();

        $vIPI = isset($this->objIPI->vIPI) ? $this->objIPI->vIPI : 0;

        $this->icms->calcICMSST($this->isConsumidorFinal, $vIPI, $this->vLiqItem);
        $this->icms->calcCredSN($this->vLiqItem, $this->empresa->nfecreditosimplesnacional);
        $indIEDest = isset($this->destinatario->indicador_ie) ? $this->destinatario->indicador_ie : 1;
        $this->stdICMSUfDest = $this->icms->calcICMSUFDest(
            $this->nf,
            $this->tagProd->cProdANP,
            $this->nfoperacao->tiponf,
            $this->idDest,
            $indIEDest
        );

        $this->icms->calcFCP();
        $this->icms->calcICMSRet();
        $this->icms->calcMono($this->tagProd->qTrib);
        $this->icms->clearUnusedTags();

        //IBS/CBS
        $this->ibscbs->calcVBCIBSCBS($this->vLiqItem);
        $this->ibscbs->calcIBSUF();
        $this->ibscbs->calcIBSMUN();
        $this->ibscbs->calcCBS();
        $this->ibscbs->calcMono(floatval($this->tagProd->qTrib));
        $this->ibscbs->clearUnusedTags();
        $this->setDetParams();
    }

    /**
     * starta a classe do IPI;
     */
    private function ipi()
    {
        $this->objIPI = new IpiBase();
        if ($this->produtoDB->nfipi !== null) {
            $this->objIPI->CST = $this->produtoDB->nfipi->codigo;
            $this->objIPI->vBC = sprintf("%0.2f", calcPercent($this->produtoDB->nfebcipi) * $this->vLiqItem);
            $this->objIPI->pIPI = sprintf("%0.2f", $this->produtoDB->nfealiqipi);
            $this->objIPI->vIPI = sprintf("%0.2f", calcPercent($this->objIPI->pIPI) * $this->objIPI->vBC);
            $this->objIPI->cEnq = $this->produtoDB->nfecodenquadramentoipi;
            $this->objIPI->item = $this->nItem;
        }
    }

    /**
     * soma os valores do item
     * @return float
     */
    private function getVLiqItem()
    {
        //vFrete adicioonado em 09/08/18
        $p = $this->tagProd;
        return $p->vItemToCalc - $p->vDesc + $p->vSeg + $p->vOutro + $p->vFrete;
    }

    /**
     * seta os valores da classe DetBase
     * @throws Exception
     */
    private function setDetParams()
    {
        $this->det->setPisCofins($this->pis(), $this->cofins());
        $this->det->setIcms($this->icms);
        $this->det->setProd($this->tagProd);
        $this->det->setVTotTrib($this->getTotTrib());
        $this->det->setIcmsUfDest($this->stdICMSUfDest);
        $this->det->setNfImposto($this->imposto);
        $this->det->setIPI($this->objIPI);
        $this->det->setIbsCbs($this->ibscbs);
    }

    /**
     * seta as classes DetBase, NfeImposto e ICMS em suas respectivas variáveis
     * @throws Exception
     */
    private function setClasses()
    {
        $this->det = new DetBase($this->nItem, $this->produto[15]);

        $this->imposto = new ImpostoDB([
            'emituf'            => $this->emituf,
            'destuf'            => $this->destuf,
            'idDest'            => $this->idDest,
            'isConsumidorFinal' => $this->isConsumidorFinal,
            'produto'           => $this->produto,
            'nfimposto'         => $this->nfimposto
        ]);

        $this->icms = new TagIcms($this->imposto, $this->tipoNf, $this->isSimples(), $this->isConsumidorFinal, $this->nfoperacao->cfop);
        $this->ibscbs = new TagIbsCbs($this->imposto, $this->tipoNf, $this->isSimples(), $this->isConsumidorFinal, $this->nfoperacao->cfop);
    }

    /**
     * seta valores do produto:
     * vUnCom, vTotalToCalculo e qCom
     */
    private function setValuesProd()
    {
        $this->vUnCom = insertNumeroDecimalOracle($this->produto[6]);

        $this->vTotalToCalculo = $this->getVTotItemToCalc();

        $this->qCom = insertNumeroDecimalOracle($this->produto[7]);
    }

    /**
     * retorna se o regime do destinatário é do Simples Nacional ou não
     * @return bool
     */
    private function isSimples()
    {
        if ($this->tipoNf === "E" && !isset($this->nf->tipolancamento)) {
            return $this->destinatario->simples && strlen($this->destinatario->cnpj) > 0;
        } else {
            return isset($this->destinatario->nfecrt) ? $this->destinatario->nfecrt != 3 : $this->empresa->nfecrt != 3;
        }
    }

    /**
     * monta o objeto de cofins
     * @return CofinsBase
     * @throws Exception
     */
    public function cofins()
    {
        $objCofins = new CofinsBase();
        $vBC = $this->vLiqItem - Util::calcImpostoProp($this->vLiqItem, 100 - $this->imposto->get("nfcofinsbase"));
        $aliq = $this->imposto->get('nfcofinsaliq');
        $cst = $this->imposto->get('codigocofins');
        $objCofins->CST = $cst;
        $objCofins->vBC = !$vBC ? 0 : $vBC;

        if (!Util::useAliqPISCOFINSPercent($cst)) {
            //não oferecemos essa opção por hora
            $objCofins->qBCProd = null;
            $objCofins->vAliqProd = null;
            $objCofins->pCOFINS = $aliq;
            $objCofins->vCOFINS = Util::calcImpostoProp($vBC, $aliq);
            if ($cst === "03") {
                $msg = "Não foi possível gerar a tag Cofins com cst [$cst] para o "
                    . "imposto código: [{$this->nfimposto->id}]";
                throw new Exception($msg);
            }
        } else {
            $objCofins->qBCProd = null;
            $objCofins->vAliqProd = null;
            $objCofins->pCOFINS = $aliq;
            $objCofins->vCOFINS = Util::calcImpostoProp($vBC, $aliq);
        }

        return $objCofins;
    }

    /**
     * monta o objeto de pis
     * @return PisBase
     * @throws Exception
     */
    public function pis()
    {
        $objPis = new PisBase();
        $aliq = $this->imposto->get('nfpisaliq');
        $vBC = $this->vLiqItem - Util::calcImpostoProp($this->vLiqItem, 100 - $this->imposto->get("nfpisbase"));
        $cst = $this->imposto->get('codigopis');
        $objPis->CST = $cst;
        $objPis->vBC = !$vBC ? 0 : $vBC;


        //fonte das validações: http://www.flexdocs.com.br/guiaNFe/gerarNFe.detalhe.imp.PIS.html
        if (!Util::useAliqPISCOFINSPercent($cst)) {
            //não oferecemos essa opção por hora
            $objPis->qBCProd = null;
            $objPis->vAliqProd = null;
            $objPis->pPIS = $aliq;
            $objPis->vPIS = Util::calcImpostoProp($vBC, $aliq);
            $msg = "Não é possível gerar a tag PIS com cst [$cst] para o "
                . "imposto código: [{$this->nfimposto->id}]";
            if ($cst === "03") {
                throw new Exception($msg);
            }
        } else {
            $objPis->qBCProd = null;
            $objPis->vAliqProd = null;
            $objPis->pPIS = $aliq;
            $objPis->vPIS = Util::calcImpostoProp($vBC, $aliq);
        }

        return $objPis;
    }

    /**
     * soma o valor total dos tributos de acordo com a Lei de Olho no Imposto
     * @return float|string
     */
    private function getTotTrib()
    {
        $aliqnac = $this->getAliqNac();
        $aliqestadual = $this->getAliqEst();
        $aliqmunicipal = $this->getAliqMun();

        return sprintf('%0.2f', $aliqnac + $aliqestadual + $aliqmunicipal);
    }

    /**
     * calcula o alíquota nacional da Lei de Olho no Imposto
     * @return float
     */
    public function getAliqNac()
    {
        $lei = $this->prodLeiImposto;
        return !is_null($lei) ? (($this->vTotalToCalculo * $lei->aliqnac) / 100) : 0;
    }

    /**
     * calcula o alíquota municipal da Lei de Olho no Imposto
     * @return float
     */
    public function getAliqMun()
    {
        $lei = $this->prodLeiImposto;
        return !is_null($lei) ? (($this->vTotalToCalculo * $lei->aliqmunicipal) / 100) : 0;
    }

    /**
     * calcula o alíquota estadual da Lei de Olho no Imposto
     * @return float
     */
    public function getAliqEst()
    {
        $lei = $this->prodLeiImposto;
        return !is_null($lei) ? (($this->vTotalToCalculo * $lei->aliqestadual) / 100) : 0;
    }

    /**
     * calcula o valor total do valor do item para fins de calculo
     * @return float
     */
    private function getVTotItemToCalc()
    {
        $this->qComToCalculo = insertNumeroDecimalOracle($this->produto[7]);

        if ($this->qComToCalculo <= 0 && $this->isComplementar)
            $this->qComToCalculo = 1;

        return $this->qComToCalculo * $this->vUnCom;
    }

    /**
     * gera o objeto da tag produto
     * @throws Exception
     */
    private function setTagProd()
    {
        $this->tagProd = new TagProd();
        $this->tagProd->EXTIPI = $this->produto[14];
        $this->tagProd->CEST = $this->produto[15];
        if ($this->tagProd->EXTIPI === '0')
            $this->tagProd->EXTIPI = null;
        elseif (strlen($this->tagProd->EXTIPI) == 1)
            $this->tagProd->EXTIPI = str_pad($this->tagProd->EXTIPI, 2, "0", STR_PAD_LEFT);

        $vProdCalc = sprintf('%0.5f', $this->qComToCalculo * $this->vUnCom);

        $this->tagProd->qCom = $this->qCom;
        $this->tagProd->vUnCom = $this->vUnCom;
        $this->tagProd->vProdItem = $vProdCalc;
        $this->tagProd->vItemToCalc = $vProdCalc;
        $this->tagProd->vProd = $vProdCalc;
        $this->tagProd->cProd = $this->produto[4];
        $this->tagProd->cEAN = $this->produto[13];
        $this->tagProd->xProd = $this->produto[16];
        $this->tagProd->NCM = $this->produto[11];
        $this->tagProd->uCom = $this->produto[12];

        $this->tagProd->indTot = '1';
        $this->tagProd->xPed = null;
        $this->tagProd->nItemPed = $this->nItem;
        $this->tagProd->nFCI = null;
        $cfop = (int) $this->idDest === 1 ? $this->nfoperacao->cfop : $this->nfoperacao->cfopie;
        $this->tagProd->CFOP = $cfop;
        $this->tagProd->cProdANP = $this->produtoDB->nfecprodanp;
        $this->tagProd->descANP = $this->produtoDB->nfedescanp;
        $this->tagProd->vAliqProd = $this->produtoDB->nfevaliqprod;
        $this->tagProd->vCIDE = $this->produtoDB->nfevcide;
        $this->tagProd->qBCProd = $this->produtoDB->nfeqbcprod;
        if ($this->tagProd->cProdANP == "210203001") {
            $this->tagProd->uTrib = "Kg";
            $this->tagProd->qTrib = $this->produto[19]; //19 é o peso líquido total
            $sum = $this->tagProd->qTrib / ($this->qCom ? $this->qCom : 1);
            $this->tagProd->vUnTrib = sprintf('%0.10f', $this->tagProd->vUnCom / ($sum > 0 ? $sum : 1));
            if (strpos($this->produto[21], ",") === false) {
                $this->tagProd->pGNi = sprintf('%0.4f', $this->produto[21]);
                $this->tagProd->pGNn = sprintf('%0.4f', $this->produto[22]);
                $this->tagProd->pGLP = sprintf('%0.4f', $this->produto[23]);
            } else {
                $this->tagProd->pGNi = sprintf('%0.4f', insertNumeroDecimalOracle($this->produto[21]));
                $this->tagProd->pGNn = sprintf('%0.4f', insertNumeroDecimalOracle($this->produto[22]));
                $this->tagProd->pGLP = sprintf('%0.4f', insertNumeroDecimalOracle($this->produto[23]));
            }

            $sum = $this->tagProd->pGNi + $this->tagProd->pGNn + $this->tagProd->pGLP;
            if ($sum < 100 && $sum > 0) {
                throw new Exception("A soma dos percentuais de GLP é diferente de 100. " .
                    "Verifique o cadastro do produto \"" . $this->tagProd->xProd . "\" e tente novamente");
            }
            $this->tagProd->vPart = sprintf('%0.2f', $this->tagProd->vUnTrib);
            $this->tagProd->cEANTrib = $this->produtoDB->eantrib;
        } else {
            $this->tagProd->uTrib = $this->produto[12];
            $this->tagProd->qTrib = $this->qCom;
            $this->tagProd->vUnTrib = insertNumeroDecimalOracle($this->produto[6]);
            $this->tagProd->pGNi = null;
            $this->tagProd->pGNn = null;
            $this->tagProd->pGLP = null;
            $this->tagProd->vPart = null;
            $this->tagProd->cEANTrib = $this->produto[13]; //quando gerar sem unidade tributavel diferente, usa o mesmo EAN que a unidade comercial
        }
        $vProdT = $this->isComplementar ? $this->vProdComplementar : $this->nf->vprod;
        if ($vProdT) {
            if ($this->isImporting) {
                if (is_array($this->originalContent)) {
                    $this->originalContent = $this->originalContent instanceof Collection ? $this->originalContent : collect($this->originalContent);
                    $prodXml = $this->originalContent->filter(function ($el) {
                        return $el->attributes->nItem == $this->nItem;
                    })->first();
                } else {
                    $prodXml = $this->originalContent;
                }
            } else {
                $prodXml = false;
            }
            $this->tagProd->vFrete = $this->vFreteUnt($vProdT, $prodXml);
            $this->tagProd->vSeg = $this->vSegUnt($vProdT, $prodXml);
            $this->tagProd->vOutro = $this->vOutroUnt($vProdT, $prodXml);
            $this->tagProd->vDesc = $this->getVDescUn($vProdT, $prodXml);
        }
    }

    /**
     * calcula o valor do desconto do item
     * @param $vProdT
     * @param $prodXml
     * @return float
     */
    private function getVDescUn($vProdT, $prodXml)
    {
        if ($prodXml) {
            $vDesc = sprintf('%0.2f', isset($prodXml->prod->vDesc) ? $prodXml->prod->vDesc : "0.0");
        } else {
            $percentDesc = sprintf('%0.5f', ($this->tagProd->vItemToCalc / $vProdT));
            $vDesc = sprintf('%0.5f', $this->nf->vdesc * $percentDesc);

            if ($this->nItems == $this->tagProd->nItemPed)
                $vDesc = $this->nf->vdesc - $this->vDescUntTotal;
        }
        return floatVal($vDesc) === 0.00 ? null : $vDesc;
    }

    /**
     * calcula o valor do frete do item
     * @param $vProdT
     * @param $prodXml
     * @return float
     */
    public function vFreteUnt($vProdT, $prodXml)
    {
        if ($prodXml) {
            $vFrete = sprintf('%0.2f', isset($prodXml->prod->vFrete) ? $prodXml->prod->vFrete : "0.0");
        } else {
            $vProp = $this->nf->vfrete / $vProdT;
            $vFrete = sprintf('%0.2f', $this->vTotalToCalculo * $vProp);

            if ($this->nItems == $this->nItem)
                $vFrete = $this->nf->vfrete - $this->total->vFrete;
        }
        return $vFrete <= 0 ? null : $vFrete;
    }

    /**
     * calcula o valor do seguro do item
     * @param $vProdT
     * @param $prodXml
     * @return float
     */
    public function vSegUnt($vProdT, $prodXml)
    {
        if ($prodXml) {
            $vSeg = sprintf('%0.2f', isset($prodXml->prod->vSeg) ? $prodXml->prod->vSeg : "0.0");
        } else {
            $vProp = $this->nf->vseg / $vProdT;
            $vSeg = sprintf('%0.2f', $this->vTotalToCalculo * $vProp);

            if ($this->nItems == $this->nItem)
                $vSeg = $this->nf->vseg - $this->total->vSeg;
        }
        return $vSeg <= 0 ? null : $vSeg;
    }

    /**
     * calcula o valor de outras despesas do item
     * @param $vProdT
     * @param $prodXml
     * @return float
     */
    public function vOutroUnt($vProdT, $prodXml)
    {
        if ($prodXml) {
            $vOutro = sprintf('%0.2f', isset($prodXml->prod->vOutro) ? $prodXml->prod->vOutro : "0.0");
        } else {
            $vProp = $this->nf->voutro / $vProdT;
            $vOutro = sprintf('%0.2f', $this->vTotalToCalculo * $vProp);

            if ($this->nItems == $this->nItem)
                $vOutro = $this->nf->voutro - $this->total->vOutro;
        }
        return $vOutro <= 0 ? null : $vOutro;
    }

    public function get($attr)
    {
        return $this->{$attr};
    }
}
