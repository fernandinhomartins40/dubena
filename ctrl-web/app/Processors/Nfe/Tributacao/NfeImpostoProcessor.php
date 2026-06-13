<?php

namespace App\Processors\Nfe\Tributacao;

use App\Empresa;
use App\Nfoperacao;
use App\Nfrecebida;
use App\User;
use Auth;
use DB;
use Exception;
use App\Produto;
use App\Nfemitida;
use App\Nfimposto;
use App\Nfemitidaitem;
use App\Nfrecebidaitem;
use App\Processors\Nfe\NfProcessor;
use App\Helpers\Utils\NfUtil as Util;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Processors\Nfe\Tributacao\Base\ValidationBase;
use Session;
use stdClass;

class NfeImpostoProcessor extends ValidationBase
{

    /**
     * @var
     */
    public $xml;

    /**
     * @var Collection
     */
    public $det;

    /**
     * @var stdClass
     */
    public $total;

    /**
     * @var int
     */
    public $nItems;

    /**
     * @var Nfemitida|Nfrecebida|null
     */
    private $nf;

    /**
     * @var Nfimposto|Collection|Model|DB|array
     */
    private $atualImposto;

    /**
     * @var Nfoperacao|Collection|Model|DB|array
     */
    private $atualOperacao;

    /**
     * @var string
     */
    private $produtosJson;

    /**
     * @var string
     */
    private $tipoNf;

    /**
     * @var Empresa|Collection|Model|DB|array
     */
    private $emitente;

    /**
     * @var Empresa|Collection|Model|DB|array
     */
    private $destinatario;

    /**
     * @var array
     */
    private $produtos;

    /**
     * @var Empresa|Collection|Model|DB
     */
    private $empresa;

    /**
     * @var  Nfoperacao|Collection|Model|DB
     */
    private $operacoesProd;

    /**
     * @var Collection
     */
    private $gruposFiscaisProd;

    /**
     * @var Collection
     */
    private $idsProd;

    /**
     * @var Collection
     */
    private $idsSetoresProd;

    /**
     * @var Nfimposto|Collection|Model|DB
     */
    private $nfImpostos;

    /**
     * @var Nfoperacao|Collection|Model|DB
     */
    private $nfOperacoes;

    /**
     * @var DB|Model|Produto|Collection
     */
    private $produtosDb;

    /**
     * @var Nfemitidaitem|Nfrecebidaitem
     */
    private $nfItems;

    /**
     * @var Collection
     */
    private $codNcmProd;

    /**
     * @var Collection
     */
    private $prodLeiImposto;

    /**
     * @var Collection
     */
    private $imposto;

    /**
     * @var bool
     */
    private $isComplementar = false;

    /**
     * @var bool
     */
    private $useICMSDeson = false;

    /**
     * @var
     */
    private $vDescUntTotal;

    /**
     * @var User|Collection
     */
    private $user;

    /**
     * contains a json with original xml of import
     * @var string
     */
    private $originalContent;

    /**
     * contains a json with original tag content from xml of import
     * @var stdClass
     */
    private $tagCombXml;

    /**
     * NfImpostoProcessor constructor.
     * @param null|Nfemitida|Nfrecebida $nf
     * @param null|array $pars
     * @throws Exception
     */
    public function __construct($nf = null, $pars = null)
    {
        $this->setData();

        if (is_null($nf)) {
            $this->tipoNf = $pars['tiponf'];
            $this->originalContent = array_key_exists("originalContent", $pars) ? json_decode($pars["originalContent"]) : [];
            $this->tagCombXml = array_key_exists("tagCombXml", $pars) ? json_decode($pars["tagCombXml"]) : (object) [];
            $this->setEmitDest($pars);
            $this->setEmpresa(true);
            $this->setProdutosInfo($pars);
            $this->setImpostos();
            $this->setOperacoes();
            try {
                $pars["idDest"] = isset($pars["idDest"]) ? $pars["idDest"] : $pars["iddest"];
            } catch (Exception $e) {
                throw new Exception(
                    "Não foi possível verificar o destino da operação, tente novamente. " .
                        "Se o problema persistir entre em contato com o suporte"
                );
            }
            $this->nf = (object) $pars;
            $isComplementar = isset($pars['complementar']) && $pars['complementar'];
            if ($isComplementar) {
                $this->setComplementar();
            }

            foreach ($this->nf as $key => $value) {
                $this->nf->{$key} = strpos($value, "R$") !== false ? insertNumeroDecimalOracle($value) : $value;
            }
        } else {
            $this->tipoNf = $nf instanceof Nfemitida || $nf->tipolancamento === "0" ? "E" : "R";
            $this->nf = $nf;

            $this->nf->load(['empresa', 'nfOperacao', 'cliente', $this->nf instanceof Nfemitida ? 'nfEmitidaItem' : 'nfRecebidaItem']);

            if (isset($this->nf->nfefinalidade) && strval($this->nf->nfefinalidade) === '2') {
                $this->setComplementar();
            }

            $this->setEmitDest();
            $this->setEmpresa();
            $this->setProdutosInfo();
            $this->setImpostos();
            $this->setOperacoes();
        }
    }

    /**
     * @param bool $toXml
     * @param int $situacao
     * @return array|bool
     * @throws Exception
     */
    public function calculaImposto($toXml = true, $situacao = 100)
    {
        $nItem = 1;
        $estoquesetorhistoricos = [];
        $processor = null;
        $optionsHistoricoGeneric = null;
        $tiponf = "";
        if ($toXml) {
            $tiponf = $this->nf instanceof Nfemitida ? "emitida" : "recebida";
            $this->user = \Auth::user();
            $processor = new NfProcessor($this->nf);

            $optionsHistoricoGeneric = (object) [
                'datahora'            => $this->nf->datahoraentradasaida,
                'datahoracompetencia' => $this->nf->datahoraentradasaida,
                'entidade'            => "Nf" . $tiponf,
                'entidade_id'         => $this->nf->id,
                'empresa_id'          => $this->nf->empresa_id,
                'grupo_id'            => $this->nf->grupo_id,
                'user_id'             => $this->user->id
            ];
        }
        $this->vDescUntTotal = 0;
        $this->nItems = count($this->produtos);

        foreach ($this->produtos as $produto) {
            if (is_string($produto)) {
                $produto = decodeAssociativeArray($produto);
            }

            $this->atualImposto = $this->getImposto($produto);
            $this->atualOperacao = $this->getOperacaoById($produto[0]);
            $prodDB = $this->getProdutoById($produto[4]);
            $prodDB->load("origens");

            $options = $this->getOptions($produto, $nItem++, $prodDB, $this->originalContent);

            $calc = new CalculoImposto($options);
            
            $this->useICMSDeson = $this->useICMSDeson(false, $calc->usingDeson);

            if ($toXml) {
                $item = $this->insertProd($produto, $prodDB, $calc);
                // 0-Nada 1-Entrada 2-Saída
                if ($this->atualOperacao->movimentaestoque > 0) {
                    $mov = $processor->gerarObjMovimentacao([
                        'optionsHistoricoGeneric' => $optionsHistoricoGeneric,
                        'tiponf'                  => $tiponf,
                        'objoperacao'             => $this->atualOperacao,
                        'objproduto'              => $prodDB,
                        'item'                    => $item,
                        'produto_id'              => $prodDB->id,
                        'setor_id'                => $item->setor_id,
                        'nf'                      => $this->nf,
                        'produtoqtde'             => $item->qcom,
                    ]);
                    $estoquesetorhistoricos = array_merge($estoquesetorhistoricos, $mov);
                }
            }
            $this->det->push($calc->det);
            $this->sumTotal($calc);
        }

        if ($toXml && $situacao === 100) {
            $processor->movimentarEstoque($estoquesetorhistoricos);
        }

        if ($this->useICMSDeson(true) && is_null($this->total->vICMSDeson)) {
            $this->total->vICMSDeson = '0.00';
        }
        $this->calcVNF();

        if (!$toXml) {
            return $this->formatDataToView();
        } elseif (!$this->isComplementar) {
            return $this->updateNf();
        } else {
            return $this->updateNf();
        }
    }

    /**
     * @param $produto
     * @param $prodDB
     * @param CalculoImposto $calc
     * @return Nfemitidaitem|Nfrecebidaitem
     */
    private function insertProd($produto, $prodDB, &$calc)
    {
        if ($this->tipoNf === 'E' && !isset($this->nf->tipolancamento)) {
            $item = new Nfemitidaitem();
            $item->nfemitida_id = $this->nf->id;
            $item->customedio = is_null($prodDB->customedio) ? 0 : $prodDB->customedio;
        } else {
            $item = new Nfrecebidaitem();
            $item->nfrecebida_id = $this->nf->id;
        }

        $item->grupo_id = $this->nf->grupo_id;
        $item->empresa_id = $this->nf->empresa_id;
        $item->nfimposto_id = $this->atualImposto->id;
        $item->nfoperacao_id = $this->atualOperacao->id;
        $item->setor_id = $produto[2];
        $item->nitemped = $calc->det->nItem;
        $item->cest = $calc->det->cest;
        $item->indTot = 1;

        $this->setTagProdItem($item, $calc->get('tagProd'));
        $this->setPISItem($item, $calc->det->imposto['pis']);
        $this->setCOFINSItem($item, $calc->det->imposto['cofins']);
        $this->setIPIItem($item, $calc->det->imposto['ipi']);

        $item->codigolote = ' ';

        $item->qvol = $produto[18];
        $item->pesol = $produto[19];
        $item->pesob = $produto[20];

        $this->setICMSItem($item, $calc->det->imposto['icms']);
        $this->setIBSCBSItem($item, $calc->det->imposto['ibscbs']);

        $item->movimentaestoque = $this->atualOperacao->movimentaestoque;

        $item->save();

        // * Origem de combustivel podem ser multiplos
        if ($prodDB->origens->isNotEmpty())
            $this->setOrigComb($item, $prodDB);

        $calc->det->setNfItem($item);

        return $item;
    }

    /**
     * @param $item
     * @param $tagProd
     */
    private function setTagProdItem(&$item, $tagProd)
    {
        $item->pgni = !$tagProd->pGNi ? 0 : $tagProd->pGNi;
        $item->pgnn = !$tagProd->pGNn ? 0 : $tagProd->pGNn;
        $item->pglp = !$tagProd->pGLP ? 0 : $tagProd->pGLP;
        $item->vpart = !$tagProd->vPart ? 0 : $tagProd->vPart;
        $item->cprod = $tagProd->cProd;
        $item->cean = $tagProd->cEAN;
        $item->xprod = $tagProd->xProd;
        $item->ncm = is_null($tagProd->NCM) ? " " : $tagProd->NCM;
        $item->cfop = $tagProd->CFOP;
        $item->ucom = $tagProd->uCom;
        $item->qcom = $tagProd->qCom;
        $item->vuncom = $tagProd->vUnCom;
        $item->vprod = $tagProd->vProd;
        $item->ceantrib = $tagProd->cEANTrib;
        $item->utrib = $tagProd->uTrib;
        $item->qtrib = $tagProd->qTrib;
        $item->vuntrib = $tagProd->vUnTrib;
        $item->qestoque = $tagProd->qCom;
        $item->vdesc = $tagProd->vDesc;
        $item->voutro = $tagProd->vOutro;
        $item->vseg = $tagProd->vSeg;
        $item->vfrete = $tagProd->vFrete;
        $item->cprodanp = $tagProd->cProdANP;
        $item->qbcprod = $tagProd->qBCProd;
        $item->valiqprod = $tagProd->vAliqProd;
        $item->vcide = $tagProd->vCIDE;
    }

    /**
     * @param $item
     * @param $objIPI
     */
    private function setIPIItem(&$item, $objIPI)
    {
        $item->tagipi = Util::getTagIPI($objIPI->CST);
        $item->cstipi = $objIPI->CST;
        $item->vbcipi = $objIPI->vBC;
        $item->vipi = $objIPI->vIPI;
        $item->pipi = $objIPI->pIPI;
        $item->pipi = $objIPI->pIPI;
    }

    /**
     * @param $item
     * @param $objPis
     */
    private function setPISItem(&$item, $objPis)
    {
        $item->tagpis = Util::getTagPIS($objPis->CST);
        $item->cstpis = $objPis->CST;
        $item->vbcpis = $objPis->vBC;
        $item->vpis = $objPis->vPIS;
        $item->ppis = $objPis->pPIS;
    }

    /**
     * @param $item
     * @param $objCofins
     */
    private function setCOFINSItem(&$item, $objCofins)
    {
        $item->tagcofins = Util::getTagCofins($objCofins->CST);
        $item->cstcofins = $objCofins->CST;
        $item->vbccofins = $objCofins->vBC;
        $item->vcofins = $objCofins->vCOFINS;
        $item->pcofins = $objCofins->pCOFINS;
    }

    /**
     * @param $item
     * @param $objIcms
     */
    private function setICMSItem(&$item, $objIcms)
    {
        $item->tagicms = Util::getTagICMS($objIcms->CST);
        $item->orig = $objIcms->orig;
        $item->cst = $objIcms->CST;
        $item->vbc = $objIcms->vBC;
        $item->picms = $objIcms->pICMS;
        $item->vicms = $objIcms->vICMS;
        $item->modbc = $objIcms->modBC;
        $item->vbcstret = $objIcms->vBCSTRet;
        $item->vicmsstret = $objIcms->vICMSSTRet;
        $item->picmsst = $objIcms->pICMSST;
        $item->modbcst = $objIcms->modBCST;
        $item->pmvast = $objIcms->pMVAST;
        $item->predbcst = $objIcms->pRedBCST;
        $item->vbcst = $objIcms->vBCST;
        $item->vicmsst = $objIcms->vICMSST;
        $item->predbc = $objIcms->pRedBC;
        $item->vicmsdeson = $objIcms->vICMSDeson;
        $item->motdesicms = $objIcms->motDesICMS;
        $item->vicmsdif = $objIcms->vICMSDif;
        $item->pdif = $objIcms->pDif;
        $item->vicmsop = $objIcms->vICMSOp;
        $item->pbcop = $objIcms->pBCOp;
        $item->vbcstdest = $objIcms->vBCSTDest;
        $item->pcredsn = $objIcms->pCredSN;
        $item->vcredicmssn = $objIcms->vCredICMSSN;
        $item->pfcp = $objIcms->pFCP;
        $item->vfcp = $objIcms->vFCP;
        $item->vbcfcp = $objIcms->vBCFCP;
        $item->vbcfcpst = $objIcms->vBCFCPST;
        $item->pfcpst = $objIcms->pFCPST;
        $item->vfcpst = $objIcms->vFCPST;
        $item->pst = $objIcms->pST;
        $item->vbcfcpstret = $objIcms->vBCFCPSTRet;
        $item->pfcpstret = $objIcms->pFCPSTRet;
        $item->vfcpstret = $objIcms->vFCPSTRet;
    }

    /**
     * @param $produto
     * @param $nItem
     * @param $prodDB
     * @param $originalContent
     * @return array
     */
    private function getOptions($produto, $nItem, $prodDB, $originalContent)
    {
        $vProdT = $this->isComplementar ? $this->sumVProdT() : 0;

        return [
            'produto'           => $produto,
            'emituf'            => $this->emitente->uf,
            'destuf'            => $this->destinatario->uf,
            'nItem'             => $nItem,
            'idDest'            => isset($this->nf->idDest) ? $this->nf->idDest : $this->nf->iddest,
            'produtoDB'         => $prodDB,
            'nfimposto'         => $this->atualImposto,
            'isConsumidorFinal' => $this->nf->indfinal == 1,
            'nfoperacao'        => $this->atualOperacao,
            'nf'                => $this->nf,
            'nItems'            => $this->nItems,
            'tipoNf'            => $this->tipoNf,
            'destinatario'      => $this->destinatario,
            'prodLeiImposto'    => $this->getProdLeiImpostoByNcm($produto[11]),
            'empresa'           => $this->empresa,
            'total'             => $this->total,
            'vDescUntTotal'     => $this->vDescUntTotal,
            'isComplementar'    => $this->isComplementar,
            'vProdComplementar' => $vProdT,
            'isImporting'       => !is_null($originalContent) && count($originalContent) > 0,
            'originalContent'   => $originalContent
        ];
    }

    /**
     * @return float|int
     */
    private function sumVProdT()
    {
        $v = 0;
        foreach ($this->produtos as $p) {
            if (is_string($p))
                $p = decodeAssociativeArray($p);

            $v += insertNumeroDecimalOracle($p[6]);
        }
        return $v;
    }

    /**
     * @return array
     */
    private function formatDataToView()
    {

        $t = $this->total;
        $data = (object) [];
        $impFloat = (object) [];
        foreach ($t as $key => $value) {
            $key = strtolower($key);
            $data->{$key} = requestNumeroDecimalOracle($value);
            $impFloat->{$key} = $value;
        }

        return [$data, $impFloat];
    }

    /**
     *
     */
    private function calcVNF()
    {
        $t = $this->total;
        // vProd - vDesc - vICMSDeson + vST + vFCPST + vFrete + vSeg + vOutro + vII + vIPI + vServ
        $vNF = $t->vProd -
            $t->vDesc -
            $t->vICMSDeson +
            $t->vST +
            $t->vFCPST +
            $t->vFrete +
            $t->vSeg +
            $t->vOutro +
            $t->vIPI;
        $t->vNF = sprintf('%0.2f', $vNF);

        $this->total = $t;
    }

    /**
     * seta os dados básicos da classe
     */
    public function setData()
    {
        $this->det = collect([]);

        $this->total = (object) [
            'vTotTrib'      => 0,
            'vTotTribTotal' => 0,
            'vICMSDeson'    => 0,
            'vICMS'         => 0,
            'vProd'         => 0,
            'vFrete'        => 0,
            'vSeg'          => 0,
            'aliqnac'       => 0,
            'aliqestadual'  => 0,
            'aliqmunicipal' => 0,
            'vCOFINS'       => 0,
            'vPIS'          => 0,
            'vBC'           => 0,
            'vBCST'         => 0,
            'vST'           => 0,
            'vIPI'          => 0,
            'vII'           => 0,
            'vDesc'         => 0,
            'vOutro'        => 0,
            'vNF'           => 0,
            'vICMSUFRemet'  => 0,
            'vICMSUFDest'   => 0,
            'vFCPUFDest'    => 0,
            'vFCP'          => 0,
            'vFCPST'        => 0,
            'vFCPSTRet'     => 0,
            'gIBSUF_vIBSUF'  => 0,
            'gIBSMun_vIBSMun'=> 0,
            'gCBS_vCBS'      => 0
        ];
    }

    /**
     * @param bool $isFromView
     */
    public function setEmpresa($isFromView = false)
    {
        if ($isFromView) {
            $this->empresa = $this->tipoNf === "E" ? $this->emitente : $this->destinatario;
        } else {
            $this->empresa = (object) $this->nf->empresa->toArray();
        }
    }

    /**
     * seta informações sobre os produtos
     * necessárias para calculos
     * @param null $pars
     * @throws Exception
     */
    public function setProdutosInfo($pars = null)
    {
        if (is_null($pars)) {
            $this->produtosJson = $this->nf->produtosjson;
        } else {
            $this->produtosJson = $pars['prod'];
        }
        $this->produtos = json_decode($this->produtosJson);
        //usado porque nas notas complementares, os produtos vem como um array não associativo
        //e na conversão para array as indexes ficam como string e gera problema
        foreach ($this->produtos as $key => $value) {
            if (is_string($value)) {
                $value = decodeAssociativeArray($value);
            }
            $this->produtos[$key] = Util::toArrayIdxInteger($value);
        }
        $this->operacoesProd = collect(array_column($this->produtos, 0));
        $this->idsProd = collect(array_column($this->produtos, 4));
        $this->idsSetoresProd = collect(array_column($this->produtos, 2));
        $this->gruposFiscaisProd = collect([]);
        $this->codNcmProd = collect([]);
        $this->setProdutosDb();
        //fica vazio quando é o calculo individual de uma importação de XML
        if ($this->gruposFiscaisProd->isEmpty()) {
            foreach ($this->produtos as &$prod) {
                $prodDB = $this->produtosDb->filter(function ($i) use ($prod) {
                    return $i->id == $prod[4];
                })->first();

                if (!$prodDB) {
                    throw new Exception("Ocorreu um erro ao buscar os produtos no banco de dados, contate o suporte");
                }

                if (!isset($prod[17])) {
                    $prod[9] = $prodDB->pesoliquido;
                    $prod[10] = $prodDB->pesobruto;
                    $prod[11] = $prodDB->ncm;
                    $prod[12] = "";
                    $prod[13] = "";
                    $prod[14] = "";
                    $prod[15] = "";
                    $prod[16] = "";
                    $prod[17] = $prodDB->nfgrupofiscal_id;
                    $prod[18] = $prod[7];
                    $prod[19] = $prodDB->pesoliquido * insertNumeroDecimalOracle($prod[7]);
                    $prod[20] = $prodDB->pesobruto * insertNumeroDecimalOracle($prod[7]);
                    $prod[21] = $this->tagCombXml->pGNi;
                    $prod[22] = $this->tagCombXml->pGNn;
                    $prod[23] = $this->tagCombXml->pGLP;
                }
                $this->gruposFiscaisProd->push($prodDB->nfgrupofiscal_id);
                $this->codNcmProd->push($prodDB->ncm);
            }
        }
        if (is_null($pars)) {
            $this->setItemsNf();
        }
        $this->setProdLeiImposto();
    }

    /**
     * seta os produtoleiimposto
     */
    private function setProdLeiImposto()
    {
        $this->prodLeiImposto = DB::table('produtoleiimpostos')->where("uf", $this->emitente->uf)->whereIn('ncm', $this->codNcmProd)->get();
    }

    /**
     * seta os proutos do banco
     */
    private function setProdutosDb()
    {
        $this->produtosDb = Produto::whereIn('id', $this->idsProd)->with('nfipi', 'unidademedida')->get();
    }

    /**
     * seta emitente e destinatário
     * @param null $pars
     * @throws Exception
     */
    function setEmitDest($pars = null)
    {
        if (is_null($pars)) {
            if ($this->tipoNf === "E") {
                $this->emitente = (object) $this->nf->empresa->toArray();
                $this->destinatario = (object) $this->nf->cliente->toArray();
            } else {
                $this->emitente = (object) $this->nf->cliente->toArray();
                $this->destinatario = (object) $this->nf->empresa->toArray();
            }
        } else {
            if ($this->tipoNf === "E") {
                $this->destinatario = DB::table('clientes')->where('id', $pars['cliente_id'])->get()->first();
                $this->emitente = DB::table('empresas')->where('id', $pars['empresa_id'])->get()->first();
            } else {
                $this->destinatario = DB::table('empresas')->where('id', $pars['empresa_id'])->get()->first();
                $this->emitente = DB::table('clientes')->where('id', $pars['cliente_id'])->get()->first();
            }
        }
        if (is_null($this->emitente)) {
            throw new Exception("Não foi possível localizar os dados do emitente para o cálculo de impostos.");
        }

        if (is_null($this->destinatario)) {
            throw new Exception("Não foi possível localizar os dados do destinaário para o cálculo de impostos.");
        }
    }

    /**
     * @param bool $value
     */
    public function setComplementar($value = true)
    {
        $this->isComplementar = $value;
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function updateNf()
    {
        $data = [];
        foreach ($this->total as $key => $value) {
            $data[strtolower($key)] = $value;
        }
        return $this->nf->update($data);
    }

    /**
     * buscando os impostos do banco e armazenando na variavel
     */
    private function setImpostos()
    {
        $this->nfImpostos = Nfimposto::whereIn('nfoperacao_id', $this->operacoesProd)
            ->where('empresa_id', $this->empresa->id)
            ->where('grupo_id', $this->empresa->grupo_id)
            ->whereIn('nfgrupofiscal_id', $this->gruposFiscaisProd)
            ->with(
                'nfoperacao',
                'nfcofins',
                'pfnfcofins',
                'nficms',
                'pfnficms',
                'nfpis',
                'pfnfpis',
                'pf_beneficiario',
                'pj_beneficiario',
                'nfimpostoestado.pfnficms',
                'nfimpostoestado.nficms',
                'nfimpostoestado.pf_beneficiario',
                'nfimpostoestado.pj_beneficiario',
                'nfcstibscbs',
                'pfcstibscbs',
                'nfclastribibscbs',
                'pfclastribibscbs'
            )
            ->get();
    }

    /**
     * busca e retorna os impostos para o produto
     * @param array $produto
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws Exception
     */
    private function getImposto($produto)
    {
        $nfImposto = $this->nfImpostos->first(function ($i) use ($produto) {
            return $i->nfoperacao_id == $produto[0] &&
                $i->empresa_id == $this->empresa->id &&
                $i->grupo_id == $this->empresa->grupo_id &&
                $i->nfgrupofiscal_id == $produto[17];
        });

        if (is_null($nfImposto)) {
            $ex = "Não foi encontrado nenhum imposto com o grupo fiscal "
                . "{$produto[16]} e operação {$produto[1]} do produto {$produto[5]}.";
            throw new Exception($ex);
        }
        return $nfImposto;
    }

    /**
     * @param CalculoImposto $calc
     */
    private function sumTotal($calc)
    {
        $det = $calc->det;
        $aliqnac = $calc->getAliqNac();
        $aliqestadual = $calc->getAliqEst();
        $aliqmunicipal = $calc->getAliqMun();
        $objIcms = $det->imposto['icms'];
        $objCofins = $det->imposto['cofins'];
        $objPis = $det->imposto['pis'];
        $objProd = $det->prod;
        $objIbsCbs = $det->imposto['ibscbs'];

        foreach ($objIcms as $key => $value) {
            if ($value === "")
                $objIcms->{$key} = (int) $value;
        }

        if (!Util::isSN($objIcms->CST)) {
            $this->total->vICMS += $objIcms->vICMS;  // Total de ICMS ST

            if (is_null($objIcms->vICMSDeson) && $this->useICMSDeson(true)) {
                $vICMSDeson = 0;
            } else {
                $vICMSDeson = $objIcms->vICMSDeson;
            }

            $this->total->vICMSDeson += $vICMSDeson;  // Total de ICMS desonerado
            $this->total->vBC += $objIcms->vBC; // Total da base de cálculo do ICMS
        }
        $this->total->vBCST += $objIcms->vBCST; // Total da base de cálculo do ICMS ST
        $this->total->vST += $objIcms->vICMSST; // Total de ICMS ST
        $this->total->vCOFINS += $objCofins->vCOFINS; // Total de COFINS
        $this->total->vPIS += $objPis->vPIS; // Total de PIS
        $this->total->vTotTrib += $det->imposto['vTotTrib'];
        $this->total->vProd += $objProd->vProdItem;
        $this->total->vFrete += sprintf('%0.5f', $objProd->vFrete);
        $this->total->vSeg += $objProd->vSeg;
        $this->total->aliqnac += sprintf('%0.5f', $aliqnac);
        $this->total->aliqestadual += sprintf('%0.5f', $aliqestadual);
        $this->total->aliqmunicipal += sprintf('%0.5f', $aliqmunicipal);
        $this->total->vTotTribTotal += $det->imposto['vTotTrib'];
        $this->total->vII += 0; //
        $this->total->vOutro += $objProd->vOutro; //
        $this->total->vDesc += $objProd->vDesc; //

        $this->total->vIPI += isset($calc->get('objIPI')->vIPI) ? $calc->get('objIPI')->vIPI : 0;

        $ufDest = $det->imposto['icmsUFDest'];
        $this->total->vICMSUFRemet += isset($ufDest->vICMSUFRemet) ? $ufDest->vICMSUFRemet : 0;
        $this->total->vICMSUFDest += isset($ufDest->vICMSUFDest) ? $ufDest->vICMSUFDest : 0;
        $this->total->vFCPUFDest += isset($ufDest->vFCPUFDest) ? $ufDest->vFCPUFDest : 0;
        $this->total->vFCP += isset($objIcms->vFCP) ? $objIcms->vFCP : 0;
        $this->total->vFCPST += isset($objIcms->vFCPST) ? $objIcms->vFCPST : 0;
        $this->total->vFCPSTRet += isset($objIcms->vFCPSTRet) ? $objIcms->vFCPSTRet : 0;

        $this->total->gIBSUF_vIBSUF += isset($objIbsCbs->gIBSUF_vIBSUF) ? $objIbsCbs->gIBSUF_vIBSUF : 0;
        $this->total->gIBSMun_vIBSMun += isset($objIbsCbs->gIBSMun_vIBSMun) ? $objIbsCbs->gIBSMun_vIBSMun : 0;
        $this->total->gCBS_vCBS += isset($objIbsCbs->gCBS_vCBS) ? $objIbsCbs->gCBS_vCBS : 0;

        $this->vDescUntTotal += $this->det->last()->prod->vDesc;
    }

    private function setOrigComb(&$item, $produto)
    {
        $origensDB = $produto->origens;

        $origens = [];
        foreach ($origensDB as $orig) {
            $origem = new stdClass();
            $origem->indImport = $orig->indimport;
            $origem->cUFOrig = $orig->cuforig;
            $origem->pOrig = $orig->porig;

            array_push($origens, $origem);
        }

        $item->origComb = $origens;
    }

    /**
     * retorna um produto da collection de produtos do banco de dados
     * @param int $produto_id
     * @return object/array
     * @throws Exception
     */
    private function getProdutoById($produto_id)
    {
        $prod = $this->produtosDb->where('id', $produto_id)->first();
        if (is_null($prod))
            throw new Exception("Produto código $produto_id não pode ser encontrado");
        return $prod;
    }

    /**
     * @param $ncm
     * @return mixed
     */
    private function getProdLeiImpostoByNcm($ncm)
    {
        $lei = $this->prodLeiImposto->where('ncm', $ncm)->first();
        if (!$lei) {
            \Session::flash("message_info", "NCM " . $ncm . " para UF " . $this->emitente->uf . " não encontrado na tabela " .
                "ProdutoLeiImpostos em desacordo com a lei 12.741/2012. Entre em contato com o suporte imediatamente.");
        }
        return $lei;
    }

    /**
     * retorna os erros
     *
     * @return string
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * buscando as operações do banco e armazenando na variavel
     */
    private function setOperacoes()
    {
        $this->nfOperacoes = DB::table('nfoperacaos')->whereIn('id', $this->operacoesProd)->get();
    }

    /**
     * retorna a operação de nf
     * @param int $id
     * @return Collection
     * @throws Exception
     */
    private function getOperacaoById($id)
    {
        $operacao = $this->nfOperacoes->where('id', $id)->first();
        if (is_null($operacao))
            throw new Exception("Não foi possível enontrar a operacao código $id");
        return $operacao;
    }

    /**
     * buscando os itens a notafiscal e armazenando na variavel
     */
    private function setItemsNf()
    {
        $this->nfItems = $this->nf instanceof Nfemitida ? $this->nf->nfEmitidaItem : $this->nf->nfRecebidaItem;
    }

    /**
     * @param bool $general
     * @param bool $using
     * @return bool
     */
    private function useICMSDeson($general = false, $using = false)
    {
        if ($general)
            return $this->useICMSDeson;
        $this->useICMSDeson = $using ? $using : $this->useICMSDeson;

        return $this->useICMSDeson;
    }

    private function setIBSCBSItem(&$item, $objIbsCbs)
    {
        $item->cstibscbs = $objIbsCbs->CST;
        $item->clastribibscbs = $objIbsCbs->cClassTrib;
        $item->vbcibscbs = $objIbsCbs->vBC;
        $item->predbcibscbs = $objIbsCbs->pRedBC;
        $item->pibsuf = $objIbsCbs->gIBSUF_pIBSUF;
        $item->vibsuf = $objIbsCbs->gIBSUF_vIBSUF;
        $item->pibsmun = $objIbsCbs->gIBSMun_pIBSMun;
        $item->vibsmun = $objIbsCbs->gIBSMun_vIBSMun;
        $item->pcbs = $objIbsCbs->gCBS_pCBS;
        $item->vcbs = $objIbsCbs->gCBS_vCBS;
    }

}
