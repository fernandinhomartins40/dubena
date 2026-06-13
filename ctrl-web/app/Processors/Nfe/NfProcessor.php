<?php

namespace App\Processors\Nfe;

use App\Nfemitidaitem;
use App\Nfrecebida;
use App\Nfrecebidaitem;
use App\Processors\EstoqueProcessor;
use App\Processors\financeiroProcessor;
use App\Processors\Nfe\Tools\MakeXml;
use App\Processors\Nfe\Tributacao\Base\DetBase;
use App\User;
use Auth;
use \Form;
use Illuminate\Support\Collection;
use \Input;
use Session;
use \Exception;
use App\Produto;
use App\Empresa;
use App\Nfemitida;
use App\Financeiro;
use App\Nfoperacao;
use App\Estoquesetor;
use App\Empresaconfig;
use App\Nfemitidavolume;
use App\Financeirorateio;
use App\Nfemitidaparcela;
use App\Nfrecebidaparcela;
use App\Contamovimentoestorno;
use App\Estoquesetorhistorico;
use App\Helpers\Utils\NfUtil as Util;
use stdClass;

class NfProcessor
{

    /**
     * @var mixed
     */
    private $empresa;

    /**
     * @var mixed
     */
    private $empresaConfig;

    /**
     * @var Nfemitida|Nfrecebida
     */
    private $nf;

    /**
     * @var
     */
    private $ambiente;

    /**
     * @var User
     */
    private $user;

    /**
     * @var int
     */
    public $resultsPerPage = 1000;

    /**
     * @var int
     */
    public $maxResults = 20000;

    /**
     * NfProcessor constructor.
     * @param Nfrecebida|Nfemitida|null $nf
     * @param null|Empresa|Collection $empresa
     * @param null|Empresaconfig $empresaConfig
     * @throws Exception
     */
    public function __construct($nf = null, $empresa = null, $empresaConfig = null)
    {
        if (!is_null($nf)) {
            $this->setNf($nf);
            if (is_null($empresa)) {
                $this->setEmpresa($nf->empresa_id, $empresa = null, $empresaConfig = null);
            } else {
                $this->setEmpresa(null, $empresa, $empresaConfig);
            }
        }
    }

    /**
     * @param Nfemitida|Nfrecebida $nf
     */
    public function setNf($nf)
    {
        $this->nf = $nf;
        $this->setAmbiente();
    }

    /**
     * seta o tipo de ambiente da NF: Homologação ou Produção
     */
    private function setAmbiente()
    {
        $this->ambiente = Util::getAmbiente($this->nf->nftipoambiente);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function gerarXML()
    {
        $nfemitida = $this->nf;
        $configJson = Util::getConfigTools($nfemitida);

        $makeXml = new MakeXml($nfemitida, $configJson, $this);

        $data = $this->getDataEmpresaToUpdate($nfemitida, $makeXml);

        return $nfemitida->update($data);
    }

    /**
     * @param DetBase $det
     */
    public function updateImpostosItems($det)
    {
        $nfitem = $det->nfItem;
        $objIcms = $det->imposto->get('icms');
        $objPis = $det->imposto->get('pis');
        $objCofins = $det->imposto->get('cofins');
        $objIPI = $det->imposto->get('ipi');
        $objProd = $det->prod;

        if ($nfitem != null) {
            $nfitem->update([
                "vbcpis"     => !isset($objIcms->vBC) ? 0 : $objIcms->vBC,
                "ppis"       => !isset($objPis->pPIS) ? 0 : $objPis->pPIS,
                "vpis"       => !isset($objPis->vPIS) ? 0 : $objPis->vPIS,
                "qbcprod"    => !isset($objPis->qBCProd) ? 0 : $objPis->qBCProd,
                "vdesc"      => !isset($objProd->vDesc) ? 0 : $objProd->vDesc,
                "orig"       => !isset($objIcms->orig) ? 0 : $objIcms->orig,
                "vbc"        => !isset($objIcms->vBC) ? 0 : $objIcms->vBC,
                "picms"      => !isset($objIcms->pICMS) ? 0 : $objIcms->pICMS,
                "vicms"      => !isset($objIcms->vICMS) ? 0 : $objIcms->vICMS,
                "vbcstret"   => !isset($objIcms->vBCST) ? 0 : $objIcms->vBCST,
                "vicmsstret" => !isset($objIcms->vICMSST) ? 0 : $objIcms->vICMSST,
                "predbcicms" => !isset($objIcms->pRedBC) ? 0 : $objIcms->pRedBC,
                "picmsst"    => !isset($objIcms->pICMSST) ? 0 : $objIcms->pICMSST,
                "vbcconfins" => !isset($objIcms->vBC) ? 0 : $objIcms->vBC,
                "pcofins"    => !isset($objCofins->pCOFINS) ? 0 : $objCofins->pCOFINS,
                "vcofins"    => !isset($objCofins->vCOFINS) ? 0 : $objCofins->vCOFINS,
                "vipi"       => !isset($objCofins->vIPI) ? 0 : $objCofins->vIPI,
                "pipi"       => !isset($objCofins->pIPI) ? 0 : $objCofins->pIPI,
                "vbcipi"     => !isset($objCofins->vBC) ? 0 : $objCofins->vBC,
                "cstipi"     => !isset($objCofins->CST) ? 0 : $objCofins->CST,
                'tagipi'     => Util::getTagIPI(strval($objIPI->CST)),
                'tagpis'     => Util::getTagPIS(strval($objPis->CST)),
                'tagcofins'  => Util::getTagCofins(strval($objCofins->CST)),
                'tagicms'    => Util::getTagICMS(strval($objIcms->CST)),
            ]);
        }
    }

    /**
     * @param Nfemitida $nfemitida
     * @param MakeXml $make
     * @return array
     */
    public function getDataEmpresaToUpdate($nfemitida, $make)
    {
        $nfoperacao = Nfoperacao::find($nfemitida->nfoperacao_id);
        $chave = $make->getChave();

        return [
            "xml"               => $make->getXml(),
            "chaveacesso"       => $chave,
            "chaveacessodv"     => substr($chave, -1),
            "cfop"              => $nfemitida->iddest == 1 ? $nfoperacao->cfop : $nfoperacao->cfopie,
            "descricaooperacao" => $nfoperacao->descricaofiscal,
            "nftipoimpressao"   => $make->getIde()->tpImp,
            "nfprocessoemissao" => $make->getIde()->procEmi,
        ];
    }

    /**
     * @param $vol
     */
    public function insertVol($vol)
    {
        Nfemitidavolume::create([
            'grupo_id'         => $this->nf->grupo_id,
            'empresa_id'       => $this->nf->empresa_id,
            'nfemitida_id'     => $this->nf->id,
            'produto_id'       => $vol->produto_id,
            'quantidadevolume' => intval($vol->quantidade),
            'produtoespecie'   => is_null($vol->especie) ? ' ' : $vol->especie,
            'produtomarca'     => is_null($vol->marca) ? ' ' : $vol->marca,
            'pesoliquido'      => $vol->pesoL,
            'pesobruto'        => $vol->pesoB
        ]);
    }

    /**
     * estornando os itens de nfemitidas ou nfrecebidas
     * @param $nf
     * @param $items
     * @param string $motivo
     * @return bool
     * @throws Exception
     */
    public function estornoItem($nf, $items, $motivo = "")
    {
        $tiponf = $nf instanceof Nfemitida ? "emitida" : "recebida";
        $empresaconfig = $this->empresaConfig;

        $user = Auth::user();

        $datahora = $nf->datahoraentradasaida;

        $optionsHistoricoGeneric = (object) [
            'datahora'            => $datahora,
            'datahoracompetencia' => $datahora,
            'entidade'            => "Nf" . $tiponf,
            'entidade_id'         => $nf->id,
            'empresa_id'          => $nf->empresa_id,
            'grupo_id'            => $nf->grupo_id,
            'user_id'             => $user->id
        ];

        $estoquesetorhistoricos = [];
        $operacoes = collect([]);
        if (!is_null($items)) {
            foreach ($items as $item) {
                $op = $operacoes->where('id', $item->nfoperacao_id)->first();
                if (!$op) {
                    $objoperacao = Nfoperacao::findOrFail($item->nfoperacao_id);
                    $operacoes->push($op);
                } else {
                    $objoperacao = $op;
                }
                if ($item->movimentaestoque > 0) { // 0-Nada 1-Entrada 2-Saída
                    $objproduto = Produto::findOrFail($item->cprod);
                    $arrMov = [
                        'optionsHistoricoGeneric' => $optionsHistoricoGeneric,
                        'tiponf'                  => $tiponf,
                        'objoperacao'             => $objoperacao,
                        'objproduto'              => $objproduto,
                        'item'                    => $item,
                        'produto_id'              => $item->cprod,
                        'setor_id'                => $item->setor_id,
                        'nf'                      => $nf,
                        'produtoqtde'             => $item->qcom,
                        'empresaconfig'           => $empresaconfig,
                    ];
                    $estoquesetorhistoricos = array_merge($estoquesetorhistoricos, $this->gerarObjMovimentacao($arrMov, true, $motivo));
                }
            }
        }
        $this->movimentarEstoque($estoquesetorhistoricos);

        return true;
    }

    /**
     * chama o metodo movimentarEstoque da EstoqueProcessor
     * @param array $estoquesetorhistoricos
     * @return boolean
     * @throws Exception
     */
    public function movimentarEstoque(array $estoquesetorhistoricos)
    {
        if (count($estoquesetorhistoricos) > 0) {
            $estoqueprocessor = new EstoqueProcessor();
            if (!$estoqueprocessor->movimentarEstoque($estoquesetorhistoricos))
                throw new Exception(implode(" ", $estoqueprocessor->getErrors()));
        }
        return true;
    }

    /**
     * gera o objeto para movimentação de estoque
     * @param array $pars
     * @param bool $estorno
     * @param string $motivo
     * @return array
     */
    public function gerarObjMovimentacao($pars, $estorno = false, $motivo = "")
    {
        $historicos = [];

        $optionsHistoricoGeneric = $pars['optionsHistoricoGeneric'];
        $tiponf = $pars['tiponf'];
        $objoperacao = $pars['objoperacao'];
        $objproduto = $pars['objproduto'];
        $item = $pars['item'];
        $produto_id = $pars['produto_id'];
        $setor_id = $pars['setor_id'];
        $nf = $pars['nf'];
        $produtoqtde = $pars["produtoqtde"];
        $empresaconfig = $this->empresaConfig;

        if ($estorno) {
            $motivo .= "Estorno de ";
        }

        $hasRessarcimento = $empresaconfig->operacao_ressarcimento == $objoperacao->id && !is_null($objproduto->ressarcimentoproduto_id) && $tiponf === "recebida";
        if ($hasRessarcimento) {
            $options = $optionsHistoricoGeneric;
            $options->setor_id = $empresaconfig->setor_ressarcimento;
            $options->produto_id = $objproduto->ressarcimentoproduto_id;
            $options->quantidade = $produtoqtde * $objproduto->pesoliquido;
            $options->motivo = $motivo . "Lançamento de Documento: " . $nf->nfnumero . " - Produto com Ressarcimento";
            // 0-Nada 1-Entrada 2-Saída --- ESTORNO FAZ O CONTRÁRIO
            if ($estorno) {
                $options->movimentacao = $item->nfOperacao->movimentaestoque == 1 ? 'SAIDA' : 'ENTRADA';
            } else {
                $options->movimentacao = $item->nfOperacao->movimentaestoque == 1 ? 'ENTRADA' : 'SAIDA';
            }
            array_push($historicos, $this->newEstoqueSetorHistorio($options));
        }
        $options = $optionsHistoricoGeneric;
        $options->setor_id = $setor_id;
        $options->produto_id = $produto_id;
        $options->quantidade = $produtoqtde;


        if ($tiponf === 'recebida')
            $motivo .= "Lançamento de Documento: ";
        else
            $motivo .= "Emissão de NF: ";

        $options->motivo = $motivo . $nf->nfnumero;

        // 0-Nada 1-Entrada 2-Saída --- ESTORNO FAZ O CONTRÁRIO
        if ($estorno)
            $options->movimentacao = $item->nfOperacao->movimentaestoque == 1 ? 'SAIDA' : 'ENTRADA';
        else
            $options->movimentacao = $item->nfOperacao->movimentaestoque == 1 ? 'ENTRADA' : 'SAIDA';

        //quando é nfemitda, armazena o customedio do item no histórico
        if (isset($item->customedio) && $options->movimentacao === 'ENTRADA' && $objoperacao->atualizacusto && $estorno)
            $options->customedioestorno = floatval($item->customedio);

        array_push($historicos, $this->newEstoqueSetorHistorio($options));

        //atualizando o customedio do produto
        if ($objoperacao->atualizacusto && $objoperacao->tiponf == 0 && $tiponf === "recebida")
            $this->estornoCusto($item, $objproduto, $estorno);

        return $historicos;
    }

    /**
     *
     * @param Nfemitidaitem|Nfrecebidaitem $item
     * @param Produto $objproduto
     * @param boolean $estorno
     */
    private function estornoCusto($item, Produto $objproduto, $estorno = false)
    {
        $produtoqtde = $item->qcom;
        $produtovlrvenda = $item->vuncom;

        $qtdetotalestoque = Estoquesetor::where("produto_id", $objproduto->id)->select('quantidade')->get()->sum('quantidade');
        Produto::updateCustoMedio($objproduto, $qtdetotalestoque, $produtoqtde, $produtovlrvenda, $estorno);
    }

    /**
     * cria um objeto do tipo Estoquesetorhistorico
     * @param object $options
     * @return Estoquesetorhistorico
     */
    private function newEstoqueSetorHistorio($options)
    {
        $estoquesetorhistorico = new Estoquesetorhistorico();
        $estoquesetorhistorico->user_id = $options->user_id;
        $estoquesetorhistorico->grupo_id = $options->grupo_id;
        $estoquesetorhistorico->empresa_id = $options->empresa_id;
        $estoquesetorhistorico->entidade = $options->entidade;
        $estoquesetorhistorico->entidade_id = $options->entidade_id;
        $estoquesetorhistorico->setor_id = $options->setor_id;
        $estoquesetorhistorico->produto_id = $options->produto_id;
        // 0-Nada 1-Entrada 2-Saída (ESTORNO faz o inverso)
        $estoquesetorhistorico->movimentacao = $options->movimentacao;
        $estoquesetorhistorico->quantidade = $options->quantidade;
        $estoquesetorhistorico->motivo = $options->motivo;
        $estoquesetorhistorico->datahora = $options->datahora;
        if (isset($options->customedioestorno))
            $estoquesetorhistorico->customedioestorno = $options->customedioestorno;
        $estoquesetorhistorico->datahoracompetencia = $options->datahoracompetencia;
        return $estoquesetorhistorico;
    }

    /**
     *
     * @param array $pars <br />
     * <b>chaves do do <i>array</i> $pars</b>: <br />
     * <i>array</i> data => $data[<i>json</i> 'rateios', 'datahoraemissao'] <br />
     * int cliente_id <br />
     * char pagarreceber <br />
     * Nfrecebida ou Nfemitida nf <br />
     * Nfrecebida ou Nfemitida nfdb <br />
     * string descricao_lanc <br />
     * float total <br />
     * int centrocusto_id <br />
     * int planoconta_id <br /> <br />
     * int condicaopagamento_id <br />
     * array parcelas - customHelper calculoParcelas() <br />
     * boolean frete <br />
     * @return boolean
     * @throws Exception
     */
    public function gerarFinanceiro(array $pars)
    {
        $data = $pars['data'];
        $cliente_id = $pars['cliente_id'];
        $pagarreceber = $pars['pagarreceber'];
        $nf = $pars['nf'];
        $nfbd = $pars['nfbd'];
        $descricao_lanc = $pars['descricao_lanc'];
        //total é vnf - vfrete
        $total = $pars['total'];
        $centrocusto_id = $pars['centrocusto_id'];
        $planoconta_id = $pars['planoconta_id'];
        $condicaopagamento_id = $pars['condicaopagamento_id'];
        $parcelas = $pars['parcelas'];
        $frete = $pars['frete'];

        $processor = new financeiroProcessor();
        if ($planoconta_id !== null && $centrocusto_id !== null) {
            $financeiro = new Financeiro();
            $financeiro->empresa_id = $nf->empresa_id;
            $financeiro->grupo_id = $nf->grupo_id;
            $financeiro->cliente_id = $cliente_id;
            $financeiro->pagarreceber = $pagarreceber;
            $financeiro->documento = $nf->nfnumero;
            $financeiro->descricao = $descricao_lanc;
            $financeiro->cartaonsu = null;
            $financeiro->cartaoautorizacao = null;
            $financeiro->valor = $frete ? $total : $total + $this->nf->vdesc;
            $financeiro->condicaopagamento_id = $condicaopagamento_id;
            $financeiro->datacompetencia = $data["datahoraemissao"];
            $financeiro->dataemissao = $data["datahoraemissao"];
            $processor->setFinanceiro($financeiro);

            $countparcelas = (int) count($parcelas);
            $avista = Util::isPgtoAvisa($parcelas, $financeiro, $countparcelas);
            $nf->avista = $avista;

            $descontoparcelar = 0;
            if (!$frete) {
                $descontoparcelar = $nf->vdesc / ($avista ? 1 : $countparcelas);
                $tRateio = $this->nf->vnf + $this->nf->vdesc - $this->nf->vfrete;
            } else {
                $tRateio = $this->nf->vfrete;
            }

            $this->gerarRateio($processor, $tRateio, $data, $frete, $planoconta_id, $centrocusto_id);
            $parc = Util::gerarParcelas($countparcelas, $parcelas, $descontoparcelar, $processor, $data['datahoraemissao'], $total, $this->empresa);
            $processor->setParcelas($parc);
            $this->setParcelasNf($parc, $nf);

            if (!$processor->gravar()) {
                throw new Exception(implode(" ", $processor->getErrors()));
            }

            $idfinanceiro = $processor->getFinanceiro()->id;

            if ($frete) {
                $dataUp = ["fretefinanceiro_id" => $idfinanceiro];
            } else {
                $dataUp = ["financeiro_id" => $idfinanceiro];
            }
            return $nfbd->update($dataUp);
        } else {
            $msg = "Problemas ao criar Financeiro: Não foi encontrado o plano de contas ou centro de custos";
            throw new Exception($msg);
        }
    }

    /**
     * @param $parcelas
     * @param $nf
     */
    private function setParcelasNf($parcelas, $nf)
    {
        foreach ($parcelas as $parc) {
            if ($nf instanceof Nfemitida) {
                $p = new Nfemitidaparcela();
                $p->nfemitida_id = $nf->id;
            } else {
                $p = new Nfrecebidaparcela();
                $p->nfrecebida_id = $nf->id;
            }
            $p->empresa_id = $parc->empresa_id;
            $p->grupo_id = $parc->grupo_id;
            $p->numeroparcela = $parc->numero;
            $p->referencia = str_pad($parc->numero, 3, "0", STR_PAD_LEFT);
            $p->datavencimento = $parc->datavencimento;
            $p->valororiginal = $parc->valorefetivado;
            $p->save();
        }
    }

    /**
     * Gera o rateio se foi passado algum, senão gera o padrão
     *
     * @param FinanceiroProcessor|null $processor
     * @param $total
     * @param $data
     * @param $frete
     * @param $planoconta_id
     * @param $centrocusto_id
     * @return array|null
     * @throws Exception
     */
    private function gerarRateio(?FinanceiroProcessor $processor, $total, $data, $frete, $planoconta_id, $centrocusto_id)
    {
        $rato = [];
        $rateios = isset($data["rateios"]) && count(json_decode($data["rateios"])) > 0 ? json_decode($data["rateios"]) : null;

        // Substitui o rateio default pelos informados
        if (!$frete && !is_null($rateios)) {
            $vlrtotalrateio = 0;

            foreach ($rateios as $rateio) {
                $rateiovalor = insertNumeroDecimalOracle($rateio[6]);
                $vlrtotalrateio += $rateiovalor;

                $raton = new Financeirorateio();
                $raton["centrocusto_id"] = $rateio[0];
                $raton["planoconta_id"] = $rateio[3];
                $raton["valor"] = $rateiovalor;
                $raton["financeiro_id"] = is_null($processor) ?: $processor->getFinanceiro()->id;
                $raton["percentual"] = $total > 0 ? $rateiovalor / $total : 1;
                array_push($rato, $raton);
            }
        } else {
            $raton = new Financeirorateio();
            $raton["centrocusto_id"] = $centrocusto_id;
            $raton["planoconta_id"] = $planoconta_id;
            $raton["valor"] = $total;
            $raton["financeiro_id"] = is_null($processor) ?: $processor->getFinanceiro()->id;
            $raton["percentual"] = 1;
            $vlrtotalrateio = $total;
            array_push($rato, $raton);
        }

        if (strval($total) !== strval($vlrtotalrateio)) {
            throw new Exception("Total do rateio não confere com o total da NF.");
        }

        if (!is_null($processor)) {
            $processor->setRateios($rato);
            return null;
        } else {
            return $rato;
        }
    }

    /**
     * @param $data
     * @throws Exception
     */
    public function updateRateio($data)
    {
        if (!is_null($this->nf->financeiro)) {
            $tRateio = $this->nf->vnf + $this->nf->vdesc - $this->nf->vfrete;
            $rateios = $this->gerarRateio(null, $tRateio, $data, false, $data["planoconta_id"], $data["centrocusto_id"]);
            $this->nf->financeiro->rateios()->delete();

            $this->nf->financeiro->rateios()->saveMany($rateios);
        }
    }

    /**
     * @param Financeiro $financeiro
     * @param $nf
     * @throws Exception
     */
    public function estornarFinanceiroModel(Financeiro $financeiro, $nf)
    {
        if ($financeiro != null) {
            $parcelas = $financeiro->parcelas()->get();
            $this->validateEstornoFinanceiro(true, $financeiro);

            $parcelasid = $parcelas->pluck('id');

            $contamovimentoestornos = Contamovimentoestorno::whereIn('financeiroparcela_id', $parcelasid)->get();
            if (count($contamovimentoestornos) > 0) {
                $processor = new financeiroProcessor();
                $processor->setParcelas($parcelas);

                if (!$processor->validarCancelamentoParcelas()) {
                    throw new Exception(implode(' ', $processor->getErrors()));
                }
                if ($nf instanceof Nfemitida) {
                    $motivo = 'Estorno de NF-e modelo \"';
                } else {
                    $motivo = 'Estorno de documento modelo \"';
                }

                if (!$processor->cancelarParcelas($motivo . $nf->nfmodelo . '" e número "' . $nf->nfnumero . '"')) {
                    throw new Exception(implode(' ', $processor->getErrors()));
                }
            } else {
                $financeiro->parcelas()->delete();
                $financeiro->rateios()->delete();
                $financeiro->delete();
            }
        }
    }

    /**
     * @param null $id
     * @param null|Empresa|Collection $empresa
     * @param null|Empresaconfig $empresaConfig
     * @throws Exception
     */
    public function setEmpresa($id = null, $empresa = null, $empresaConfig = null)
    {
        if (!is_null($empresa)) {
            $this->empresa = $empresa;
        } else {
            $this->empresa = Empresa::find($id);
        }

        if (is_null($this->empresa))
            throw new Exception('Não foi possível buscar a empresa no banco de dados.');

        $this->setConfig($empresaConfig);
    }

    /**
     * @param null $config
     * @throws Exception
     */
    public function setConfig($config = null)
    {
        if ($config) {
            $this->empresaConfig = $config;
            return;
        }
        if (!$this->empresa) {
            throw new Exception('Empresa não informada para buscar as configurações.');
        }
        $this->empresaConfig = Empresaconfig::where('empresa_id', $this->empresa->id)->get()->first();

        if (is_null($this->empresaConfig))
            throw new Exception('Não foi possível buscar as configurações da empresa no banco de dados.');
    }

    /**
     * @param Nfrecebida|Nfemitida $nfDb
     * @return object
     */
    public function filterIndex($nfDb)
    {
        $count = clone $nfDb;
        $countn = $count->selectRaw('count(id) as count, sum(CASE WHEN nfsituacao_id = 100 THEN vnf ELSE 0 END) AS vnf')->get()->first();
        $countTotal = !is_null($countn) ? $countn->count : 0;
        $vNF = !is_null($countn) ? $countn->vnf : 0;

        if ($countTotal > $this->maxResults) {
            $msg = "O número de registros é muito grande e pode causar uma sobrecarga. "
                . "Por favor, utilize os filtros para trazer um número menor resultados.";
            Session::flash('message_danger', $msg);
            return (object) [
                'nf'    => collect([]),
                'vNF'   => 0,
                'total' => 0
            ];
        }

        $sort = Input::get('sort', 'nfnumero');
        $order = Input::get('order', 'desc');

        if ($sort == 'cliente')
            $sort = 'destrazaosocial';
        elseif ($sort == 'nfoperacao')
            $sort = 'descricaooperacao';

        return (object) [
            'nf'    => $nfDb->get()->sortBy($sort, SORT_NATURAL, strtoupper($order) !== 'ASC'),
            'vNF'   => $vNF,
            'total' => $countTotal
        ];
    }

    /**
     * @param $paginated
     * @param $redirectUrl
     * @return Collection
     */
    private function treatDataToTable($paginated, $redirectUrl)
    {
        $this->user = Auth::user();
        $nfTable = collect([]);
        foreach ($paginated as $nf) {
            $situacao = $nf->nfSituacao;
            $stdNf = new stdClass();
            $stdNf->id = $nf->id;
            $stdNf->nfnumero = $nf->nfnumero;
            $stdNf->nfmodelo = $nf->nfmodelo;
            $stdNf->datahoraemissao = requestDataOracleSemSeg($nf->datahoraemissao);
            $stdNf->cliente = $nf->cliente->nome;

            if ($nf instanceof Nfemitida) {
                $stdNf->nfsituacao_id = $situacao != null ? $situacao->id : '';
                $stdNf->nfsituacao = $situacao != null ? $situacao->msgerroreceita : '';
            } else {
                $stdNf->tipolancamento = $nf->tipolancamento ? "Terceiros" : "Própria";
                if ($situacao) {
                    switch ((int) $situacao->id) {
                        case 100:
                            $stdNf->nfsituacao = "Normal";
                            break;
                        case 101:
                            $stdNf->nfsituacao = "Cancelado";
                            break;
                        default:
                            $stdNf->nfsituacao = "Inutilizado";
                    }
                } else {
                    $stdNf->nfsituacao = "Não encontrado";
                }
            }

            $stdNf->vnf = requestNumeroDecimalOracle($nf->vnf);
            $stdNf->buttons = $this->getButtonsNfIndex($nf, $situacao, $stdNf, $redirectUrl);

            $nfTable->push($stdNf);
        }

        return $nfTable;
    }

    /**
     * @param $nf
     * @param $situacao
     * @param $stdNf
     * @param $redirectUrl
     * @return string
     */
    private function getButtonsNfIndex($nf, $situacao, $stdNf, $redirectUrl)
    {
        $buttons = "";
        $type = $nf instanceof Nfemitida ? "emitida" : "recebida";
        $id = "btnVisualizar";
        $title = "Visualizar";
        $content = "<span class='fa fa-eye fa-lg'></span>";
        $class = "btn btn-nw-buscas btn-xs";
        $href = route("nf{$type}.show", $nf->id) . $redirectUrl;
        $buttons .= $this->getButtonActionIndex($id, $title, $content, $class, $href);

        $id = "btnEditar";
        $title = "Editar";
        $content = "<span class='fa fa-pencil-square-o fa-lg'></span>";
        $class = "btn btn-nw-geral btn-xs";
        $href = route("nf{$type}.edit", $nf->id) . $redirectUrl;

        if ($this->user->can('update', $nf))
            $btnEdit = $this->getButtonActionIndex($id, $title, $content, $class, $href);
        else
            $btnEdit = "";

        if ($nf instanceof Nfemitida) {
            if ($situacao != null) {
                $sit = $situacao->id;
                if (!Util::isAuthorized($sit)) {
                    $buttons .= $btnEdit;
                } elseif ($sit !== 102) {
                    $id = "btnImprimir";
                    $title = "Imprimir";
                    $content = "<span class='fa fa-lg fa-file-pdf-o'></span>";
                    $class = "btn btn-nw-registro btn-xs";
                    $buttons .= $this->getButtonActionIndex($id, $title, $content, $class);
                }
            }
            if (count($nf->nfEmitidaCartaCorrecao) > 0) {
                $buttons .= '<a href=" ' . url('nfemitida/evento/cce?id=' . $nf->id . '&correcao=&create=0') . '" target="_blank"'
                    . ' class="btn btn-nw-registro btn-xs btnCCE" data-toggle="tooltip" data-trigger="hover"'
                    . ' data-placement="bottom" title="Carta Correção">'
                    . '<span class="fa fa-lg fa-file-pdf-o"></span></span>'
                    . '</a>';
            }
        } else {
            $buttons .= $btnEdit;
            $id = "btnRemover";
            $title = "Remover";
            $content = "<span class='fa fa-trash fa-lg'></span>";
            $class = "btn btn-nw-registro btn-xs";
            $nfJson = json_encode($stdNf);
            $onclick = "removeRegister({$nfJson})";

            if ($this->user->can('delete', $nf))
                $buttons .= $this->getButtonActionIndex($id, $title, $content, $class, null, $onclick);

            $id = "btnPrint";
            $title = "Imprimir Comprovante";
            $content = "<span class='fa fa-file-pdf-o fa-lg'></span>";
            $class = "btn btn-nw-buscas btn-xs";
            $onclick = "printRegister({$stdNf->id})";

            if ($this->user->can('view', $nf))
                $buttons .= $this->getButtonActionIndex($id, $title, $content, $class, null, $onclick);
        }

        return $buttons;
    }

    /**
     * @param $id
     * @param $title
     * @param $content
     * @param $class
     * @param string $href
     * @param string $onclick
     * @return string
     */
    private function getButtonActionIndex($id, $title, $content, $class, $href = '', $onclick = '')
    {
        $htmlButton = [
            'id'             => $id,
            'class'          => $class,
            'onclick'        => $onclick,
            'data-toggle'    => 'tooltip',
            'data-trigger'   => 'hover',
            'data-placement' => 'bottom',
            'title'          => $title,
        ];
        if ($href) {
            $f = "";
            foreach ($htmlButton as $key => $v) {
                $f .= $key . '="' . $v . '" ';
            }
            $htmlElement = "<a href='" . $href . "' " . $f . ">" . $content . "</a>";
        } else {
            $htmlElement = Form::button($content, $htmlButton)->toHtml();
        }
        return $htmlElement . "&nbsp";
    }

    /**
     * @param $results
     * @param $currentPage
     * @param $totalPages
     * @param $redirectUrl
     * @return Collection
     */
    public function paginate($results, $currentPage, $totalPages, $redirectUrl)
    {
        if ($currentPage === 0) {
            $currentPage = 1;
        } elseif ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        //diminui 1 para pegar a index correta do array
        $paginated = paginate($results->nf, $currentPage - 1, $this->resultsPerPage);

        return $this->treatDataToTable($paginated, $redirectUrl);
    }

    /**
     * @param bool $throwEx
     * @param null $model
     * @throws Exception
     */
    public function validateEstornoFinanceiro($throwEx = false, $model = null)
    {
        $financeiroModel = $model ? $model : $this->nf->financeiro;
        if ($financeiroModel) {
            $baixado = $this->isBaixadoFinanceiro($financeiroModel);
            if ($throwEx) {
                $msg = "Uma ou mais parcela (s) %s e não pode ser estornada.";
            } else {
                $msg = "Uma ou mais parcela (s) %s, se alguma alteração referente ao financeiro for feita, o sistema não permitirá salvar a NF-e.";
            }
            if ($baixado) {
                $anotherMsg = sprintf($msg, "já está baixada");
                if ($throwEx) {
                    throw new Exception($anotherMsg);
                } else {
                    Session::flash("message_info", $anotherMsg);
                }
            }
            if (!Util::allowedEstornoParcela($financeiroModel->id)) {
                $anotherMsg = sprintf($msg, "está vinculada a um cheque ou boleto");
                if ($throwEx) {
                    throw new Exception($anotherMsg);
                } else {
                    Session::flash("message_info", $anotherMsg);
                }
            }
        }
    }

    /**
     * verifica se alguma parcela do financeiro já foi baixada
     * @param Financeiro $financeiro
     * @return bool
     */
    public function isBaixadoFinanceiro(Financeiro $financeiro)
    {
        return !is_null($financeiro) && !is_null($financeiro->parcelas->where('baixado', 1)->first());
    }

    /**
     * @param Nfemitida|Nfrecebida $nf
     * @param array $data
     * @return bool
     */
    public function checkForChangesFinanceiro($nf, $data)
    {
        return $this->compareProducts($nf->produtosjson, $data['produtosJson'])
            || $nf->cliente_id !== $data['cliente_id']
            || $nf->condicaopagamento_id !== $data['condicaopagamento_id']
            || $nf->planoconta_id !== $data['planoconta_id']
            || $nf->centrocusto_id !== $data['centrocusto_id']
            || $nf->datahoraemissao !== $data['datahoraemissao']
            || requestNumeroDecimal4DigitosOracle($nf->vprod) !== requestNumeroDecimal4DigitosOracle($data['vprod'])
            || requestNumeroDecimal4DigitosOracle($nf->vdesc) !== requestNumeroDecimal4DigitosOracle($data['vdesc'])
            || requestNumeroDecimal4DigitosOracle($nf->voutro) !== requestNumeroDecimal4DigitosOracle($data['voutro'])
            || requestNumeroDecimal4DigitosOracle($nf->vseg) !== requestNumeroDecimal4DigitosOracle($data['vseg'])
            || requestNumeroDecimal4DigitosOracle($nf->vnf) !== requestNumeroDecimal4DigitosOracle($data['vnf']);
    }

    public function compareProducts($jsonNf, $jsonView)
    {
        if ($jsonNf === $jsonView) {
            return false;
        }

        return isArrayDiffMultidimensional(json_decode($jsonNf), json_decode($jsonView));
    }

    /**
     * @param Nfemitida|Nfrecebida $nf
     * @param array $data
     * @return null
     */
    public function checkForChangesFinanceiroFrete($nf, $data)
    {
        return $nf->fretecliente_id !== (isset($data['fretecliente_id']) ? $data['fretecliente_id'] : null)
            || sprintf("%0.4", $nf->vfrete) !== sprintf("%0.4", (isset($data['vfrete']) ? $data['vfrete'] : 0))
            || $nf->freteplanoconta_id !== (isset($data['freteplanoconta_id']) ? $data['freteplanoconta_id'] : null)
            || $nf->fretecentrocusto_id !== (isset($data['fretecentrocusto_id']) ? $data['fretecentrocusto_id'] : null)
            || $nf->fretecondicaopagamento_id !== (isset($data['fretecondicaopagamento_id']) ? $data['fretecondicaopagamento_id'] : null)
            || $nf->formapagamento !== (isset($data['formapagamento']) ? $data['formapagamento'] : null);
    }
}
