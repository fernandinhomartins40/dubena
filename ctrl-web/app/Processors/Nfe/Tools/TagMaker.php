<?php

namespace App\Processors\Nfe\Tools;

use App\Processors\Nfe\Tributacao\Base\IcmsBase;
use \stdClass;
//use App\Helpers\Utils\NfUtil;
use \Exception;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Nfe\Tributacao\Base\ValidationBase;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Gera as stdClass para as tags da API of TagXml
 *
 * @author Jeferson
 */
class TagMaker extends ValidationBase
{

    /**
     * @var bool
     */
    protected $debug = true;

    /**
     * @var \App\Nfemitida
     */
    protected $nf;

    /**
     * @var \App\Cliente
     */
    protected $cliente;

    /**
     * @var \App\Empresa
     */
    protected $empresa;

    /**
     * @var
     */
    protected $dest;

    /**
     * @var
     */
    protected $enderDest;

    /**
     * @var
     */
    protected $enderEmit;

    /**
     * @var
     */
    protected $emit;

    /**
     * @var
     */
    protected $ide;

    /**
     * @var
     */
    protected $respTec;

    /**
     * @var
     */
    protected $infNfe;

    /**
     * @var
     */
    protected $CEST;

    /**
     * @var
     */
    protected $imposto;

    /**
     * @var array
     */
    protected $ICMS;

    /**
     * @var array
     */
    protected $ICMSSN;

    /**
     * @var
     */
    protected $PIS;

    /**
     * @var
     */
    protected $IPI;

    /**
     * @var
     */
    protected $COFINS;

    /**
     * @var array
     */
    protected $ICMSPART;

    /**
     * @var
     */
    protected $cBenef;

    /**
     * @var
     */
    protected $prod;

    /**
     * @var
     */
    protected $comb;

    /**
     * @var
     */
    protected $ICMSST;

    /**
     * @var
     */
    protected $refNFe;

    /**
     * @var
     */
    protected $ICMSTot;

    /**
     * @var array
     */
    protected $dup;

    /**
     * @var
     */
    protected $infAdic;

    /**
     * @var
     */
    protected $infRespTec;

    /**
     * @var
     */
    protected $pag;

    /**
     * @var array
     */
    protected $vol;

    /**
     * @var
     */
    protected $fat;

    /**
     * @var
     */
    protected $transp;

    /**
     * @var
     */
    protected $detPag;

    /**
     * @var
     */
    protected $transportadora;

    /**
     * @var
     */
    protected $veicTransp;

    /**
     * @var string
     */
    protected $layoutVersion;

    /**
     * @var
     */
    protected $ICMSUFDest;

    /**
     * @var array
     */
    protected $infAdProd;

    /**
     * @var string
     */
    protected $errors = "";

    protected $IBSCBS;

    /**
     *
     * @param \App\Nfemitida $nf
     */
    public function __construct($nf)
    {
        $this->nf = $nf;
        $this->cliente = $nf->cliente;
        $this->empresa = $nf->empresa;
        $this->ICMS = [];
        $this->ICMSSN = [];
        $this->ICMSPART = [];
        $this->dup = [];
        $this->vol = [];
        $this->infAdProd = [];
        $this->layoutVersion = NFE_VERSION;
    }

    /**
     * @throws Exception
     */
    public function setStdEnderDest()
    {
        $enderDest = new stdClass();

        $enderDest->xLgr = $this->nf->destendereco;
        $enderDest->nro = $this->nf->destnumero;
        $enderDest->xCpl = $this->nf->destcomplemento;
        $enderDest->xBairro = $this->nf->destbairro;
        $enderDest->cMun = $this->nf->destcidadecodigoibge;
        $enderDest->xMun = $this->nf->destcidadenome;
        $enderDest->UF = $this->nf->destuf;
        $enderDest->CEP = $this->nf->destcep;
        $enderDest->cPais = $this->nf->destpaiscodigoibge;
        $enderDest->xPais = $this->nf->destpaisnome;
        $enderDest->fone = $this->nf->desttelefone;

        $this->validateRequired($enderDest, $this->requiredForEnderDest(), 'enderDest');

        $this->enderDest = $enderDest;
    }

    /**
     * @return stdClass
     */
    private function requiredForEnderDest()
    {
        $std = new stdClass();
        $std->xLgr = null;
        $std->nro = null;
        $std->xCpl = null;
        $std->xBairro = null;
        $std->cMun = null;
        $std->xMun = null;
        $std->UF = null;
        $std->CEP = null;
        $std->cPais = null;
        $std->xPais = null;
        $std->fone = null;

        return $std;
    }

    /**
     * @throws Exception
     */
    public function setStdDest()
    {
        $dest = new stdClass();

        $nfemitida = $this->nf;
        //destinatário
        $dest->CNPJ = is_null($nfemitida->destcnpj) ? "" : $nfemitida->destcnpj;
        $dest->CPF = is_null($nfemitida->destcpf) ? "" : $nfemitida->destcpf;
        $dest->xNome = $nfemitida->destrazaosocial;

        $dest->indIEDest = $nfemitida->destindicadorie;
        if (!$nfemitida->destindicadorie) {
            throw new Exception("Informe o Indicador IE no cadastro do cliente");
        }
        $dest->idEstrangeiro = $nfemitida->destIdEstrangeiro;
        $dest->IE = $nfemitida->destie;
        $dest->ISUF = $nfemitida->destISUF;
        $dest->IM = $nfemitida->destIM;
        $dest->email = $nfemitida->destemail;

        $this->validateRequired($dest, $this->requiredForDest(), 'emit');

        $this->dest = $dest;
    }

    /**
     * @return stdClass
     */
    private function requiredForDest()
    {
        $std = new stdClass();
        $std->xNome = null;
        $std->indIEDest = null;
        $std->IE = null;
        $std->ISUF = null;
        $std->IM = null;
        $std->email = null;
        $std->CNPJ = null; //indicar apenas um CNPJ ou CPF ou idEstrangeiro
        $std->CPF = null;
        $std->idEstrangeiro = null;

        return $std;
    }

    /**
     * @throws Exception
     */
    public function setStdEnderEmit()
    {
        $enderEmit = new stdClass();

        $enderEmit->xLgr = $this->nf->emitendereco;
        $enderEmit->nro = $this->nf->emitnumero;
        $enderEmit->xCpl = $this->nf->emitcomplemento;
        $enderEmit->xBairro = $this->nf->emitbairro;
        $enderEmit->cMun = $this->nf->emitcidadecodigoibge;
        $enderEmit->xMun = $this->nf->emitcidadenome;
        $enderEmit->UF = $this->nf->emituf;
        $enderEmit->CEP = str_pad($this->nf->emitcep, 8, "0", STR_PAD_LEFT);
        $enderEmit->cPais = str_pad($this->nf->emitpaiscodigoibge, 4, "0", STR_PAD_LEFT);
        $enderEmit->xPais = $this->nf->emitpaisnome;
        $enderEmit->fone = $this->nf->emittelefone;

        $this->validateRequired($enderEmit, $this->requiredForEnderEmit(), 'enderEmit');

        $this->enderEmit = $enderEmit;
    }

    /**
     * @return stdClass
     */
    private function requiredForEnderEmit()
    {
        $std = new stdClass();
        $std->xLgr = null;
        $std->nro = null;
        $std->xCpl = null;
        $std->xBairro = null;
        $std->cMun = null;
        $std->xMun = null;
        $std->UF = null;
        $std->CEP = null;
        $std->cPais = null;
        $std->xPais = null;
        $std->fone = null;

        return $std;
    }

    /**
     * @throws Exception
     */
    public function setStdEmit()
    {
        $emit = new stdClass();
        //Dados do emitente - (Importando dados do config.json)
        $emit->CNPJ = onlyNumbers($this->empresa->cnpj);
        $emit->CPF = ''; // Utilizado para CPF na nota
        $emit->xNome = $this->empresa->razao_social;
        $emit->xFant = $this->empresa->nome_fantasia;
        $emit->IE = $this->empresa->inscricao_estadual;
        $emit->CRT = $this->empresa->nfecrt;
        $emit->IEST = $this->empresa->inscricao_estadual_st;
        $emit->IM = $this->empresa->inscricao_municipal;
        $emit->CNAE = $emit->IM ? $this->empresa->cnae : null;

        $this->validateRequired($emit, $this->requiredForEmit(), 'emit');

        $this->emit = $emit;
    }

    /**
     * @return stdClass
     */
    private function requiredForEmit()
    {
        $std = new stdClass();
        $std->xNome = null;
        $std->xFant = null;
        $std->IE = null;
        $std->IEST = null;
        $std->IM = null;
        $std->CNAE = null;
        $std->CRT = null;
        $std->CNPJ = null;
        $std->CPF = null;

        return $std;
    }

    /**
     * @param $total
     * @param $usingICMSUFDest
     * @param $operacao
     * @throws Exception
     */
    public function setStdICMSTot($total, $usingICMSUFDest, $operacao)
    {
        if (!$usingICMSUFDest) {
            $total->vICMSUFRemet = '0.00';
            $total->vICMSUFDest = '0.00';
            $total->vFCPUFDest = '0.00';
        }
        if ($total->vICMS == '') {
            $total->vICMS = '0.00';
        }
        $total->vTotTrib = $operacao->deolhonoimposto ? $total->vTotTrib : '';

        foreach ($total as $key => $t) {
            if ($t === 0) {
                $total->{$key} = '0.00';
            }
        }

        $total->vIPIDevol = null;
        $this->validateRequired($total, $this->requiredForICMSTot(), 'ICMSTot');
        $this->ICMSTot = $total;
    }

    private function requiredForICMSTot()
    {
        $std = new stdClass();
        $std->vBC = null;
        $std->vICMS = null;
        $std->vICMSDeson = null;
        if ($this->layoutVersion === "4.00") {
            $std->vFCP = null; //incluso no layout 4.00
            $std->vFCPST = null; //incluso no layout 4.00
            $std->vFCPSTRet = null; //incluso no layout 4.00
            $std->vIPIDevol = null; //incluso no layout 4.00
        }
        $std->vBCST = null;
        $std->vST = null;
        $std->vProd = null;
        $std->vFrete = null;
        $std->vSeg = null;
        $std->vDesc = null;
        $std->vII = null;
        $std->vIPI = null;
        $std->vPIS = null;
        $std->vCOFINS = null;
        $std->vOutro = null;
        $std->vNF = null;
        $std->vTotTrib = null;

        return $std;
    }

    /**
     * 0=Por conta do emitente; 1=Por conta do destinatário/remetente; 2=Por conta de terceiros; 9=Sem Frete;
     * @throws Exception
     */
    public function setStdTransp()
    {
        $transp = new stdClass();
        $transp->modFrete = $this->nf->fretemodalidade;
        $this->validateRequired($transp, $this->requiredForTransp(), 'Transp');
        $this->transp = $transp;
    }

    /**
     * @return stdClass
     */
    private function requiredForTransp()
    {
        $std = new stdClass();
        $std->modFrete = null;
        return $std;
    }

    /**
     * @throws Exception
     */
    public function setStdRefNFe()
    {
        $refNFe = new stdClass();
        $refNFe->refNFe = $this->nf->chaveacessoref;
        $refNFe->cUF = null;
        $refNFe->AAMM = null;
        $refNFe->CNPJ = null;
        $refNFe->mod = null;
        $refNFe->serie = null;
        $refNFe->nNF = null;

        $this->validateRequired($refNFe, $this->requiredForRefNFe(), 'refNFe');
        $this->refNFe = $refNFe;
    }

    /**
     * @return stdClass
     */
    private function requiredForRefNFe()
    {
        $std = new stdClass();
        $std->cUF = null;
        $std->AAMM = null;
        $std->CNPJ = null;
        $std->mod = null;
        $std->serie = null;
        $std->nNF = null;

        return $std;
    }

    /**
     * @throws Exception
     */
    public function setStdVeicTransp()
    {
        $veicTransp = new stdClass();

        //dados dos veiculos de transporte
        $veicTransp->placa = $this->nf->freteplaca;
        $veicTransp->UF = strtoupper($this->nf->freteplacauf);
        $veicTransp->RNTC = '';

        $this->validateRequired($veicTransp, $this->requiredForVeicTransp(), 'veicTransp');

        $this->veicTransp = $veicTransp;
    }

    /**
     * @return stdClass
     */
    private function requiredForVeicTransp()
    {
        $std = new stdClass();
        $std->placa = null;
        $std->UF = null;
        $std->RNTC = null;

        return $std;
    }

    /**
     * @throws Exception
     */
    public function setStdFat()
    {
        $fat = new stdClass();

        $fat->nFat = $this->nf->financeiro_id ? $this->nf->financeiro_id : $this->nf->nfnumero;
        $fat->vOrig = sprintf('%0.2f', $this->nf->vnf + $this->nf->vdesc);

        $fat->vDesc = sprintf('%0.2f', $this->nf->vdesc);

        if ($this->nf->vdesc <= 0)
            $fat->vDesc = '0.00';

        $fat->vLiq = sprintf('%0.2f', floatval($fat->vOrig) - floatval($fat->vDesc));

        $this->validateRequired($fat, $this->requiredForFat(), "fat");

        $this->fat = $fat;
    }

    /**
     * @return stdClass
     */
    private function requiredForFat()
    {
        $std = new stdClass();
        $std->nFat = null;
        $std->vOrig = null;
        $std->vDesc = null;
        $std->vLiq = null;

        return $std;
    }

    /**
     * @param $parc
     * @throws Exception
     */
    public function addStdDup($parc)
    {
        $dup = new stdClass();
        $dup->nDup = $parc->referencia;
        $dup->dVenc = Carbon::parse($parc->datavencimento)->format('Y-m-d');
        $dup->vDup = sprintf('%0.2f', $parc->valororiginal);

        $this->validateRequired($dup, $this->requiredForDup(), "dup");

        $this->add('dup', $dup, $parc->referencia);
    }

    /**
     * @return stdClass
     */
    private function requiredForDup()
    {
        $std = new stdClass();
        $std->nDup = null;
        $std->dVenc = null;
        $std->vDup = null;

        return $std;
    }

    /**
     * Node referente as formas de pagamento OBRIGATÓRIO para NFCe a partir do layout 3.10
     * e também obrigatório para NFe (modelo 55) a partir do layout 4.00
     */
    public function setStdPag()
    {
        $pag = new stdClass();
        $pag->vTroco = null; //incluso no layout 4.00, obrigatório informar para NFCe (65)

        $this->pag = $pag;
    }

    /**
     * @param $condicaopagamento
     * @param $nf
     * @throws Exception
     */
    public function setStdDetPag($condicaopagamento, $nf)
    {
        $detPag = new stdClass();

        $hasPag = !is_null($condicaopagamento) && !isset($condicaopagamento->nfc_tpag);
        if ($hasPag) {
            $msg = "Preencha o campo \"Tipo Pagamento NF\" para a condição "
                . "\"$condicaopagamento->descricao\" na tela de \"Condicões de Pagamento\"";
            throw new \Exception($msg);
        }
        if ($this->ide->finNFe <= 2) {
            $detPag->tPag = is_null($condicaopagamento) || (!is_null($condicaopagamento) && !$condicaopagamento->nfc_tpag) ? '90' :     $condicaopagamento->nfc_tpag;
            if ($detPag->tPag == 14 && $nf->nfmodelo == 65) {
                $detPag->tPag = $nf->nfc_tpag;
            }
            if (!$detPag->tPag && $nf->nfmodelo == 65) {
                throw new Exception("Não foi possível verificar o tipo de pagamento para esta NF, " .
                    "verifique seu cadastro de Condições de Pagamento.");
            }

            // ? NT 2020.006 - Para Tipo de Pagamento 99 é obrigatório informar a descrição da condição de pagamento - Seq: YA02a-10
            if ($detPag->tPag == 99) {
                $detPag->xPag = $condicaopagamento->descricao;
            }

            $detPag->vPag = number_format($this->nf->vnf, 2, '.', '');
        } else {
            $detPag->tPag = '90';
            $detPag->vPag = '0.00';
        }
        $detPag->CNPJ = null;
        $detPag->tBand = null;
        $detPag->cAut = null;
        $detPag->tpIntegra = null;

        if (in_array($detPag->tPag, ['03', '04', '17'])) $detPag->tpIntegra = 2;

        $this->validateRequired($detPag, $this->requiredForDetPag(), 'detPag');

        $this->detPag = $detPag;
    }

    /**
     * @return stdClass
     */
    private function requiredForDetPag()
    {
        $std = new stdClass();
        $std->tPag = null;
        $std->vPag = null; //Obs: deve ser informado o valor pago pelo cliente
        $std->CNPJ = null;
        $std->tBand = null;
        $std->cAut = null;
        $std->tpIntegra = null; //incluso na NT 2015/002

        return $std;
    }

    /**
     * @param $total
     * @param $operacao
     * @param $uf
     * @throws Exception
     */
    public function setStdInfAdic($total, $operacao, $uf)
    {
        $infAdic = new stdClass();

        $infAdic->infAdFisco = $operacao->informacoesadicionalfisco;

        $infComplementar = "";

        if (strlen($this->nf->informacaocomplementar) > 0) {
            $infComplementar .= $this->nf->informacaocomplementar . ' - ';
        }
        if (count($this->get('ICMSSN'))) {
            $icms = collect($this->get('ICMSSN'));
            $vCred = $icms->sum('vCredICMSSN');
            if ($vCred) {
                $firstEl = $icms->where("pCredSN", "<>", null)->first();
                $pCred = $firstEl && $firstEl->pCredSN ? $firstEl->pCredSN : 0;
                $infComplementar .= " pCredSN: " . requestPercentualOracle($pCred)
                    . ", vCredICMSSN: " . requestNumeroDecimalOracle($vCred) . " - ";
            }
        }

        if ($this->get('ICMSTot')->vFCP > 0) {
            $infComplementar .= "FCP: " . requestNumeroDecimalOracle($this->get('ICMSTot')->vFCP) . ", ";
        }

        if ($this->get('ICMSTot')->vFCPST > 0) {
            $infComplementar .= "FCPST: " . requestNumeroDecimalOracle($this->get('ICMSTot')->vFCPST) . ", ";
        }

        if ($this->get('ICMSTot')->vFCPSTRet > 0) {
            $infComplementar .= "FCPSTRet: " . requestNumeroDecimalOracle($this->get('ICMSTot')->vFCPSTRet) . ", ";
        }

        // Calculo de carga tributária similar ao IBPT - Lei 12.741/12
        $federal = sprintf('%0.2f', $total->aliqnac);
        $estadual = sprintf('%0.2f', $total->aliqestadual);
        $municipal = sprintf('%0.2f', $total->aliqmunicipal);
        $totalT = sprintf('%0.2f', $total->vTotTribTotal);

        if ($operacao->deolhonoimposto) {
            $lei = \DB::table("produtoleiimpostos")->where("uf", $uf)->first();
            $chave = $lei ? $lei->chave : " ";
            $textoIBPT = " - Valor Aprox. Tributos R$ {$totalT} - {$federal} Federal, "
                . "{$estadual} Estadual e {$municipal} Municipal. Fonte: IBPT {$chave}.";
        } else {
            $textoIBPT = "";
        }

        $infAdic->infCpl = $infComplementar . "NF: " . $this->nf->nfnumero . $textoIBPT;

        $this->validateRequired($infAdic, $this->requiredForInfAdic(), 'infAdic');

        $this->infAdic = $infAdic;
    }

    /**
     *
     */
    public function setStdInfRespTec()
    {
        $infRespTec = new stdClass();


        //TODO está fixo, mas criar campos na config
        $infRespTec->CNPJ = "05547115000114";
        $infRespTec->xContato = "QTI";
        $infRespTec->email = "luizcarlos@qti.inf.br";
        $infRespTec->fone = "4236240210";

        $this->infRespTec = $infRespTec;
    }

    /**
     * @return stdClass
     */
    private function requiredForInfAdic()
    {
        $std = new stdClass();
        $std->infAdFisco = null;
        $std->infCpl = null;

        return $std;
    }

    /**
     * @throws Exception
     */
    public function setStdTransportadora()
    {
        $nfemitida = $this->nf;
        $transportadora = new stdClass();
        $transportadora->CNPJ = $nfemitida->fretecnpj;
        $transportadora->CPF = $nfemitida->fretecpf;
        $transportadora->xNome = $nfemitida->freterazaosocial;
        $transportadora->IE = $nfemitida->fretie;
        $transportadora->xEnder = $nfemitida->freteenderecocompl;
        $transportadora->xMun = $nfemitida->fretecidadenome;
        $transportadora->UF = $nfemitida->fretuf;

        $this->validateRequired($transportadora, $this->requiredForTransportadora(), 'transportadora');

        $this->transportadora = $transportadora;
    }

    /**
     * @return stdClass
     */
    private function requiredForTransportadora()
    {
        $std = new stdClass();
        $std->xNome = null;
        $std->IE = null;
        $std->xEnder = null;
        $std->xMun = null;
        $std->UF = null;
        $std->CNPJ = null;
        $std->CPF = null;

        return $std;
    }

    /**
     *
     */
    public function setStdInfNfe()
    {
        $infNfe = new stdClass();
        $infNfe->versao = $this->layoutVersion;
        $infNfe->Id = null;
        $infNfe->pk_nItem = null;
        $this->infNfe = $infNfe;
    }

    /**
     * @param $det
     * @throws Exception
     */
    public function addStdCEST($det)
    {
        $cest = new stdClass();
        $cest->item = $det->nItem;
        $cest->CEST = $det->cest;
        $cest->indEscala = null;
        $cest->CNPJFab = null;

        $this->validateRequired($cest, $this->requiredForCEST(), 'CEST');
        if ($det->cest) {
            $this->add('CEST', $cest, $cest->item);
        }
    }

    /**
     * @return stdClass
     */
    private function requiredForCEST()
    {
        $std = new stdClass();
        $std->item = null; //item da NFe
        $std->CEST = null;
        if ($this->layoutVersion === '4.00') {
            $std->indEscala = null; //incluido no layout 4.00
            $std->CNPJFab = null; //incluido no layout 4.00
        }
        return $std;
    }

    /**
     * @param IcmsBase $objICMS
     * @param $nItem
     */
    public function addStdICMSST(IcmsBase $objICMS, $nItem)
    {
        $std = new \stdClass();
        $std->item = $nItem; //item da NFe
        $std->orig = $objICMS->orig;
        $std->CST = '60';
        $std->vBCSTRet = !$objICMS->vBCSTRet ? "0.00" : $objICMS->vBCSTRet;
        $std->vICMSSTRet = !$objICMS->vICMSSTRet ? "0.00" : $objICMS->vICMSSTRet;
        $std->vBCSTDest = !$objICMS->vBCSTDest ? "0.00" : $objICMS->vBCSTDest;
        $std->vICMSSTDest = 0;

        $this->add("ICMSST", $std, $nItem);
    }

    /**
     * @param $objIcms
     * @param $nItem
     * @throws Exception
     */
    public function addStdICMS($objIcms, $nItem)
    {
        $icms = $this->toStdClass($this->toArray($objIcms));
        $icms->item = $nItem;

        //novos campos
        $icms->pFCP = $objIcms->pFCP;
        $icms->vFCP = $objIcms->vFCP;
        $icms->vBCFCP = $objIcms->vBCFCP;
        $icms->vBCFCPST = $objIcms->vBCFCPST;
        $icms->pFCPST = $objIcms->pFCPST;
        $icms->vFCPST = $objIcms->vFCPST;
        $icms->pST = $objIcms->pST;
        $icms->vICMSSTRet = $objIcms->vICMSSTRet;
        $icms->vBCFCPSTRet = $objIcms->vBCFCPSTRet;
        $icms->pFCPSTRet = $objIcms->pFCPSTRet;
        $icms->vFCPSTRet = $objIcms->vFCPSTRet;

        if (strlen($icms->CST) === 2) {
            $this->validateRequired($icms, $this->requiredForICMS(), 'ICMS');
            $this->add('ICMS', $icms, $icms->item);
        } else if (strlen($objIcms->CST) === 3) {
            $this->validateRequired($icms, $this->requiredForICMSSN(), 'ICMSSN');
            $this->add('ICMSSN', $icms, $icms->item);
        }
    }

    /**
     * @return stdClass
     */
    private function requiredForICMSSN()
    {
        $std = new stdClass();
        $std->item = null;
        $std->orig = null;
        $std->CSOSN = null;
        $std->pCredSN = null;
        $std->vCredICMSSN = null;
        $std->modBCST = null;
        $std->pMVAST = null;
        $std->pRedBCST = null;
        $std->vBCST = null;
        $std->pICMSST = null;
        $std->vICMSST = null;
        if ($this->layoutVersion === "4.00") {
            $std->vBCFCPST = null; //incluso no layout 4.00
            $std->pFCPST = null; //incluso no layout 4.00
            $std->vFCPST = null; //incluso no layout 4.00
            $std->vBCFCPSTRet = null; //incluso no layout 4.00
            $std->pFCPSTRet = null; //incluso no layout 4.00
            $std->vFCPSTRet = null; //incluso no layout 4.00
        }
        $std->vBCSTRet = null;
        $std->pST = null;
        $std->vICMSSTRet = null;
        $std->modBC = null;
        $std->vBC = null;
        $std->pRedBC = null;
        $std->pICMS = null;
        $std->vICMS = null;

        return $std;
    }

    /**
     * @return stdClass
     */
    private function requiredForICMS()
    {
        $std = new stdClass();
        $std->item = null; //item da NFe
        $std->orig = null;
        $std->CST = null;
        $std->modBC = null;
        $std->vBC = null;
        $std->pICMS = null;
        $std->vICMS = null;
        if ($this->layoutVersion === "4.00") {
            $std->vFCP = null;
            $std->vBCFCP = null;
            $std->pFCP = null;
            $std->vBCFCPST = null; //incluso no layout 4.00
            $std->pFCPST = null; //incluso no layout 4.00
            $std->vFCPST = null; //incluso no layout 4.00
            $std->vBCFCPSTRet = null; //incluso no layout 4.00
            $std->pFCPSTRet = null; //incluso no layout 4.00
            $std->vFCPSTRet = null; //incluso no layout 4.00
            $std->pST = null;
        }
        $std->modBCST = null;
        $std->pMVAST = null;
        $std->pRedBCST = null;
        $std->vBCST = null;
        $std->pICMSST = null;
        $std->vICMSST = null;
        $std->vICMSDeson = null;
        $std->motDesICMS = null;
        $std->pRedBC = null;
        $std->vICMSOp = null;
        $std->pDif = null;
        $std->vICMSDif = null;
        $std->vBCSTRet = null;
        $std->vICMSSTRet = null;

        return $std;
    }

    /**
     * @param $vol
     * @param $index
     * @throws Exception
     */
    public function addStdVol($vol, $index)
    {
        $stdVol = new stdClass();
        $stdVol->item = $index;
        $vol->pesoL = number_format($vol->pesoL, 3, '.', '');
        $vol->pesoB = number_format($vol->pesoB, 3, '.', '');
        $stdVol->qVol = intval($vol->quantidade); //Quantidade de volumes transportados
        $stdVol->esp = $vol->especie; //Espécie dos volumes transportados
        $stdVol->marca = $vol->marca; //Marca dos volumes transportados
        $stdVol->nVol = $vol->nVol; //Numeração dos volume
        $stdVol->pesoL = $vol->pesoL; //Kg do tipo Int, mesmo que no manual diz que pode ter 3 digitos verificador...
        $stdVol->pesoB = $vol->pesoB;  //...se colocar Float não vai passar na expressão regular do Schema. =\
        $stdVol->aLacres = $vol->aLacres;

        $this->validateRequired($stdVol, $this->requiredForVol(), 'vol');

        $this->add('vol', $stdVol, $index);
    }

    /**
     * @return stdClass
     */
    private function requiredForVol()
    {
        $std = new stdClass();
        $std->item = null;
        $std->qVol = null;
        $std->esp = null;
        $std->marca = null;
        $std->nVol = null;
        $std->pesoL = null;
        $std->pesoB = null;

        return $std;
    }

    /**
     * @param $pis
     * @param $nItem
     * @throws Exception
     */
    public function addStdPIS($pis, $nItem)
    {
        $pis->item = $nItem;

        $pis = $this->toStdClass((array) $pis);

        $this->validateRequired($pis, $this->requiredForPIS(), 'PIS');
        $this->add('PIS', $pis, $nItem);
    }

    /**
     * @return stdClass
     */
    private function requiredForPIS()
    {
        $std = new stdClass();
        $std->item = null;
        $std->CST = null;
        $std->vBC = null;
        $std->pPIS = null;
        $std->vPIS = null;
        $std->qBCProd = null;
        $std->vAliqProd = null;

        return $std;
    }

    /**
     * @param $cofins
     * @param $nItem
     * @throws Exception
     */
    public function addStdCOFINS($cofins, $nItem)
    {
        $cofins->item = $nItem;

        $cofins = $this->toStdClass((array) $cofins);

        $this->validateRequired($cofins, $this->requiredForCOFINS(), 'COFINS');

        $this->add('COFINS', $cofins, $nItem);
    }

    /**
     * @return stdClass
     */
    private function requiredForCOFINS()
    {
        $std = new stdClass();
        $std->item = null;
        $std->CST = null;
        $std->vBC = null;
        $std->pCOFINS = null;
        $std->vCOFINS = null;
        $std->qBCProd = null;
        $std->vAliqProd = null;

        return $std;
    }

    /**
     * @param $prod
     * @param $nItem
     * @throws Exception
     */
    public function addStdProd($prod, $nItem)
    {
        $prod = $this->toStdClass((array) $prod);

        $prod->item = $nItem;
        $prod->indEscala = null;
        $prod->CNPJFab = null;
        $prod->cBenef = $this->cBenef;

        $this->validateRequired($prod, $this->requiredForProd(), 'prod');

        $this->add('prod', $prod, $nItem);
    }


    public function setCodBenef($cBenef)
    {
        $this->cBenef = $cBenef;
    }

    /**
     * @return stdClass
     */
    private function requiredForProd()
    {
        $std = new stdClass();
        $std->item = null; //item da NFe
        $std->cProd = null;
        $std->cEAN = null;
        $std->xProd = null;
        $std->NCM = null;

        if ($this->layoutVersion === '4.00') {
            $std->indEscala = null;
            $std->CNPJFab = null;
            $std->cBenef = null; //incluido no layout 4.00 -> Obrigatorio
        }

        $std->EXTIPI = null;
        $std->CFOP = null;
        $std->uCom = null;
        $std->qCom = null;
        $std->vUnCom = null;
        $std->vProd = null;
        $std->cEANTrib = null;
        $std->uTrib = null;
        $std->qTrib = null;
        $std->vUnTrib = null;
        $std->vFrete = null;
        $std->vSeg = null;
        $std->vDesc = null;
        $std->vOutro = null;
        $std->indTot = null;
        $std->xPed = null;
        $std->nItemPed = null;
        $std->nFCI = null;

        return $std;
    }

    /**
     * pMixGN Para o grupo de combustível, foi incluído campo para identificar o percentual de mistura de GLP e GN no produto final que é comercializado.
     * codif - 3º param = O Codif é um sistema informatizado de controle destinado à
     * prévia autorização do diferimento do lançamento do imposto incidente
     * na operação interna ou interestadual, que destina Álcool
     * Etílico Anidro Combustível (AEAC) aos estabelecimentos distribuidores de combustíveis.
     * $qTemp = '',
     * $ufCons = '',
     * @param $prod
     * @param $nItem
     * @throws Exception
     */
    public function addStdComb($prod, $nItem)
    {
        $comb = new stdClass();
        $comb->item = $nItem;
        $comb->cProdANP = $prod->cProdANP;
        if ($this->layoutVersion === "3.10") {
            $comb->pMixGN = null; //removido no layout 4.00
        } else {
            $comb->descANP = $prod->descANP; //incluido no layout 4.00
            $comb->pGLP = $prod->pGLP; //incluido no layout 4.00
            $comb->pGNn = $prod->pGNn; //incluido no layout 4.00
            $comb->pGNi = $prod->pGNi; //incluido no layout 4.00
            $comb->vPart = $prod->vPart; //incluido no layout 4.00
        }

        $comb->CODIF = null;
        $comb->qTemp = null;
        $comb->UFCons = $this->nf->destuf;
        $comb->qBCProd = $prod->qBCProd;
        $comb->vAliqProd = $prod->vAliqProd;
        $comb->vCIDE = $prod->vCIDE;

        $this->validateRequired($comb, $this->requiredForComb(), 'comb');

        $this->add('comb', $comb, $nItem);
    }

    /**
     * @return stdClass
     */
    private function requiredForComb()
    {
        $std = new stdClass();
        $std->item = null; //item da NFe
        $std->cProdANP = null;

        if ($this->layoutVersion === '3.10') {
            $std->pMixGN = null; //removido no layout 4.00
        } else {
            $std->descANP = null; //incluido no layout 4.00
            $std->pGLP = null; //incluido no layout 4.00
            $std->pGNn = null; //incluido no layout 4.00
            $std->pGNi = null; //incluido no layout 4.00
            $std->vPart = null; //incluido no layout 4.00
        }

        $std->CODIF = null;
        $std->qTemp = null;
        $std->UFCons = null;
        $std->qBCProd = null;
        $std->vAliqProd = null;
        $std->vCIDE = null;

        return $std;
    }

    /**
     * @param $icms
     * @param $nItem
     * @throws Exception
     */
    public function addStdICMSUFDest($icms, $nItem)
    {
        $icms->item = $nItem;
        $this->validateRequired($icms, $this->requiredForICMSUFDest(), 'ICMSUFDest');
        $this->add('ICMSUFDest', $icms, $nItem);
    }

    /**
     * @return stdClass
     */
    private function requiredForICMSUFDest()
    {
        $std = new stdClass();
        $std->item = null;
        $std->vBCUFDest = null;
        if ($this->layoutVersion === "4.00") {
            $std->vBCFCPUFDest = null;
        }
        $std->pFCPUFDest = null;
        $std->pICMSUFDest = null;
        $std->pICMSInter = null;
        $std->pICMSInterPart = null;
        $std->vFCPUFDest = null;
        $std->vICMSUFDest = null;
        $std->vICMSUFRemet = null;

        return $std;
    }

    /**
     * @param $element
     * @param $value
     * @param $index
     */
    private function add($element, $value, $index)
    {
        $this->{$element}[$index] = $value;
    }

    /**
     * @param $object
     * @return array
     */
    private function toArray($object)
    {
        return get_object_vars($object);
    }

    /**
     * @param $array
     * @return object
     */
    private function toStdClass($array)
    {
        return (object) $array;
    }

    /**
     * @param $det
     * @param $operacao
     * @throws Exception
     */
    public function addStdImposto($det, $operacao)
    {
        $imposto = new stdClass();
        $imposto->item = $det->nItem;
        $imposto->vTotTrib = $operacao->deolhonoimposto ? $det->imposto->get('vTotTrib') : '';

        $this->validateRequired($imposto, $this->requiredForImposto(), 'imposto');

        $this->add('imposto', $imposto, $imposto->item);
    }

    /**
     * @return stdClass
     */
    private function requiredForImposto()
    {
        $std = new stdClass();
        $std->item = null;
        $std->vTotTrib = null;

        return $std;
    }

    /**
     * @param $item
     * @throws Exception
     */
    public function addStdInfAdd($item)
    {
        if ($this->has('ICMS', $item)) {
            $icms =  $this->get('ICMS', $item);
        } elseif ($this->has('ICMSST', $item)) {
            $icms = $this->get('ICMSST', $item);
        } else {
            $icms =  $this->get('ICMSSN', $item);
        }
        $std = new \stdClass();
        $std->item = $item;
        $inf = "NCM: " . $this->get('prod', $item)->NCM;

        if (isset($icms->vBCFCP) && $icms->vBCFCP > 0) {
            $inf .= ", vBCFCP: " . $icms->vBCFCP;
        }

        if (isset($icms->pFCP) && $icms->pFCP > 0) {
            $inf .= ", pFCP: " . $icms->pFCP;
        }

        if (isset($icms->vFCP) && $icms->vFCP > 0) {
            $inf .= ", vFCP: " . $icms->vFCP;
        }

        if (isset($icms->vBCFCPST) && $icms->vBCFCPST > 0) {
            $inf .= ", vBCFCPST: " . $icms->vBCFCPST;
        }

        if (isset($icms->pFCPST) && $icms->pFCPST > 0) {
            $inf .= ", pFCPST: " . $icms->pFCPST;
        }

        if (isset($icms->vFCPST) && $icms->vFCPST > 0) {
            $inf .= ", vFCPST: " . $icms->vFCPST;
        }

        if (isset($icms->vBCSTRet) && $icms->vBCSTRet > 0) {
            $inf .= ", vBCSTRet: " . $icms->vBCSTRet;
        }

        if (isset($icms->vICMSSTRet) && $icms->vICMSSTRet > 0) {
            $inf .= ", vICMSSTRet : " . $icms->vICMSSTRet;
        }

        if (isset($icms->vBCSTDest) && $icms->vBCSTDest > 0) {
            $inf .= ", vBCSTDest  : " . $icms->vBCSTDest;
        }

        if (isset($icms->vICMSSTDest) && $icms->vICMSSTDest > 0) {
            $inf .= ", vBCSTDest  : " . $icms->vICMSSTDest;
        }

        $std->infAdProd = $inf;

        $this->add('infAdProd', $std, $item);
    }

    /**
     * @param $ipi
     * @throws Exception
     */
    public function addStdIPI($ipi)
    {
        $ipi = $this->toStdClass((array) $ipi);
        $ipi->qSelo = null;
        $ipi->cSelo = null;
        $ipi->CNPJProd = null;
        $ipi->clEnq = null;
        $ipi->qUnid = null;
        $ipi->vUnid = null;
        $this->validateRequired($ipi, $this->requiredForIPI(), 'ipi');

        $this->add('IPI', $ipi, $ipi->item);
    }

    /**
     * @return stdClass
     */
    public function requiredForIPI()
    {
        $std = new stdClass();
        $std->item = null;
        $std->clEnq = null;
        $std->CNPJProd = null;
        $std->cSelo = null;
        $std->qSelo = null;
        $std->cEnq = null;
        $std->CST = null;
        $std->vIPI = null;
        $std->vBC = null;
        $std->pIPI = null;
        $std->qUnid = null;
        $std->vUnid = null;

        return $std;
    }

    /**
     * @param $operacao
     * @throws Exception
     */
    public function setStdIde($operacao)
    {
        $nfemitida = $this->nf;

        $ide = new stdClass();
        $ide->cDV = null;
        //Dados da NFe - infNfe

        // ? A partir da NT 2019.001 1.00 o cNF não pode ser igual o nNF e nem um numero igual ou sequencial (ex 0000000, 111111...)
        // $ide->cNF = str_pad($nfemitida->nfnumero, 8, "0", STR_PAD_LEFT);
        $ide->cNF = null;
        $ide->nNF = $nfemitida->nfnumero;

        if ($this->layoutVersion === '3.10') {
            $ide->indPag = $nfemitida->formapagamento;
        }

        //codigo numerico do estado
        $ide->cUF = $nfemitida->emitufcodigoibge;
        //'VENDA MERC SUJ ST RET ANT'; //natureza da operação
        $ide->natOp = replaceAccents($operacao->descricaofiscal);
        //modelo 55 ou 65
        $ide->mod = $nfemitida->nfmodelo;
        //serie da NFe
        $ide->serie = $nfemitida->nfserie;
        setTimezone();
        //“AAAA-MM-DDThh:mm:ssTZD” (UTC - Universal Coordinated Time).
        $ide->dhEmi = Carbon::parse($nfemitida->datahoraemissao)->format("Y-m-d\TH:i:sP");
        //Não informar este campo para a NFC-e.
        $ide->dhSaiEnt = $ide->mod == 65 ? '' : Carbon::parse($nfemitida->datahoraentradasaida)->format("Y-m-d\TH:i:sP");
        //0 - entrada e 1 - saída.
        $ide->tpNF = $operacao->tiponf;
        //1 = Operação interna; 2 = Operação interestadual; 3 = Operação com exterior.
        $ide->idDest = $nfemitida->iddest;

        $ide->indFinal = $nfemitida->indfinal;

        $ide->cMunFG = $nfemitida->emitcidadecodigoibge;
        $ide->tpImp = $this->getTpImp();
        $ide->tpEmis = $this->getTpEmis();

        //1=Produção; 2=Homologação
        $ide->tpAmb = $nfemitida->nftipoambiente;

        $ide->finNFe = $this->getFinalidade();
        $ide->indPres = $this->getPresencacomprador();
        $ide->procEmi = $this->getProcEmi();

        // ? Nota Técnica 2020.006 - v 1.40 Exige que caso indPres seja (2, 3, 4 ou 9), tpNF = 1 (Saída) e finNFe = 1 (normal)
        // ? seja informado o indIntermed - 0 para operação sem intermediador (site ou plataforma própria)
        // ? 1 - para operação com intermediario (site ou plataforma de terceiros (marketplace))
        if ($this->needsIndItermed($ide->tpNF)) {
            $ide->indIntermed = 0;
        }

        //versão do aplicativo emissor
        $ide->verProc = '1.1.5';

        $dhCont = "";
        $xJust = "";
        if ($nfemitida->empresa->contingenciaemissao) {
            //entrada em contingência AAAA-MM-DDThh:mm:ssTZD
            $dhCont = Carbon::parse($nfemitida->empresa->datahoracontingencia)->format("Y-m-d\TH:i:sP");
            //Justificativa da entrada em contingência
            $xJust = $nfemitida->empresa->contingenciajustificativa;
        }
        $ide->xJust = $xJust;
        $ide->dhCont = $dhCont;

        $this->validateRequired($ide, $this->requiredForIDE(), 'ide');

        $this->ide = $ide;
    }

    /**
     * @return stdClass
     */
    public function setStdRespTec($config)
    {
        $cnpj = preg_replace("/[^0-9]/", "", $config->rtcnpj);
        $fone = preg_replace("/[^0-9]/", "", $config->rttelefone);

        $std = new stdClass();
        $std->CNPJ = empty($cnpj) ? null : $cnpj;
        $std->xContato = $config->rtcontato;
        $std->email = $config->rtemail;
        $std->fone = empty($fone) ? null : $fone;
        $std->CSRT = $config->rtcsrt;
        $std->idCSRT = $config->rtidcsrt;

        $this->respTec = $std;
    }

    /**
     * @return stdClass
     */
    private function requiredForIDE()
    {
        $std = new stdClass();
        $std->cUF = null;
        $std->cNF = null;
        $std->natOp = null;
        $std->mod = null;
        $std->serie = null;
        $std->nNF = null;
        $std->dhEmi = null;
        $std->dhSaiEnt = null;
        $std->tpNF = null;
        $std->idDest = null;
        $std->cMunFG = null;
        $std->tpImp = null;
        $std->tpEmis = null;
        $std->cDV = null;
        $std->tpAmb = null;
        $std->finNFe = null;
        $std->indFinal = null;
        $std->indPres = null;
        $std->procEmi = null;
        $std->verProc = null;
        $std->dhCont = null;
        $std->xJust = null;

        return $std;
    }

    /**
     * 1=Emissão normal (não em contingência);
     * 2=Contingência FS-IA, com impressão do DANFE em formulário de segurança;
     * 3=Contingência SCAN (Sistema de Contingência do Ambiente Nacional);
     * 4=Contingência DPEC (Declaração Prévia da Emissão em Contingência);
     * 5=Contingência FS-DA, com impressão do DANFE em formulário de segurança;
     * 6=Contingência SVC-AN (SEFAZ Virtual de Contingência do AN);
     * 7=Contingência SVC-RS (SEFAZ Virtual de Contingência do RS);
     * 9=Contingência off-line da NFC-e (as demais opções de contingência são válidas também para a NFC-e);
     * Nota: Para a NFC-e somente estão disponíveis e são válidas as opções de contingência 5 e 9.
     * @return mixed
     */
    private function getTpEmis()
    {
        return $this->nf->nfmodelo == 55 ? $this->empresa->nfetipoemissao : $this->empresa->nfcetipoemissao;
    }

    /**
     * 0=Emissão de NF-e com aplicativo do contribuinte;
     * 1=Emissão de NF-e avulsa pelo Fisco;
     * 2=Emissão de NF-e avulsa, pelo contribuinte com seu certificado digital, através do site do Fisco;
     * 3=Emissão NF-e pelo contribuinte com aplicativo fornecido pelo Fisco.
     * @return mixed
     */
    private function getProcEmi()
    {
        return $this->nf->nfprocessoemissao;
    }

    /**
     * 0=Não se aplica (por exemplo, Nota Fiscal complementar ou de ajuste);
     * 1=Operação presencial;
     * 2=Operação não presencial, pela Internet;
     * 3=Operação não presencial, Teleatendimento;
     * 4=NFC-e em operação com entrega a domicílio;
     * 9=Operação não presencial, outros.
     * @return mixed
     */
    private function getPresencacomprador()
    {
        return $this->nf->presencacomprador;
    }

    /**
     * 0=Sem geração de DANFE; 1=DANFE normal, Retrato; 2=DANFE normal, Paisagem;
     * 3=DANFE Simplificado; 4=DANFE NFC-e; 5=DANFE NFC-e em mensagem eletrônica
     * o envio de mensagem eletrônica pode ser feita de forma simultânea com a impressão do DANFE;
     * usar o tpImp=5 quando esta for a única forma de disponibilização do DANFE).
     * @return string
     */
    private function getTpImp()
    {
        return $this->nf->nfmodelo == 55 ? '1' : '4';
    }

    /**
     * 1=NF-e normal; 2=NF-e complementar; 3=NF-e de ajuste; 4=Devolução/Retorno.
     * @return mixed
     */
    private function getFinalidade()
    {
        return $this->nf->nfefinalidade;
    }

    private function needsIndItermed($tpNF)
    {
        $indPressReq = [2, 3, 4, 9];
        $tpNFReq = 1;
        $finNF = 1;

        $ruleIndPress = in_array($this->getPresencacomprador(), $indPressReq);
        $ruleTpNF = $tpNF == $tpNFReq;
        $ruleFinNF = $this->getFinalidade() == $finNF;

        return $ruleIndPress && $ruleTpNF && $ruleFinNF;
    }

    /**
     * @param $name
     * @param null $index
     * @param bool $ex
     * @return string|StdClass|array
     * @throws Exception
     */
    public function get($name, $index = null, $ex = true)
    {
        $value = $this->getSelf($name, $index);

        if ($value === 'not' && $ex) {
            throw new Exception("Index $index não encontrada para o atributo $name");
        }

        return $value;
    }

    /**
     * @param $name
     * @param null $index
     * @return bool
     */
    public function has($name, $index = null)
    {
        $value = $this->getSelf($name, $index);
        return $value !== 'not';
    }

    /**
     * @param $name
     * @param null $index
     * @return string
     */
    private function getSelf($name, $index = null)
    {
        if (is_null($index)) {
            return $this->{$name};
        } else {
            if (isset($this->{$name}[$index])) {
                return $this->{$name}[$index];
            } else {
                return 'not';
            }
        }
    }

    /**
     * @param $obj
     * @param $rules
     * @param $tag
     */
    protected function validate($obj, $rules, $tag)
    {
        foreach ($rules as $key => $rule) {
            if (!property_exists($obj, $key)) {
                $this->addError("Tag $key não informada para a tag pai $tag.");
            } else {
                if (strlen($obj->{$key}) === 0 && $rule) {
                    $this->addError("Tag $key é obrigatória para a tag pai $tag.");
                }
                $isZero = strval($obj->{$key}) === '0.0' || strval($obj->{$key}) === '0' || strval($obj->{$key}) === '0.00';
                if ($isZero && !is_null($obj->{$key}) && !$rule) {
                    $this->addError("Tag $key não pode ir com valor 0 para a tag pai $tag.");
                }
            }
        }
    }

    /**
     * @param $msg
     */
    private function addError($msg)
    {
        $this->errors .= $msg . "\n";
    }

    /**
     * @return string
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * @param $std
     * @param $required
     * @param $tagName
     * @throws Exception
     */
    private function validateRequired($std, $required, $tagName)
    {
        if (!$required instanceof stdClass) {
            throw new Exception("Definição de obrigatórios inválida para a tag $tagName");
        }
        foreach ($required as $key => $value) {
            if (!property_exists($std, $key))
                throw new Exception("$key é esperado na tag $tagName");
        }
    }

    public function addStdIBSCBS($objIbs, $nItem)
    {
        $ibs = $this->toStdClass($this->toArray($objIbs));
        $ibs->item = $nItem;

        //$this->validateRequired($pis, $this->requiredForPIS(), 'PIS');
        $this->add('IBSCBS', $ibs, $nItem);
    }
}
