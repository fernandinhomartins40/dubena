<?php

namespace App\Processors\Nfe\Tools;

use App\Empresa;
use App\Nfemitida;
use App\Processors\Nfe\NfProcessor;
use App\Processors\Nfe\Tributacao\ImpostoDB;
use Exception;
use App\Produto;
use App\Nfoperacao;
use NFePHP\NFe\Make;
use App\Empresaconfig;
use App\Nfemitidaparcela;
use App\Condicaopagamento;
use App\Configuracoesgerais;
use App\Helpers\Utils\NfUtil as Util;
use App\Processors\Nfe\Tributacao\NfeImpostoProcessor;
use App\Processors\Nfe\Tributacao\Base\ValidationBase;

/**
 * @author QTI
 */
class MakeXml extends ValidationBase
{

    /**
     * @var \NFePHP\NFe\Make
     */
    private $makeNfe;

    /**
     * @var \NFePHP\NFe\Tools
     */
    private $nfeTools;

    /**
     * @var Nfemitida
     */
    private $nf;

    /**
     * @var Empresaconfig
     */
    private $empresaConfig;

    /**
     * @var Empresa
     */
    private $empresa;

    /**
     * @var NfeImpostoProcessor
     */
    private $tributacao;

    /**
     * @var \stdClass
     */
    private $ide;

    /**
     * @var \stdClass
     */
    private $respTec;

    /**
     * @var string
     */
    private $ambiente;

    /**
     * @var boolean
     */
    public $isFinal;

    /**
     * @var NfProcessor
     */
    private $processor;

    /**
     * @var array Código da ANP que obriga icms monofasico
     */
    private $codAnpMonofasica = [
        "210203001",
        "210203003",
        "210203004",
        "210203005",
        "420102004",
        "420102005",
        "420105001",
        "420201001",
        "420201003",
        "420301002",
        "820101001",
        "820101012",
        "820101013",
        "820101033",
        "820101034",
        "320101001",
        "320101002",
        "320102001",
        "320102002",
        "320102003",
        "320102005",
        "320201001",
        "810102001",
        "810102004",
        "810102003",
        "320103001",
        "320103003",
        "320301002",
        "320301001",
        "320103002",
    ];


    /**
     * MakeXml constructor.
     * @param $nfemitida
     * @param $configJson
     * @param $processor
     * @throws Exception
     */
    public function __construct($nfemitida, $configJson, $processor)
    {

        $this->setNf($nfemitida);
        $this->processor = $processor;

        $this->ambiente = $nfemitida->nftipoambiente == 1 ? "producao" : "homologacao";

        $this->setDataEmpresa($nfemitida->empresa);

        $tools = new Tools();
        $this->nfeTools = $tools->mount($nfemitida, $configJson);

        $this->nfeTools->model($nfemitida->nfmodelo);
        $this->nfeTools->version(NFE_VERSION);

        $this->generateXml();

        $this->getXml();

        $this->montaNfe();

        $this->assignXml();
    }

    /**
     * @throws Exception
     */
    private function generateXml()
    {
        $config = Configuracoesgerais::first();
        if($this->nf->empresa->geraibscbs){
            $schema = 'PL_010_V1';
            $this->makeNfe = new Make($schema);
        } else {
            $this->makeNfe = new Make();
        }

        $this->tributacao = new NfeImpostoProcessor($this->nf);
        if ($this->nf->nfefinalidade == 2) {
            $this->tributacao->setComplementar();
        }
        $this->tributacao->calculaImposto(true);
        $nfemitida = $this->nf;

        $nfOperacao = Nfoperacao::findOrFail($nfemitida->nfoperacao_id);
        $nfmodelo = $nfemitida->nfmodelo;

        $stdTags = new TagMaker($nfemitida);

        //tags
        $stdTags->setStdInfNfe();
        $this->makeNfe->taginfNFe($stdTags->get('infNfe'));

        $stdTags->setStdIde($nfOperacao);
        $ide = $stdTags->get('ide');
        $this->ide = $ide;
        $this->makeNfe->tagide($ide);

        $stdTags->setStdRespTec($config);
        $respTec = $stdTags->get('respTec');
        $this->respTec = $respTec;
        $this->makeNfe->taginfRespTec($respTec);

        $stdTags->setStdEmit();
        $this->makeNfe->tagemit($stdTags->get('emit'));

        $stdTags->setStdEnderEmit();
        $this->makeNfe->tagenderEmit($stdTags->get('enderEmit'));

        if ($nfemitida->destcnpj != "" || $nfemitida->destcpf != "") {
            $stdTags->setStdDest();
            $this->makeNfe->tagdest($stdTags->get('dest'));
            $isCliConfig = (int) $this->empresaConfig->nfcecliente_id === (int) $nfemitida->cliente_id;
            if (!$isCliConfig) {
                $stdTags->setStdEnderDest();
                $this->makeNfe->tagenderDest($stdTags->get('enderDest'));
            }
        }

        $usingICMSUFDest = false;
        $usingComb = false;
        foreach ($this->tributacao->det as $det) {
            $stdTags->addStdCEST($det);
            if ($stdTags->get('CEST', $det->nItem, false) !== 'not') {
                $this->makeNfe->tagCEST($stdTags->get('CEST', $det->nItem));
            }

            $stdTags->addStdImposto($det, $nfOperacao);
            $this->makeNfe->tagimposto($stdTags->get('imposto', $det->nItem));

            /**
             * @var ImpostoDB $imposto
             */
            $imposto = $det->imposto;
            $objIcms = $imposto->get('icms');
            if ($objIcms->CST === "60" && Util::isCodAnpComb($det->prod->cProdANP) && strval($nfemitida->nfmodelo) === "55") {
                $stdTags->addStdICMSST($objIcms, $det->nItem);
                $this->makeNfe->tagICMSST($stdTags->get('ICMSST', $det->nItem));
            } else {
                $stdTags->addStdICMS($objIcms, $det->nItem);
                if ($stdTags->has('ICMS', $det->nItem)) {
                    $this->makeNfe->tagICMS($stdTags->get('ICMS', $det->nItem));
                } else {
                    $this->makeNfe->tagICMSSN($stdTags->get('ICMSSN', $det->nItem));
                }
            }

            $codbenef = $imposto->get("nfimposto")->get("codbenef");
            $stdTags->setCodBenef($codbenef);

            $objPis = $imposto->get('pis');
            $stdTags->addStdPIS($objPis, $det->nItem);

            $this->makeNfe->tagPIS($stdTags->get('PIS', $det->nItem));

            $objCofins = $imposto->get('cofins');
            $stdTags->addStdCOFINS($objCofins, $det->nItem);
            $this->makeNfe->tagCOFINS($stdTags->get('COFINS', $det->nItem));

            $indIeDest = $stdTags->get('dest') ? $stdTags->get('dest')->indIEDest : "9";
            $usingICMSUFDestItem = Util::generateICMSUFDest(strlen($objIcms->CST) > 0 ? $objIcms->CST : $objIcms->CSOSN, $ide, $det->prod->CFOP, $det->prod->cProdANP, $indIeDest);

            if ($usingICMSUFDestItem) {
                $icms = $det->imposto->get('icmsUFDest');
                $stdTags->addStdICMSUFDest($icms, $det->nItem);
                $icmsUf = $stdTags->get('ICMSUFDest', $det->nItem);
                if ($icmsUf->pICMSInter == 0) {
                    throw new Exception("Para vendas interestaduais com o grupo de partilha de ICMS, o percentual não pode ser igual a 0");
                }
                $this->makeNfe->tagICMSUFDest($icmsUf);
                $usingICMSUFDest = true;
            }

            if (isset($imposto->get('ipi')->CST)) {
                $objIPI = $imposto->get('ipi');
                $stdTags->addStdIPI($objIPI);
                $this->makeNfe->tagIPI($stdTags->get('IPI', $det->nItem));
            }
            
            $objIbsCbs = $imposto->get('ibscbs');
            $stdTags->addStdIBSCBS($objIbsCbs, $det->nItem);
            $imp = $imposto->get('nfimposto');
            //$this->makeNfe->tagIBSCBS($stdTags->get('IBSCBS', $det->nItem));
            if($imp->ind_gtribregular){
                $this->makeNfe->tagIBSCBS($stdTags->get('IBSCBS', $det->nItem));
            } elseif($imp->ind_gmonoret){
                $this->makeNfe->tagIBSCBS($stdTags->get('IBSCBS', $det->nItem));
                //dd($stdTags->get('IBSCBS', $det->nItem));
                $this->makeNfe->tagIBSCBSMono($stdTags->get('IBSCBS', $det->nItem));
            }  else{
                //por enquanto somente 410
                $this->makeNfe->tagIBSCBS($stdTags->get('IBSCBS', $det->nItem));
            }
            
            $stdTags->addStdProd($det->prod, $det->nItem);
            
            $this->makeNfe->tagprod($stdTags->get('prod', $det->nItem));

            $stdTags->addStdInfAdd($det->nItem);
            $this->makeNfe->taginfAdProd($stdTags->get('infAdProd', $det->nItem));

            if ($this->isCFOPComb($det->prod->CFOP)) {
                $stdTags->addStdComb($det->prod, $det->nItem);
                $tComb = $stdTags->get('comb', $det->nItem);
                if (is_null($tComb->cProdANP) || is_null($tComb->descANP)) {
                    $msg = "Informe os valores de combustível para o produto \"" . $det->prod->xProd . '"';
                    throw new Exception($msg);
                }
                $usingComb = true;
                $this->makeNfe->tagcomb($tComb);

                if (isset($det->nfItem->origComb)) {
                    foreach ($det->nfItem->origComb as $origem) {
                        $origem->item = $det->nItem;
                        $this->makeNfe->tagorigComb($origem);
                    }
                }
            }
        }

        $stdTags->setStdICMSTot($this->tributacao->total, $usingICMSUFDest, $nfOperacao);
        $this->makeNfe->tagICMSTot($stdTags->get('ICMSTot'));

        $stdTags->setStdTransp();
        $this->makeNfe->tagtransp($stdTags->get('transp'));

        if (strlen($nfemitida->chaveacessoref) > 0) {
            $stdTags->setStdRefNFe();
            $this->makeNfe->tagrefNFe($stdTags->get('refNFe'));
        }

        if ($this->nf->fretemodalidade != 9 && ($ide->idDest == 1 || $usingComb)) {
            $stdTags->setStdTransportadora();
            $this->makeNfe->tagtransporta($stdTags->get('transportadora'));

            if ($nfemitida->nfmodelo != 65 && $ide->idDest == 1) {
                $stdTags->setStdVeicTransp();
                $this->makeNfe->tagveicTransp($stdTags->get('veicTransp'));
            }
        }

        //Dados dos Volumes Transportados
        $this->nf->nfEmitidaVolume()->delete();
        $aVol = $this->getVols();
        $i = 0;
        foreach ($aVol as $vol) {
            $vol->pesoL = number_format($vol->pesoL, 3, '.', '');
            $vol->pesoB = number_format($vol->pesoB, 3, '.', '');
            $stdTags->addStdVol($vol, $i);
            if ($nfemitida->fretemodalidade != 9) {
                $this->makeNfe->tagvol($stdTags->get('vol', $i));
            }
            $this->processor->insertvol($vol);
            $i++;
        }

        //e também obrigatório para NFe (modelo 55) a partir do layout 4.00
        $condicaopagamento = Condicaopagamento::find($nfemitida->condicaopagamento_id);

        if ($nfmodelo == 55 && $this->nf->nfefinalidade != '2') {
            //dados da fatura
            $stdTags->setStdFat();
            $this->makeNfe->tagfat($stdTags->get('fat'));

            $parcelasfinanceiro = Nfemitidaparcela::where('nfemitida_id', $nfemitida->id)->get();
            if (!is_null($condicaopagamento) && $condicaopagamento->nfc_tpag == '14') {
                //dados das duplicatas (Pagamentos)
                foreach ($parcelasfinanceiro as $parc) {
                    $stdTags->addStdDup($parc);
                    $this->makeNfe->tagdup($stdTags->get('dup', $parc->referencia));
                }
            }
        }

        $stdTags->setStdPag();
        $this->makeNfe->tagpag($stdTags->get('pag'));
        $stdTags->setStdDetPag($condicaopagamento, $nfemitida);
        $this->makeNfe->tagdetPag($stdTags->get('detPag'));

        $stdTags->setStdInfAdic($this->tributacao->total, $nfOperacao, $stdTags->get('enderEmit')->UF);
        $this->makeNfe->taginfAdic($stdTags->get('infAdic'));

        // $stdTags->setStdInfRespTec($config);
        // $this->makeNfe->taginfRespTec($stdTags->get('infRespTec'));

        if ($stdTags->hasErrors())
            throw new Exception($stdTags->getErrors());
    }

    /**
     * @return array
     */
    private function getVols()
    {
        $aVol = [];
        $pJson = $this->nf->produtosjson;

        $produtos = json_decode($pJson);

        foreach ($produtos as $produto) {
            if (is_string($produto))
                $produto = decodeAssociativeArray($produto);

            //usado porque nas notas complementares, os produtos vem como um array não associativo
            //e na conversão para array as indexes ficam como string e gera problema
            $produto = Util::toArrayIdxInteger($produto);

            $objproduto = Produto::findOrFail($produto[4]);

            $achou = false;
            $novoaVol = [];
            foreach ($aVol as $vol) {
                if ($vol->especie == $objproduto->especie && $vol->marca == $objproduto->marca) {
                    $qtde = $produto[18];
                    $vol->quantidade = $vol->quantidade + ($qtde); //Quantidade de volumes transportados
                    $vol->pesoL = $vol->pesoL + $produto[19]; //Kg do tipo Int, mesmo que no manual diz que pode ter 3 digitos verificador...
                    $vol->pesoB = $vol->pesoB + $produto[20]; //...se colocar Float não vai passar na expressão regular do Schema. =\
                    $achou = true;
                }
                array_push($novoaVol, $vol);
            }
            $aVol = $novoaVol;

            if (!$achou) {
                $vol = (object) [];
                $vol->quantidade = $produto[18]; //Quantidade de volumes transportados
                $vol->especie = $objproduto->especie; //Espécie dos volumes transportados
                $vol->marca = $objproduto->marca; //Marca dos volumes transportados
                $vol->nVol = ''; //Numeração dos volume
                $vol->pesoL = $produto[19]; //Kg do tipo Int, mesmo que no manual diz que pode ter 3 digitos verificador...
                $vol->pesoB = $produto[20]; //...se colocar Float não vai passar na expressão regular do Schema. =\
                $vol->aLacres = '';
                $vol->produto_id = $objproduto->id; //Produto ID
                array_push($aVol, $vol);
            }
        }
        return $aVol;
    }

    /**
     * @return mixed
     */
    public function getTributacao()
    {
        return $this->tributacao;
    }

    /**
     * @return mixed
     */
    public function getIde()
    {
        return $this->ide;
    }

    /**
     * @return string
     */
    public function getChave()
    {
        return $this->makeNfe->getChave();
    }

    /**
     * @param $cfop
     * @return bool
     */
    private function isCFOPComb($cfop)
    {
        return Util::isCFOPComb($cfop);
    }


    /**
     * @param $empresa
     * @throws Exception
     */
    private function setDataEmpresa($empresa)
    {
        $this->empresa = $empresa;
        $this->empresaConfig = Empresaconfig::where('empresa_id', $empresa->id)->get()->first();

        if (is_null($this->empresaConfig))
            throw new Exception("Configurações da empresa não encontrada");
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function montaNfe()
    {
        $response = $this->getXml();

        Util::saveXML($this->empresa->cnpj, $response, $this->nf->nfmodelo, $this->ambiente, $this->makeNfe->getChave());

        return $response;
    }

    /**
     * @return string
     */
    public function getXml()
    {
        return $this->makeNfe->getXML();
    }

    /**
     * @param $nfemitida
     */
    public function setNf($nfemitida)
    {
        $this->nf = $nfemitida;
    }

    /**
     * @throws Exception
     */
    public function assignXml()
    {
        $signed = $this->nfeTools->signNFe(Util::getXmlFile($this->empresa->cnpj, $this->ambiente, $this->nf->nfmodelo, $this->makeNfe->getChave(), 'base'));

        Util::saveXML($this->empresa->cnpj, $signed, $this->nf->nfmodelo, $this->ambiente, $this->makeNfe->getChave(), "assinadas");

        $this->nf->update(["xmlassinado" => $signed]);
    }
}
