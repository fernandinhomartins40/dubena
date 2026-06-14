<?php

namespace App\Http\Controllers;

use Log;
use App\Nfemitidaitem;
use App\Processors\Nfe\Tools\SefazEvento;
use \Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use \Input;
use \Exception;
use App\Pedido;
use App\Cliente;
use App\Produto;
use App\Empresa;
use App\Nfemitida;
use App\Nfoperacao;
use App\Nfsituacao;
use App\Planoconta;
use App\Centrocusto;
use App\Empresaconfig;
use App\Nfceconfigpedido;
use App\Nfoperacaoproduto;
use Illuminate\Http\Request;
use App\Processors\Nfe\NfProcessor;
use App\Http\Requests\NfRequest;
use Illuminate\Support\Facades\DB;
use App\Repository\SelectRepository;
use App\Helpers\Utils\NfUtil as Util;
use App\Nfoperacaoprodutoconvenio;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Services\CarbonCustom as Carbon;
use App\Processors\Nfe\Tributacao\NfeImpostoProcessor;
use NFePHP\Common\Exception\ValidatorException as NFeException;
use NFePHP\NFe\Common\ValidTXT;
use NFePHP\NFe\Convert;

/**
 * Class NfemitidaController
 * @package App\Http\Controllers
 */
class NfemitidaController extends Controller
{

    /**
     * @var Empresa|null
     */
    private $empresa = null;
    private $transportadora = null;
    private $nf_id = 0;

    /**
     * NfemitidaController constructor.
     */
    public function __construct()
    {
        if (!defined('NFE_VERSION'))
            define('NFE_VERSION', '4.00');
    }

    /**
     * @param Nfemitida $nots
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Nfemitida $nots)
    {
        $this->authorize('view', $nots);
        $empresa_id = (int) Input::get('empresa_id', Session::get('empresa_padrao')->id);
        $dataInicial = Input::get('dataI', Carbon::now()->subDays(2)->toDateString());
        $dataFinal = Input::get('dataF', Carbon::now()->toDateString());

        $processor = new NfProcessor();

        if ($empresa_id > 0) {
            $results = $this->filter($empresa_id, $dataInicial, $dataFinal, $processor);
        } else {
            $results = (object) [
                'nf'    => collect([]),
                'vNF'   => 0,
                'total' => 0
            ];
        }
        $redirectUrl = '?index=' . str_replace("&", 'extPar', \Request::fullUrl());
        $fullUrl = route('nfemitida.create') . $redirectUrl;

        $empresaUser = $this->getEmpresaUser();
        $empresasSelect = clone $empresaUser->pluck('nome_informal', 'id')->prepend('', '');
        $empresas = $empresaUser->toJson();

        $nfmodelos = Util::getNfModelos();

        $currentPage = (int) Input::get('page', 1);
        $totalPages = ceil($results->total / $processor->resultsPerPage);

        $nfTable = $processor->paginate($results, $currentPage, $totalPages, $redirectUrl);

        $valorTotalAutorizadas = requestNumeroDecimalOracle($results->vNF);

        $dataFinal = requestDataOracle($dataFinal, false, true);
        $dataInicial = requestDataOracle($dataInicial, false, true);
        $tiponf = "emitida";

        $c = compact(
            'nfmodelos',
            'empresa_id',
            'dataInicial',
            'dataFinal',
            'empresas',
            'valorTotalAutorizadas',
            'empresasSelect',
            'nfTable',
            'currentPage',
            'totalPages',
            'fullUrl',
            'tiponf'
        );
        return view('nf.nfemitida', $c);
    }

    /**
     * @param $empresa_id
     * @param $dataInicial
     * @param $dataFinal
     * @param $processor
     * @return mixed
     */
    private function filter($empresa_id, $dataInicial, $dataFinal, NfProcessor $processor)
    {
        $nfmodelo = (int) Input::get('nfmodelo', '');
        $numnf = (int) Input::get('numnf', 0);

        $nfDb = Nfemitida::with('nfSituacao', 'cliente', 'nfEmitidaCartaCorrecao')
            ->where('empresa_id', $empresa_id)->where('nfmodelo', $nfmodelo);
        if ($numnf > 0) {
            $nfDb->where('nfnumero', $numnf);
        } else {
            $aDates = [
                $dataInicial . ' 00:00:00',
                $dataFinal . ' 23:59:59'
            ];
            $nfDb->whereBetween('datahoraemissao', $aDates);
        }

        return $processor->filterIndex($nfDb);
    }

    /**
     * @param NfRequest $request
     * @param Nfemitida $nots
     * @return $this|\Illuminate\Database\Eloquent\Model|string
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(NfRequest $request, Nfemitida $nots)
    {
        $this->authorize('create', $nots);
        $complementar = $request->only('nfcomplementar')['nfcomplementar'];
        DB::beginTransaction();
        if ($complementar) {
            try {
                $request = $this->getRequestComplementar($request, $nots);
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }

        $nf = $this->processaStore($request->all());

        if ($nf instanceof Nfemitida) {
            DB::commit();
            if ($complementar)
                return "OK|" . $nf->id;
            else {
                $url = Util::getUrlPostWrite('emitida', $nf->id);
                Session::flash("message_success", "Nota Fiscal gravada com sucesso!");
                return Redirect::to($url);
            }
        } else {
            DB::rollback();
            if ($complementar) {
                return $nf;
            } else {
                $url = "nfemitida/create" . '?index=' . str_replace('&', "extPar", Input::get('index', route("nfemitida.index")));
                return Redirect::to($url)->withErrors($nf)->withInput();
            }
        }
    }

    /**
     * Gera um formulário igual ao que vem da view no metodo Store para gerar a NF complementar
     * @param $request
     * @param Nfemitida $nots
     * @return NfRequest|string
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function getRequestComplementar(Nfrequest $request, Nfemitida $nots)
    {
        $this->authorize('especial', $nots);
        $numero = trim($request->only('numnfe_complementar')['numnfe_complementar']);
        $serie = trim($request->only('nfserie_complementar')['nfserie_complementar']);
        $produtos = $request->only('produtos_complementar')['produtos_complementar'];

        $empresa = Session::get('empresa_padrao');

        $nfRef = Nfemitida::with('nfEmitidaItem.produto', 'nfEmitidaItem.nfoperacao', 'cliente')
            ->where('empresa_id', $empresa->id)
            ->where('nfnumero', $numero)
            ->where('nfmodelo', '55')
            ->where('nfserie', $serie)->get()->first();

        if (is_null($nfRef)) {
            return "Nota referenciada não encontrada no banco de dados";
        }

        $itemsInfo = $this->prodNfComplementar($produtos, $nfRef->nfEmitidaItem);

        $pars = [
            'tiponf'     => "E",
            'prod'       => $itemsInfo['produtos'],
            'vdesc'      => 0,
            'vprod'      => $itemsInfo['value'],
            'voutro'     => 0,
            'destuf'     => $nfRef->destuf,
            'iddest'     => $nfRef->iddest,
            'indfinal'   => $nfRef->indfinal,
            'vfrete'     => 0,
            'vseg'       => 0,
            'nfmodelo'   => $nfRef->nfmodelo,
            'cliente_id' => $nfRef->cliente_id,
            'empresa_id' => $nfRef->empresa_id,
        ];

        $processor = new NfeImpostoProcessor(null, $pars);
        $processor->setComplementar();
        $calculoImpostos = $processor->calculaImposto(false);
        $impFloat = $calculoImpostos[1];
        $calculoImpostos = $calculoImpostos[0];
        $data = [
            "nfmodelo"                 => $nfRef->nfmodelo,
            "datahoraemissao"          => Carbon::now()->format('d/m/Y H:m:i'),
            "datahoraentradasaida"     => Carbon::now()->format('d/m/Y H:m:i'),
            "nfefinalidade"            => "2",
            "presencacomprador"        => 0,
            "empresa_id"               => $nfRef->empresa_id,
            "nfoperacao_id"            => $nfRef->nfoperacao_id,
            "cliente_id"               => $nfRef->cliente_id,
            "informacaocomplementar"   => "",
            "informacaoadicionalfisco" => $nfRef->informacaoadicionalfisco,
            "emitrazaosocial"          => $nfRef->emitrazaosocial,
            "emitnomefantasia"         => $nfRef->emitnomefantasi,
            "emitie"                   => $nfRef->emitie,
            "emitcnpj"                 => $nfRef->emitcnpj,
            "emitcpf"                  => $nfRef->emitcpf,
            "emitinscricaomunicipal"   => $nfRef->emitinscricaomunicipal,
            "emitcnae"                 => $nfRef->emitcnae,
            "codcrt"                   => $nfRef->codcrt,
            "emitpaiscodigoibge"       => $nfRef->emitpaiscodigoibge,
            "emitpaisnome"             => "Brasil",
            "emitufcodigoibge"         => $nfRef->emitufcodigoibge,
            "emituf"                   => $nfRef->emituf,
            "emitcidadecodigoibge"     => $nfRef->emitcidadecodigoibge,
            "emitcidadenome"           => $nfRef->emitcidadenome,
            "emitcidade_id"            => $nfRef->emitcidade_id,
            "emitbairro"               => $nfRef->emitbairro,
            "emitendereco"             => $nfRef->emitendereco,
            "emitnumero"               => $nfRef->emitnumero,
            "emitcep"                  => $nfRef->emitcep,
            "iddest"                   => $nfRef->iddest,
            "indfinal"                 => $nfRef->indfinal,
            "emitcomplemento"          => $nfRef->emitcomplemento,
            "emittelefone"             => $nfRef->emittelefone,
            "destcliente_id"           => $nfRef->destcliente_id,
            "destrazaosocial"          => $nfRef->destrazaosocial,
            "destie"                   => $nfRef->destie,
            "destcnpj"                 => $nfRef->destcnpj,
            "destcpf"                  => $nfRef->destcpf,
            "destindicadorie"          => $nfRef->destindicadorie,
            "destpaiscodigoibge"       => $nfRef->destpaiscodigoibge,
            "destpaisnome"             => $nfRef->destpaisnome,
            "destuf"                   => $nfRef->destuf,
            "destcidadecodigoibge"     => $nfRef->destcidadecodigoibge,
            "destcidadenome"           => $nfRef->destcidadenome,
            "destcidade_id"            => $nfRef->destcidade_id,
            "destbairro_id"            => $nfRef->destbairro_id,
            "destbairro"               => $nfRef->destbairro,
            "destendereco"             => $nfRef->destendereco,
            "destnumero"               => $nfRef->destnumero,
            "destcep"                  => $nfRef->destcep,
            "destcomplemento"          => $nfRef->destcomplemento,
            "desttelefone"             => $nfRef->desttelefone,
            "destemail"                => $nfRef->destemail,
            "fretemodalidade"          => 9,
            "freterazaosocial"         => "",
            "vicmsdeson"               => "",
            "vfcp"                     => "",
            "vfcpst"                   => "",
            "fretie"                   => "",
            "fretecnpj"                => "",
            "fretecpf"                 => "",
            "fretuf"                   => "",
            "fretecidadenome"          => "",
            "freteenderecocompl"       => "",
            "freteplacauf"             => "",
            "freteplaca"               => "",
            "vfrete"                   => "",
            "fretecentrocusto_id"      => "",
            "freteplanoconta_id"       => "",
            "produtos"                 => $itemsInfo['produtos'],
            "vbruto"                   => requestNumeroDecimalOracle($impFloat->vprod + $impFloat->vdesc - $impFloat->vfrete),
            "vbc"                      => $calculoImpostos->vbc,
            "vicms"                    => $calculoImpostos->vicms,
            "vbcst"                    => $calculoImpostos->vbcst,
            "vst"                      => $calculoImpostos->vst,
            "vcofins"                  => $calculoImpostos->vcofins,
            "vpis"                     => $calculoImpostos->vpis,
            "vipi"                     => $calculoImpostos->vipi,
            "vseg"                     => $calculoImpostos->vseg,
            "vdesc"                    => $calculoImpostos->vdesc,
            "vnf"                      => $calculoImpostos->vnf,
            "descricaofinanceiro"      => " NFe complementar",
            "condicaopagamento_id"     => $nfRef->condicaopagamento_id,
            "centrocusto_id"           => $nfRef->centrocusto_id,
            "planoconta_id"            => $nfRef->planoconta_id,
            "totalqtdeprodutos"        => $itemsInfo['qtde'],
            "vprod"                    => $calculoImpostos->vprod,
            "voutro"                   => $calculoImpostos->voutro,
            "rateiocentrocusto_id"     => "",
            "rateiovalor"              => "",
            "rateioplanoconta_id"      => "",
            "chaveacessoref"           => $nfRef->chaveacesso
        ];

        $request = new NfRequest();
        $request->replace($data);

        return $request;
    }

    /**
     * @param string $produtosJson
     * @param Nfemitidaitem $originalItems
     * @return array
     * @throws Exception
     *  Cria os objetos de produtos de forma parecida com o que é gerado na view
     *
     */
    private function prodNfComplementar($produtosJson, $originalItems)
    {
        $produtosJson = json_decode($produtosJson);
        $produtosRequest = collect([]);
        $totalValue = 0;
        $totalQtde = 0;

        foreach ($produtosJson as $prodJson) {
            if (is_string($prodJson)) {
                $prodJson = decodeAssociativeArray($prodJson);
            }
            $qde = $prodJson[3];
            $value = $prodJson[2];
            $totalQtde += $qde;
            $totalValue += insertNumeroDecimalOracle($value);

            $item = $originalItems->where('id', $prodJson[0])->first();

            if (is_null($item))
                throw new Exception("Item não encontrado na Nota Fiscal referenciada.");

            if ($qde != 0 || insertNumeroDecimalOracle($value) > 0) {

                $produtoDb = $item->produto;
                if (is_null($produtoDb)) {
                    throw new Exception("Produto não encontrado.");
                }

                $produtoToRequest = array();
                $produtoToRequest[0] = $item->nfoperacao_id;
                $produtoToRequest[1] = $item->nfoperacao->descricao;
                $produtoToRequest[2] = $item->setor_id;
                $produtoToRequest[4] = $produtoDb->id;
                $produtoToRequest[5] = $produtoDb->descricao;
                $produtoToRequest[6] = $value;
                $produtoToRequest[7] = $qde;
                $produtoToRequest[11] = $produtoDb->ncm;
                $produtoToRequest[12] = $produtoDb->unidademedida->sigla;
                $produtoToRequest[13] = $produtoDb->ean;
                $produtoToRequest[14] = $produtoDb->nfeextipi;
                $produtoToRequest[15] = $produtoDb->nfcest;
                $produtoToRequest[16] = $produtoDb->nfedescricaofiscal;
                $produtoToRequest[17] = $produtoDb->nfgrupofiscal_id;
                $produtoToRequest[18] = $qde; //qVol
                $produtoToRequest[19] = $qde * $produtoDb->pesoliquido;
                $produtoToRequest[20] = $qde * $produtoDb->pesobruto;
                $produtoToRequest[21] = $item->pgni;
                $produtoToRequest[22] = $item->pgnn;
                $produtoToRequest[23] = $item->pglp;

                $produtosRequest->push(encodeAssociativeArray($produtoToRequest));
            }
        }

        if ($produtosRequest->count() === 0)
            throw new Exception("Não houve nenhuma alteração nos produtos para gerar a NFe complementar.");

        return ['produtos' => $produtosRequest->toJson(), 'qtde' => $totalQtde, 'value' => $totalValue];
    }

    /**
     * @param Request $request
     * @param $pedido_id
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function processorPedidoNf(Request $request, $pedido_id)
    {
        $this->authorize('especial', Pedido::class);
        DB::beginTransaction();
        try {
            $data = $request->all();
            $pedido = Pedido::findOrFail($pedido_id);

            $empresa = Empresa::findOrFail($pedido->empresa_id);
            $empresaconfig = Empresaconfig::where('empresa_id', $pedido->empresa_id)->first();
            $cliente = Cliente::with('telefones', 'bairro', 'cidade', 'rua', 'convenioempresa.clienteConvenio')->find($empresaconfig->nfcecliente_id);
            $clientepedido = Cliente::with('telefones', 'bairro', 'cidade', 'rua')->find($pedido->cliente_id);
            $nfoperacao = Nfoperacao::findOrFail($empresaconfig->nfoperacoes_id);
            $pedidoemitenfce = $empresaconfig->pedidoemitenfce;
            $fretemodalidade = $empresaconfig->fretemodalidade;

            $presencacomprador = $empresaconfig->presencacomprador;

            $data['transportador_id'] = (int) $data['transportador_id'];
            $this->transportadora = DB::table('clientes as c')
                ->join('cidades as cid', 'cid.id', 'c.cidade_id')
                ->where('c.id', $data['transportador_id'])
                ->select('cid.descricao as cidade', 'c.complemento', 'c.id', 'c.inscricao_estadual', 'c.nome', 'c.cnpj', 'c.cpf', 'c.uf')->get()
                ->first();

            if ($presencacomprador == 4 && is_null($this->transportadora))
                throw new Exception("Erro ao buscar a transportadora.");

            $data['transportador_id'] = $data['transportador_id'] === 0 ? null : $data['transportador_id'];

            if ($data['formapagamento']) {
                $data['fretecondicaopagamento_id'] = $data['fretecondicaopagamento_id'] === 'null' ? null : $data['fretecondicaopagamento_id'];
            } else {
                $data['fretecondicaopagamento_id'] = null;
            }

            $ccfrete_id = $presencacomprador == 4 ? $empresaconfig->ccfrete_id : '';
            $pcfrete_id = $presencacomprador == 4 ? $empresaconfig->pcfrete_id : '';
            //quando a presença é 4, a taxa de entrega vira o frete
            $vFrete = $presencacomprador == 4 ? $pedido->entregataxa : 0;
            if ($pedidoemitenfce) {

                $data["grupo_id"] = $pedido->grupo_id;

                $data["nfmodelo"] = 65;
                $data["nftipoambiente"] = $empresa->nfcetipoambiente;
                $data["datahoraemissao"] = requestDataOracle(Carbon::now()->toDateTimeString()); // requestDataOracle($pedido->datahoraprevisaoentrega); Alterado por requisicao do gas, porque as notas sao criadas em horarios diferentes
                $data["datahoraentradasaida"] = requestDataOracle(Carbon::now()->toDateTimeString()); // requestDataOracle($pedido->datahoraprevisaoentrega); Alterado por requisicao do gas, porque as notas sao criadas em horarios diferentes
                $data["nfefinalidade"] = 1; //1-Normal
                $data["presencacomprador"] = $presencacomprador; //1 - Operação PresencialF
                $data["nfoperacao_id"] = $nfoperacao->id;
                $data["empresa_id"] = $empresa->id;
                $data["informacaocomplementar"] = isset($data["appinformacaocomplementar"]) ? $data["appinformacaocomplementar"] : "";
                $data["informacaoadicionalfisco"] = $nfoperacao->informacoesadicionalfisco;

                $data["emitrazaosocial"] = $empresa->razao_social;
                $data["emitnomefantasia"] = $empresa->nome_fantasia;
                $data["emitie"] = $empresa->inscricao_estadual;
                $data["emitcnpj"] = $empresa->cnpj;
                $data["emitcpf"] = "";
                $data["emitinscricaomunicipal"] = $empresa->inscricao_municipal;
                $data["emitcnae"] = $empresa->inscricao_municipal ? $empresa->cnae : "";
                $data["codcrt"] = $empresa->nfcecrt;
                $data["emitpaiscodigoibge"] = $empresa->codigoibgepais;
                $data["emitpaisnome"] = "Brasil";
                $data["emitufcodigoibge"] = $empresa->estado->cod_ibge;
                $data["emituf"] = $empresa->estado->uf;
                $data["emitcidadecodigoibge"] = $empresa->cidade->cod_ibge;
                $data["emitcidadenome"] = $empresa->cidade->descricao;
                $data["emitcidade_id"] = $empresa->cidade->id;
                $data["emitbairro"] = $empresa->bairro->descricao;
                $data["emitendereco"] = $empresa->rua->descricao;
                $data["emitnumero"] = $empresa->numero;
                $data["emitcep"] = $empresa->cep;
                $data["emitcomplemento"] = $empresa->complemento;
                $data["emittelefone"] = $empresa->telefone1;
                $data["nfc_tpag"] = $request->get("nfc_tpag", null);
                if (!is_null($pedido->cliente->convenioempresa) && $pedido->cliente->convenioempresa->clienteConvenio) {
                    $data['conveniovencimento'] = $pedido->cliente->convenioempresa->clienteConvenio->diavencimento;
                }

                if ($pedido->nftipo == 0) {
                    //cliente não identificado
                    $cliente->cnpj = "";
                    $cliente->cpf = "";
                    $pedido->nfcpfcnpj = preg_replace('/[^0-9]/', '', $pedido->nfcpfcnpj);
                    if (strlen($pedido->nfcpfcnpj) == 11) {
                        $cliente->cpf = $pedido->nfcpfcnpj;
                    } else {
                        $cliente->cnpj = $pedido->nfcpfcnpj;
                    }
                } elseif ($pedido->nftipo == 1) {
                    //identificado
                    $cliente = $clientepedido;
                } elseif ($pedido->nftipo == 2) {
                    //presença do comprador 4
                    $cliente = $clientepedido;
                }
                if (!$cliente->cep) {
                    $cliente->cep = str_replace("-", "", $pedido->entregacep);
                }

                $data = $this->getDataDest($cliente, $data);
                $data["destpaiscodigoibge"] = $empresa->codigoibgepais; // RECEBE DA EMPRESA
                $data["fretemodalidade"] = $fretemodalidade;
                $data["fretecpf"] = $this->getDataTransp('cpf');
                $data["fretecnpj"] = $this->getDataTransp('cnpj');
                $data["freterazaosocial"] = $this->getDataTransp('nome');
                $data["fretie"] = $this->getDataTransp('inscricao_estadual');
                $data["fretuf"] = $this->getDataTransp('uf');
                $data["fretecidadenome"] = $this->getDataTransp('cidade');
                $data["freteenderecocompl"] = $this->getDataTransp('complemento');
                $data["fretecliente_id"] = $this->getDataTransp('id');
                $data["fretecentrocusto_id"] = $ccfrete_id;
                $data["freteplanoconta_id"] = $pcfrete_id;

                $processaItems = $this->processaItemsPedido($pedido, $nfoperacao);

                if (!is_array($processaItems)) {
                    throw new Exception($processaItems);
                }

                $produtos = json_encode($processaItems[0]);
                $totalvalorproduto = $processaItems[1];

                $pars = [
                    "prod"       => $produtos,
                    "destuf"     => $data['emituf'], //emituf por conta de uma NFC-e ser somente com operação interna
                    "cliente_id" => $data['destcliente_id'],
                    "indfinal"   => 1,
                    "iddest"     => 1,
                    "empresa_id" => $pedido->empresa_id,
                    "vprod"      => $totalvalorproduto,
                    "vfrete"     => $vFrete,
                    "vipi"       => "R$ 0,00",
                    "vseg"       => "R$ 0,00",
                    "voutro"     => "R$ 0,00",
                    "nfmodelo"   => "65",
                    "tiponf"     => "E",
                    'vdesc'      => requestNumeroDecimalOracle($pedido->valordesconto)
                ];

                $tributos = new NfeImpostoProcessor(null, $pars);
                $calculoImpostos = $tributos->calculaImposto(false);
                $impFloat = $calculoImpostos[1];
                $calculoImpostos = $calculoImpostos[0];

                $data["produtos"] = $produtos;
                $data['vprod'] = requestNumeroDecimalOracle($totalvalorproduto);
                $data['vfrete'] = $vFrete;
                $data['vseg'] = "R$ 0,00";
                $data['voutro'] = "R$ 0,00";
                $data['vicmsdeson'] = "R$ 0,00";
                $data['vfcp'] = "R$ 0,00";
                $data['vfcpst'] = "R$ 0,00";
                $data['vipi'] = "R$ 0,00";
                $data['vbruto'] = requestNumeroDecimalOracle($impFloat->vprod);
                $data['vdesc'] = requestNumeroDecimalOracle($pedido->valordesconto);
                $data['vnf'] = requestNumeroDecimalOracle($impFloat->vprod + $vFrete);

                $data["vbc"] = $calculoImpostos->vbc;
                $data["vicms"] = $calculoImpostos->vicms;
                $data["vbcst"] = $calculoImpostos->vbcst;
                $data["vst"] = $calculoImpostos->vst;
                $data["vcofins"] = $calculoImpostos->vcofins;
                $data["vpis"] = $calculoImpostos->vpis;
                $data["iddest"] = "1";
                $data["indfinal"] = "1";

                $data['descricaofinanceiro'] = "Pedido " . $pedido->id;
                $data['condicaopagamento_id'] = $pedido->condicaopagamento_id;
                $data['centrocusto_id'] = $empresaconfig->centrocusto_id;
                $data['planoconta_id'] = $empresaconfig->planoconta_id;
                $request = new Request();
                $request->replace($data);
                $nf = $this->processaStore($request->all(), $pedido->id);

                if ($nf instanceof Nfemitida) {

                    if (!$pedido->update(['nfcegerou' => true, 'nfce_id' => $nf->id]))
                        throw new Exception("NFCe foi gerada mas não foi possível vinculá-la ao pedido, contate o suporte.");

                    DB::commit();
                    return response()->json([
                        'status'    => "OK",
                        'msg'       => "NFC-e gerada: " . $nf->id . ":" . $nf->nfnumero,
                        'id'        => $this->nf_id,
                        'nfnumero'  => $nf->nfnumero
                    ]);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'status'    => "NO",
                        'msg'       => $nf,
                        'id'        => $this->nf_id
                    ]);
                }
            } else {
                throw new Exception("Erro: Configurações Gerais da empresa não permite emitir NFC-e pela tela de pedido.");
            }
        } catch (Exception $e) {
            DB::rollBack();
            Util::log($e->getTraceAsString(), $e->getCode());
            return response()->json([
                'status'    => "NO",
                'msg'       => $e->getMessage(),
                'id'        => 0
            ]);
        }
    }

    /**
     * passando o campo que precisa ser buscado, trata se o campo existe ou não para retorna-lo
     * @param string $field
     * @return string
     */
    private function getDataTransp($field)
    {
        if (is_null($this->transportadora))
            return '';
        return isset($this->transportadora->{$field}) ? $this->transportadora->{$field} : '';
    }

    /**
     *  Pega os dados do destinatário para gerar a NFC-e pela tela de pedidos
     * @param Cliente $cliente
     * @param array $data
     * @return array
     */
    private function getDataDest($cliente, $data)
    {
        $data["destcliente_id"] = $cliente->id;
        $data["destrazaosocial"] = $cliente->nome;
        $data["destie"] = $cliente->inscricao_estadual;
        $data["cliente_id"] = $cliente->id;
        $data["destindicadorie"] = $cliente->indicador_ie;
        $data["destpaisnome"] = "Brasil";
        $data["destuf"] = $cliente->uf;
        $data["destcidadecodigoibge"] = $cliente->cidade->cod_ibge;
        $data["destcidadenome"] = $cliente->cidade->descricao;
        $data["destcidade_id"] = $cliente->cidade->id;
        $data["destbairro"] = $cliente->bairro->descricao;
        $data["destendereco"] = $cliente->rua->descricao;
        $data["destnumero"] = $cliente->numero;
        $data["destcep"] = $cliente->cep;
        $data["destcomplemento"] = $cliente->complemento;
        $data["destcnpj"] = $cliente->cnpj;
        $data["destcpf"] = $cliente->cpf;
        $data["destemail"] = $cliente->email;

        if (count($cliente->telefones) > 0) {
            $data["desttelefone"] = $cliente->telefones[0]->telefone;
        } else {
            $data["desttelefone"] = "";
        }

        return $data;
    }

    /**
     * Gera os items do pedido de forma parecida da que é gerada na view para gravar a NFC-e
     * @param Pedido|Model $pedido
     * @param Nfoperacao|Model $nfoperacao
     * @return array|string
     */
    private function processaItemsPedido($pedido, $nfoperacao)
    {
        $produtos = [];
        $totalvalorproduto = 0;
        foreach ($pedido->pedidoitem as $pedidoitem) {
            $prod = Produto::find($pedidoitem->produto_id);
            $nfceconfigpedido = Nfceconfigpedido::where([
                ['nfoperacao_id', $nfoperacao->id],
                ['nfgrupofiscal_id', $prod->nfgrupofiscal_id]
            ])->get()->first();
            if ($nfceconfigpedido != null) {
                $produto = [];
                $produto[0] = $nfceconfigpedido->nfoperacaonova_id;
                $produto[1] = "";
                $produto[2] = $pedido->entregasetor_id;
                $produto[3] = "";
                $produto[4] = $prod->id;
                $produto[5] = $prod->descricao;
                $produto[6] = requestNumeroDecimalOracle($pedidoitem->precovendaunitario);
                $produto[7] = $pedidoitem->quantidade;
                $produto[8] = "";
                $produto[9] = $prod->pesoliquido;
                $produto[10] = $prod->pesobruto;
                $produto[11] = $prod->ncm;
                $produto[12] = $prod->unidademedida->sigla;
                $produto[13] = $prod->ean;
                $produto[14] = $prod->nfeextipi;
                $produto[15] = $prod->nfcest;
                $produto[16] = $prod->nfedescricaofiscal;
                $produto[17] = $prod->nfgrupofiscal_id;
                $produto[18] = intval($pedidoitem->quantidade);
                $produto[19] = number_format(($pedidoitem->quantidade * $prod->pesoliquido), 3, '.', '');
                $produto[20] = number_format(($pedidoitem->quantidade * $prod->pesobruto), 3, '.', '');
                $produto[21] = number_format($prod->pgni, 4, ',', '');
                $produto[22] = number_format($prod->pgnn, 4, ',', '');
                $produto[23] = number_format($prod->pglp, 4, ',', '');

                $produtos[] = $produto;

                $totalvalorproduto += $pedidoitem->quantidade * $pedidoitem->precovendaunitario;
            } else {
                return "Erro: Produto " . $prod->descricao . " não tem Configuração NFCe para Pedidos com seu Grupo Fiscal.";
            }
        }
        return [$produtos, $totalvalorproduto];
    }

    /**
     * @param $data
     * @return Nfemitida|\Illuminate\Database\Eloquent\Model|string
     */
    private function processaStore($data, $pedido_id = null)
    {
        try {
            $data = $this->trataNumNF($this->validateData($data));

            if (!is_null($pedido_id)) {
                $pedido = Pedido::findOrFail($pedido_id);

                if ($pedido->nfcegerou) {
                    throw new \Exception("Pedido já gerou uma Nota Fiscal, consulte novamente.");
                }
            }

            Util::validateCustom($data);

            $nfemitida = Nfemitida::create($data);

            $this->nf_id = $nfemitida->id;
            $nfemitida->verProc = '1.0.02';

            $this->gerarFinanceiro($data, $nfemitida);

            if ($nfemitida->formapagamento && $nfemitida->vfrete > 0) {
                $this->gerarFinanceiro($data, $nfemitida, true);
            }

            $processor = new NfProcessor($nfemitida);

            if ($processor->gerarXML() !== true) {
                throw new Exception("Erro ao gerar NF");
            }

            $nfemitida->update(["nfsituacao_id" => 2]); //Nota Fiscal Assinada
            return $nfemitida;
        } catch (NFeException $e) {
            DB::rollback();
            Util::log($e->getTraceAsString(), $e->getCode());
            return Util::treatNFeException($e) . " código do erro: " . $e->getCode();
        } catch (Exception $e) {
            Util::log($e->getTraceAsString(), $e->getCode());
            return $e->getMessage() . " código do erro: " . $e->getCode();
        }
    }

    /**
     * @param $id
     * @param Nfemitida $nots
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit($id, Nfemitida $nots)
    {
        $this->authorize('update', $nots);
        return $this->form($id);
    }

    /**
     * @param $id
     * @param Nfemitida $nots
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show($id, Nfemitida $nots)
    {
        $this->authorize('view', $nots);
        if (Input::get("await", false)) {
            sleep(3);
        }
        return $this->form($id, "show");
    }


    /**
     * @param Nfemitida $nots
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create(Nfemitida $nots)
    {
        $this->authorize('create', $nots);
        return $this->form(null, "create");
    }
    /**
     * @param $id
     * @param string $mode
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Exception
     */
    private function form($id, $mode = "edit")
    {
        $nfmodelos = Util::getNfModelos();
        $nftiposambiente = Util::getNfTipoAmbiente();
        $nffinalidade = Util::getNfFinalidade();
        $presencascomprador = Util::getPrecencaComprador();
        $fretemodalidades = Util::getFreteModalidades();

        $nfemitida = Util::getNfToForm($id, $mode, "emitida");
        $this->authorize('igualdade', $nfemitida);
        $nfemitidaitems = Util::getItemsToForm($id, $mode, "emitida");

        $empresa = SelectRepository::getEmpresaNfToView($nfemitida->empresa_id);

        $duplicate = Input::get("act", null) === "dup";
        if ($mode === "edit" && !$duplicate) {
            $processor = new NfProcessor($nfemitida, $empresa);
            $processor->validateEstornoFinanceiro();
            $valid = $this->validateStatusNfEdit($nfemitida);
            if ($valid instanceof RedirectResponse) {
                return $valid;
            }
        } elseif ($duplicate && !Auth::user()->support) {
            Session::flash("message_info", "Sem permissões para realizar a operação");
            Redirect::route("nfemitida.index");
        }

        if ($nfemitida) {
            $nfemitida->destcep = str_pad($nfemitida->destcep, 8, "0", STR_PAD_LEFT);
            $nfemitida->emitcep = str_pad($nfemitida->emitcep, 8, "0", STR_PAD_LEFT);
        }

        $nfoperacaos = SelectRepository::getOperacaoToView($empresa, "emitida");
        $transportadoras = SelectRepository::getTransportadorasToView($empresa);
        $setores = SelectRepository::getSetoresToView($empresa);
        $condicaopagamentos = SelectRepository::getCondPgtoToView($empresa, false);

        $centrocustos = Centrocusto::menuspermissoesAll();
        $planocontas = collect(Planoconta::menuspermissoesAll());
        $planocontasR = $planocontas->where("pagarreceber", "R");
        $planocontasD = $planocontas->where("pagarreceber", "D");

        $nfemitida = Util::adjustValuesToForm($nfemitida, $nfemitidaitems);

        $produtos = SelectRepository::getProdutoNf($nfemitida->empresa_id);

        $fields = [
            'nfmodelos'                   => $nfmodelos,
            'contingenciaemissao'         => $empresa->contingenciaemissao,
            'nftiposambiente'             => $nftiposambiente,
            'nffinalidade'                => $nffinalidade,
            'nfoperacaos'                 => $nfoperacaos,
            'empresa'                     => $empresa,
            'nfc_tpag'                    => Util::getTPag(false),
            'presencascomprador'          => $presencascomprador,
            'fretemodalidades'            => $fretemodalidades,
            'transportadoras'             => $transportadoras,
            'setores'                     => $setores,
            'produtos'                    => $produtos,
            'condicaopagamentos'          => $condicaopagamentos->pluck('descricao', 'id')->prepend('Selecione', ''),
            'planocontas'                 => $planocontas,
            'centrocustos'                => $centrocustos,
            'fretecentrocusto_descricao'  => strval($nfemitida->fretecc),
            'fretecentrocusto_id'         => $nfemitida->fretecentrocusto_id,
            'freteplanoconta_descricao'   => strval($nfemitida->fretepc),
            'freteplanoconta_id'          => $nfemitida->freteplanoconta_id,
            'centrocusto_descricao'       => strval($nfemitida->cc),
            'centrocusto_id'              => $nfemitida->centrocusto_id,
            'planoconta_descricao'        => strval($nfemitida->pc),
            'planoconta_id'               => $nfemitida->planoconta_id,
            'empresa_id'                  => $nfemitida->empresa_id,
            'planocontasR'                => $planocontasR,
            'planocontasD'                => $planocontasD,
            'tiponf'                      => 'emitida',
            'cProdAnpGLP'                 => Util::getCProdAnpGLP(),
            'cfopGLP'                     => Util::getCFOPGLP(),
            'cfopVenda'                   => Util::getCfopVenda(),
            'condicoesPgtoDB'             => Util::getCfopVenda(),
            'cliente_config'              => $this->getClienteConfig()
        ];

        if ($mode === "show") {
            $fields['show'] = true;
        }

        if ($duplicate) {
            $fields['duplicate'] = 1;
        }

        if ($mode !== "create") {
            Util::setFieldsFormShowEdit($nfemitida, $nfemitidaitems, $fields, "emitida");
        }

        return view('nf.nfe_form', $fields);
    }

    /**
     * @param NfRequest $request
     * @param $id
     * @return NfemitidaController|Model|RedirectResponse|string
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(NfRequest $request, $id)
    {
        $duplicate = $request->get("duplicate", false);
        if ($duplicate && Auth::user()->support) {
            $not = new Nfemitida();
            return $this->store($request, $not);
        }
        $url = Util::getUrlPostWrite('emitida', $id);
        $nfemitida = Nfemitida::with('nfEmitidaItem')->find($id);

        $this->authorize('update', $nfemitida);
        $this->authorize('igualdade', $nfemitida);

        if ($nfemitida->nfSituacao == null) {
            $nfemitida->nfsituacao_id = 1;
        } else {
            $sit = (int) $nfemitida->nfSituacao->id;
            $error = in_array($sit, [100, 135]) && !$nfemitida->xmlretornocancelamento ? "autorizada" : ($sit === 101 ? "cancelada" : null);
            if (!is_null($error)) {
                Session::flash("message_info", "NF está $error na receita e não pode ser alterada.");
                return Redirect::to($url);
            }
        }

        try {
            DB::beginTransaction();
            $data = $this->validateData($request->all());
            $data["nfmodelo"] = $nfemitida->nfmodelo;
            Util::revisionItems($nfemitida, $data, "produtosJson", "NFEmitidaItems");
            Util::generateRateioLog($nfemitida, $data, "rateios", "NFEmitidaRateios");

            if (isset($data['chaveacesso']))
                unset($data['chaveacesso']);

            Util::validateCustom($data);

            $original = clone $nfemitida;

            $nfemitida->update($data);

            $this->estornoNfEmitidaItem($original);

            $processor = new NfProcessor($nfemitida);

            if ($processor->checkForChangesFinanceiro($original, $data)) {
                $this->estornarFinanceiro($nfemitida);
                $this->gerarFinanceiro($data, $nfemitida);
            }

            if ($processor->checkForChangesFinanceiroFrete($original, $data)) {
                $this->estornarFinanceiro($nfemitida, true);
                if ($nfemitida->formapagamento && $nfemitida->vfrete > 0) {
                    $this->gerarFinanceiro($data, $nfemitida, true);
                }
            }

            $processor->updateRateio($data);

            $nfemitida->verProc = '1.0.02';

            if ($processor->gerarXML() !== true) {
                throw new Exception("Erro ao atualizar NF");
            }

            //Nota Fiscal Assinada
            $nfemitida->update(["nfsituacao_id" => 2]);

            DB::commit();
            Session::flash("message_success", 'NF atualizada com sucesso!');
            return Redirect::to($url);
        } catch (NFeException $e) {
            DB::rollback();
            Util::log($e->getTraceAsString(), $e->getCode());
            $m = Util::treatNFeException($e);
            return Redirect::back()->withErrors($m . " código do erro: " . $e->getCode())->withInput();
        } catch (Exception $e) {
            DB::rollback();
            Util::log($e->getTraceAsString(), $e->getCode());
            return Redirect::back()->withErrors($e->getMessage() . " código do erro: " . $e->getCode())->withInput();
        }
    }

    /**
     * Estornando o Financeiro
     *
     * @param Nfemitida $nfemitida
     * @param bool $frete
     * @throws Exception
     */
    private function estornarFinanceiro($nfemitida, $frete = false)
    {
        $processor = new NfProcessor($nfemitida);

        if ($frete) {
            $financeiroFreteModel = $nfemitida->freteFinanceiro;
            $nfemitida->update(['fretefinanceiro_id' => null]);
            if (!is_null($financeiroFreteModel)) {
                $processor->estornarFinanceiroModel($financeiroFreteModel, $nfemitida);
            }
        } else {
            $financeiroModel = $nfemitida->financeiro;
            $nfemitida->update(['financeiro_id' => null]);

            if (!is_null($financeiroModel)) {
                $processor->estornarFinanceiroModel($financeiroModel, $nfemitida);
            }

            $nfemitida->nfEmitidaParcela()->delete();
        }
    }

    /**
     * @param array $data
     * @param Nfemitida $nfemitida
     * @param bool $frete
     * @return bool
     * @throws Exception
     */
    private function gerarFinanceiro($data, $nfemitida, $frete = false)
    {
        $data["condicaopagamento_id"] = isset($data["condicaopagamento_id"]) ? $data["condicaopagamento_id"] : null;
        $condicaopagamento_id = !$frete ? $data["condicaopagamento_id"] : $data["fretecondicaopagamento_id"];
        $condicaopagamento = DB::table("condicaopagamentos")->where('id', $condicaopagamento_id)->get()->first();
        $err = $frete ? ' do frete.' : '.';
        $nfoperacao = Nfoperacao::find($data['nfoperacao_id']);
        if ($nfoperacao->movimentafinanceiro != 0 && is_null($condicaopagamento))
            throw new Exception("Não foi possivel localizar a condição de pagamento $condicaopagamento_id" . $err);
        // tipo = 5-Vale Gás
        $isValeGas = !is_null($condicaopagamento) && (int) $condicaopagamento->tipo === 5;
        // movimentafinanceiro = 0-Nada 1-Pagar 2-Receber
        if (($nfoperacao->movimentafinanceiro == 0 || $isValeGas) && !$frete) {
            return false;
        } else {
            $processor = new NfProcessor($nfemitida);
            $date = Carbon::parse($nfemitida->datahoraemissao);

            if ($frete && $nfemitida->vfrete > 0) {
                $total = $nfemitida->vfrete;
                $desconto = 0;
                $cliente_id = $data["fretecliente_id"];
                $descricao_lanc = "Frete da Nota Fiscal N°:" . $nfemitida->nfnumero . ", id:" . $nfemitida->id . ". " . $nfemitida->descricaofinanceiro;
                $condicaopagamento_id = $data["fretecondicaopagamento_id"];

                $planoconta_id = $nfemitida->freteplanoconta_id;
                $centrocusto_id = $nfemitida->fretecentrocusto_id;

                if (!$centrocusto_id || !$planoconta_id) {
                    $msg = "Informe um plano de contas e/ou centro de custos para o frete.";
                    throw new Exception($msg);
                }

                if ($nfemitida->formapagamento == "1") {
                    $pagarreceber = "P";
                } elseif ($nfemitida->formapagamento == "2") {
                    $pagarreceber = "R";
                } else {
                    //Não gera financeiro
                    return false;
                }
            } else {
                $total = $nfemitida->vnf - $nfemitida->vfrete;
                $desconto = $nfemitida->vdesc;
                $cliente_id = $data["cliente_id"];
                $descricao_lanc = "Nota Fiscal N°:" . $nfemitida->nfnumero
                    . ", id:" . $nfemitida->id . ". " . $nfemitida->descricaofinanceiro;
                $condicaopagamento_id = $data["condicaopagamento_id"];
                $centrocusto_id = $nfemitida->centrocusto_id;
                $planoconta_id = $nfemitida->planoconta_id;
                // 0-Nada 1-Pagar 2-Receber
                $pagarreceber = $nfoperacao->movimentafinanceiro == 1 ? "P" : "R";
            }

            $conveniovencimento = isset($data['conveniovencimento']) ? $data['conveniovencimento'] : null;
            $parcelas = calculoParcelas($condicaopagamento_id, $date, $total, $condicaopagamento, $conveniovencimento);
            return $processor->gerarFinanceiro([
                'total'                => $total,
                'desconto'             => $desconto,
                'parcelas'             => $parcelas,
                'data'                 => $data,
                'cliente_id'           => $cliente_id,
                'descricao_lanc'       => $descricao_lanc,
                'condicaopagamento_id' => $condicaopagamento_id,
                'centrocusto_id'       => $centrocusto_id,
                'planoconta_id'        => $planoconta_id,
                'pagarreceber'         => $pagarreceber,
                'nf'                   => $nfemitida,
                'nfbd'                 => $nfemitida,
                'frete'                => $frete
            ]);
        }
    }

    /**
     * @param $data
     * @return array
     * @throws Exception
     */
    protected function validateData($data)
    {
        $data['nftipoemissao'] = Empresa::find($data['empresa_id'])->nfetipoemissao;
        $produtos = json_decode($data["produtos"]);
        foreach ($produtos as &$prod) {
            if (is_string($prod)) {
                $prod = decodeAssociativeArray($prod);
            }
            $prod[8] = "";
        }
        $data["produtos"] = json_encode($produtos);

        if (array_key_exists("nfc_tpag", $data) && $data["nfmodelo"] == 55) {
            unset($data["nfc_tpag"]);
        }

        if (!isset($data['nfefinalidade']) || (isset($data['nfefinalidade']) && strlen($data['nfefinalidade']) === 0))
            throw new Exception("O campo Finalidade NF é obrgatório");

        return Util::validateDataGeneral($data);
    }

    /**
     * @param $data
     * @return mixed
     * @throws Exception
     */
    protected function trataNumNF($data)
    {
        $nftipoambiente = $data["nftipoambiente"];
        $nfmodelo = $data['nfmodelo'];
        $empresa = Empresa::lockForUpdate()->find($data['empresa_id']);

        if (is_null($empresa)) {
            throw new Exception("Empresa não encontrada no banco de dados");
        }

        if ((int) $nftipoambiente == 1) { // Produção
            if ((int) $nfmodelo == 55) { // NF-e
                $data["nfnumero"] = $empresa->nfenumero + 1; //'4064'; // numero da NFe
                $empresa->update(["nfenumero" => $data["nfnumero"]]);
            } elseif ((int) $nfmodelo == 65) { // NFC-e
                $data["nfnumero"] = $empresa->nfcenumero + 1; //'4064'; // numero da NFe
                $empresa->update(["nfcenumero" => $data["nfnumero"]]);
            }
        } elseif ((int) $nftipoambiente == 2) { // Homologação
            if ((int) $nfmodelo == 55) { // NF-e
                $data["nfnumero"] = $empresa->nfenumerohomologacao + 1; //'4064'; // numero da NFe
                $empresa->update(["nfenumerohomologacao" => $data["nfnumero"]]);
            } elseif ((int) $nfmodelo == 65) { // NFC-e
                $data["nfnumero"] = $empresa->nfcenumerohomologacao + 1; //'4064'; // numero da NFe
                $empresa->update(["nfcenumerohomologacao" => $data["nfnumero"]]);
            }
        }
        if ((int) $nfmodelo == 55) // NF-e
            $data["nfserie"] = $empresa->nfeserie;
        elseif ((int) $nfmodelo == 65) // NFC-e
            $data["nfserie"] = $empresa->nfceserie;

        return $data;
    }

    /**
     * @return object|\stdClass|string
     * @throws Exception
     */
    public function transmitirnf($id = -1)
    {
        if ($id == -1) {
            $id = (int) Input::get('id', 0);
        }
        $sefazEvento = new SefazEvento($id);
        $nf = $sefazEvento->getNf();
        $sit = $nf->nfsituacao_id;

        if ($sit == 100 || $sit == 3) {
            return "NF já transmitida";
        }

        $result = $sefazEvento->evento('TRANSMITIR');
        sleep(3);
        return $result;
    }

    /**
     * Consulta a Nf na sefaz
     * @param Nfemitida $nots
     * @return string
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function consultarnf(Nfemitida $nots)
    {
        $getFile = (bool) Input::get('getFile', false);
        try {
            $id = (int) Input::get('id', 0);
            $sefazEvento = new SefazEvento($id);
            $nf = $sefazEvento->getNf();
            if ((int) $nf->nfsituacao_id === 100) {
                $getFile = true;
            }
        } catch (Exception $e) {
            return "Erro ao buscar NF-e: " . $e->getMessage();
        }
        if (!$getFile) {
            $this->authorize("especial", $nots);
        } else {
            $this->authorize("view", $nots);
        }
        DB::beginTransaction();
        try {
            $fromPedidos = (bool) Input::get('fromPedidos', false);

            $validate = $this->validateSitConsulta($nf);
            $cStat = $validate->cStat;
            $xMotivo = $validate->xMotivo;

            if (!$getFile && $validate->allowedConsulta) {
                $result = $sefazEvento->evento('CONSULTAR');
                $cStat = (int) $result->cStat;
                $xMotivo = $result->xMotivo;
            }

            if (Util::isAuthorized($cStat) && $cStat !== 135) {
                $sefazEvento->addAuthorized()->emiteDanfe($fromPedidos, $cStat);
                DB::commit();
                return $xMotivo . " Status: " . $cStat;
            } elseif (in_array($cStat, [135, 136])) {
                DB::commit();
                return $sefazEvento->emiteDanfe($fromPedidos, $cStat);
            } elseif ($cStat > 100) {
                DB::commit();
                return $xMotivo . " Status: " . $cStat;
            } else {
                DB::commit();
                return "Sem retorno ao consultar recibo da NF. $xMotivo Status: $cStat";
            }
        } catch (Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
    }
    public function consultarnfAppNF(Nfemitida $nots, $id = -1, $downloadpdf = false)
    {
        $getFile = (bool) Input::get('getFile', false);
        try {
            if ($id == -1) {
                $id = (int) Input::get('id', 0);
            }
            $sefazEvento = new SefazEvento($id);
            $nf = $sefazEvento->getNf();

            if ((int) $nf->nfsituacao_id === 100) {
                $getFile = true;
            }
        } catch (Exception $e) {
            return ['erro' => true,  'msg' => "Erro ao buscar NF-e: " . $e->getMessage() . " " . $e->getFile() . " " . $e->getLine()];
        }
        if (!$getFile) {
            $this->authorize("especial", $nots);
        } else {
            $this->authorize("view", $nots);
        }
        DB::beginTransaction();
        try {
            $fromPedidos = (bool) Input::get('fromPedidos', false);

            if ($downloadpdf) {
                $fromPedidos = true;
            }

            $validate = $this->validateSitConsulta($nf);

            $cStat = $validate->cStat;
            $xMotivo = $validate->xMotivo;

            if (!$getFile && $validate->allowedConsulta && $validate->hasRecibo) {
                $result = $sefazEvento->evento('CONSULTAR');
                $cStat = (int) $result->cStat;
                $xMotivo = $result->xMotivo;
            }

            if (Util::isAuthorized($cStat) && $cStat !== 135) {
                $ret = $sefazEvento->addAuthorized()->emiteDanfe($fromPedidos, $cStat, true);
                DB::commit();
                if ($downloadpdf) {
                    return $ret;
                } else {
                    return ['erro' => false,  'msg' => '', 'data' => $ret, 'cStat' => $cStat];
                }
            } elseif (in_array($cStat, [135, 136])) {
                DB::commit();
                $ret =  $sefazEvento->emiteDanfe($fromPedidos, $cStat, true);
                if ($downloadpdf) {
                    return $ret;
                } else {
                    return ['erro' => false,  'msg' => '', 'data' => $ret, 'cStat' => $cStat];
                }
            } elseif ($cStat > 100) {
                DB::commit();
                return ['erro' => true,  'msg' => $xMotivo . " Status: " . $cStat, 'cStat' => $cStat];
            } else {
                DB::commit();
                return ['erro' => true,  'msg' => "Sem retorno ao consultar recibo da NF. $xMotivo Status: $cStat"];
            }
        } catch (Exception $e) {
            DB::rollback();
            return ['erro' => true,  'msg' => $e->getMessage()];
        }
    }

    /**
     * @param $nf
     * @return object
     * @throws Exception
     */
    private function validateSitConsulta($nf)
    {
        $nfsituacao = Nfsituacao::find($nf->nfsituacao_id);

        if (is_null($nfsituacao)) {
            throw new Exception("Situação de NF não encontrada");
        }

        $cStat = (int) $nfsituacao->id;
        if ($cStat <= 2) {
            //1 = Não transmitida, 2 = Nota Fiscal Assinada
            throw new Exception("É preciso transmitr a NF antes.");
        }

        //3 = Nota Fiscal Transmitida,
        //100 = Autorizado o uso da NF-e
        //150 = Autorizado o uso da NF-e
        //105 = Lote em processamento,
        //635 = Rejeição: NF-e com mesmo número e série já transmitida e aguardando processamento
        $allowedConsulta = in_array($cStat, [3, 100, 105, 150, 635]);
        $hasRecibo = !is_null($nf->numeroreciboenvio) || $nf->numeroreciboenvio != 0;
        if ($allowedConsulta && !$hasRecibo && $cStat !== 100) {
            $msg = "NF não tem o número de recibo da transmição para "
                . "atualizar status/imprimir, possível erro no processo de transmição.";
            throw new Exception($msg);
        }

        return (object) [
            'cStat'             => $cStat,
            'xMotivo'           => $nfsituacao->msgerroreceita,
            'allowedConsulta'   => $allowedConsulta,
            'hasRecibo'         => $hasRecibo
        ];
    }

    /**
     * @param Nfemitida $nots
     * @return string
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function inutilizarFaixaNFs(Nfemitida $nots)
    {
        $this->authorize('create', $nots);
        try {
            DB::beginTransaction();
            $nfmodelo = Input::get('nfmodelo', 0);
            $xJust =  Input::get('xJust', 0);
            $nIni =  Input::get('nIni', 0);
            $nFin =  Input::get('nFin', 0);

            $empresa = Empresa::find((int) Input::get('empresa_id', 0));

            if (is_null($empresa)) {
                throw new Exception("Não foi possível encontrar a empresa");
            }

            $this->validateNumInutilizacao($empresa, $nfmodelo, $nIni, $nFin);

            $emitcnpj = str_replace(['.', '/', '-'], '', $empresa->cnpj);

            if ($nfmodelo == 55) {
                $nSerie = $empresa->nfeserie;
                $tpAmb = $empresa->nfetipoambiente;
            } else if ($nfmodelo == 65) {
                $nSerie = $empresa->nfceserie;
                $tpAmb = $empresa->nfcetipoambiente;
            } else {
                throw new Exception("Modelo de NF-e inválido");
            }

            $data = [];
            $data["empresa_id"] = $empresa->id; //precisa mandar da view ***********
            $data["empresa"] = $empresa;
            $data["grupo_id"] = $empresa->grupo_id; //precisa mandar da view ***********
            $data["user_id"] = \Auth::user()->id;
            $data["datahora"] = Carbon::now();
            $data["xjust"] = $xJust;
            $data["nftipoambiente"] = $tpAmb;
            $data["nini"] = $nIni;
            $data["nfin"] = $nFin;
            $data["nfmodelo"] = $nfmodelo;
            $data["emitcnpj"] = $emitcnpj;
            $data["nfeserie"] = $nSerie;
            $data["nfetipoambiente"] = $tpAmb;
            $evento = new SefazEvento(null);

            $evento->setAmbiente($tpAmb);
            $evento->initTools(null, $emitcnpj, $nfmodelo, $empresa);
            $evento->setModelo($nfmodelo);

            $retorno = $evento->inutilizarFaixaNFs($data);
            if (strpos($retorno, 'Problemas com a inutilização') !== false)
                throw new Exception($retorno);
        } catch (Exception $e) {
            DB::rollback();
            return getErrorsException($e);
        }
        DB::commit();
        return $retorno;
    }

    /**
     * @param Nfemitida $nots
     * @return object|\stdClass|string
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function cancelarNF(Nfemitida $nots)
    {
        $this->authorize('especial', $nots);
        $id = (int) Input::get('id', 0);
        $xJust = Input::get('just', '');
        $sefazEvento = new SefazEvento($id);
        $callbackSuccess = function () use ($sefazEvento) {
            $this->estornoFinanceiroEstoque($sefazEvento->getNf(), "", false);
            $sefazEvento->getNf()->update(['nfsituacao_id' => 135]);
        };
        return $sefazEvento->evento('CANCELAR', $xJust, $callbackSuccess);
    }

    /**
     * @param Nfemitida $nots
     * @return object|\stdClass|string
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function cartaCorrecaoNF(Nfemitida $nots)
    {
        $create = (bool) Input::get('create', false);
        if ($create) {
            $this->authorize("especial", $nots);
        } else {
            $this->authorize("view", $nots);
        }
        $id = (int) Input::get('id', 0);
        $xCorrecao = Input::get('correcao', '');
        $hash = Input::get('hash', null);

        if (is_null($hash) && $create)
            throw new Exception("Ocorreu um erro ao processar a Carta de Correçao");

        $sefazEvento = new SefazEvento($id);
        return $sefazEvento->evento('CCE', $xCorrecao, $create, $hash);
    }

    /**
     * @param $id
     * @param Nfemitida $nots
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function enviarEmailNF($id, Nfemitida $nots)
    {
        $this->authorize('especial', $nots);
        try {
            $nfemitida = Nfemitida::find($id);
            $nfsituacao = Nfsituacao::find($nfemitida->nfsituacao_id);
            $cStat = $nfsituacao->id;

            if ($cStat == 1 || $cStat == 2)
                return response()->json(["É preciso transmitr a NF antes."]);

            $evento = new SefazEvento($nfemitida);
            $retorno = $evento->enviarMailNF();

            if ($retorno["cStat"] == 999999)
                return response()->json("OK|Email enviado com sucesso!");
            else
                return response()->json($retorno['xMotivo']);
        } catch (Exception $ex) {
            return response()->json([getErrorsException($ex)]);
        }
    }

    /**
     * @param $nfemitida
     * @param string $motivo
     * @param bool $removeItens
     * @throws Exception
     */
    public function estornoFinanceiroEstoque($nfemitida, $motivo = "", $removeItens = true)
    {
        try {
            $this->estornarFinanceiro($nfemitida);

            $this->estornarFinanceiro($nfemitida, true);

            $this->estornoNfEmitidaItem($nfemitida, $motivo, $removeItens);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|mixed|null|static|static[]
     */
    public function getempresa($id)
    {
        if (!is_null($this->empresa)) {
            if ($this->empresa->id !== $id)
                $this->empresa = Empresa::find($id);
        } else {
            $this->empresa = Empresa::find($id);
        }
        return $this->empresa;
    }

    /**
     * @param Nfemitida $nfemitida
     * @param string $motivo
     * @param bool $removeItens
     * @return bool
     * @throws Exception
     */
    private function estornoNfEmitidaItem($nfemitida, $motivo = "", $removeItens = true)
    {
        $processor = new NfProcessor($nfemitida);
        $processor->estornoItem($nfemitida, $nfemitida->nfEmitidaItem, $motivo);

        //Excluir do Banco
        if ($removeItens) {
            $nfemitida->nfEmitidaVolume()->delete();
            $nfemitida->nfEmitidaItem()->delete();
        }

        return true;
    }

    /**
     * @param $ids
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function exportarXmls($ids)
    {
        $semXml = Nfemitida::whereIn('id', json_decode($ids))
            ->where('nfsituacao_id', '100')
            ->whereNull("xmlretornocompleto")
            ->whereNotNull("xmlassinado")
            ->whereNotNull("xmlretorno")
            ->get();

        if ($semXml->isNotEmpty()) {
            $updated = $this->updateRetornoXml($semXml);

            if (!$updated) {
                return response("Erro ao processar XML.", 400);
            }
        }

        $nfs = Nfemitida::whereIn('id', json_decode($ids))->where('nfsituacao_id', '100')->get();

        $namezip = requestDataHoraFileName(Carbon::now());

        return view('nf.nfemitidaexportar', compact('nfs', 'namezip'));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getEmpresaUser()
    {
        return Auth::user()->empresas()
            ->select('id', 'nome_informal', 'nfeemite', 'nfceemite')
            ->whereRaw("(nfeemite = 1 or nfceemite = 1) and grupo_id = " . Session::get('empresa_padrao')->grupo_id)
            ->orderBy('nome_informal')->get();
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function importTxt()
    {
        return view('nf.util.txtimport');
    }

    /**
     * @return \Response
     */
    public function getXmlByTxt()
    {
        try {
            $txt = request()->file('file-upload');

            $c = new Convert();
            $e = ValidTXT::isValid(file_get_contents($txt->getRealPath()));

            if (count($e) > 0) {
                dd($e);
            }

            $axml = $c->toXML(file_get_contents($txt->getRealPath()));
            $filename = 'temp' . DIRECTORY_SEPARATOR . Carbon::now()->format('dmYhis') . '.xml';

            \Storage::disk('nfe')->put($filename, $axml[0]);
            $path = storage_path('nfe' . DIRECTORY_SEPARATOR . $filename);

            return response()->download($path)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            dump($e->getMessage());
            dd($e->getTraceAsString());
            return response()->json(["sucesso"]);
        }
    }

    /**
     * @return mixed
     */
    private function getClienteConfig()
    {
        $config = Session::get("empresa_config");
        if (is_null($config)) {
            Session::flash("message_info", "Não foi localizada as Configurações da Empresa.");
        }

        if (!isset($config->nfcecliente_id)) {
            Session::flash("message_info", "Não foi possível localizar o cliente Consumidor Final padrão das configurações da empresa.");
            return null;
        }

        return $config->nfcecliente_id;
    }

    /**
     * @param $nfemitida
     * @return bool|RedirectResponse
     */
    private function validateStatusNfEdit($nfemitida)
    {
        if ((Util::isAuthorized($nfemitida->nfsituacao_id) || $nfemitida->nfsituacao_id == 3)) {
            $url = "/nfemitida/$nfemitida->id" . '?index='
                . str_replace('&', 'extPar', Input::get('index', route('nfemitida.create')));
            Session::flash("message_info", "Esta NFe não pode ser editada.");
            return Redirect::to($url);
        }
        return true;
    }

    private function validateNumInutilizacao($empresa, $modelo, int $inicial, int $final)
    {
        $ambiente = (int) ($modelo === 55 ? $empresa["nfetipoambiente"] : $empresa["nfcetipoambiente"]);
        $key = $modelo === 55 ? "nfenumero" : "nfcenumero";
        $keyHomo = $modelo === 55 ? "nfenumerohomologacao" : "nfcenumerohomologacao";
        $numero = (int) ($ambiente === 1 ? $empresa[$key] : $empresa[$keyHomo]);

        if (($inicial - $numero) > 50) {
            throw new \Exception("A diferença entre o Número Atual ($numero) e o Número Inicial ($inicial) não pode ser maior que 50.");
        }

        if (($final - $inicial) > 50) {
            throw new \Exception("A diferença entre o Número Inical ($inicial) e o Número Final ($final) não pode ser maior que 50");
        }

        if (($final - $numero) > 50) {
            throw new \Exception("A diferença entre o Número Atual ($numero) e o Número Final ($final) não pode ser maior que 50.");
        }
    }

    public function processorPedidoAppnf(Request $request, $pedido_id)
    {
        $this->authorize('especial', Pedido::class);
        DB::beginTransaction();
        try {
            $data = $request->all();
            $pedido = Pedido::findOrFail($pedido_id);

            $empresa = Empresa::findOrFail($pedido->empresa_id);
            $empresaconfig = Empresaconfig::where('empresa_id', $pedido->empresa_id)->first();
            $cliente = Cliente::with('telefones', 'bairro', 'cidade', 'rua', 'convenioempresa.clienteConvenio')->find($empresaconfig->nfcecliente_id);
            $clientepedido = Cliente::with('telefones', 'bairro', 'cidade', 'rua')->find($pedido->cliente_id);
            $nfoperacao = Nfoperacao::findOrFail($data["nfoperacao_id"]);
            $fretemodalidade = $empresaconfig->fretemodalidadeappnf;

            $presencacomprador = $empresaconfig->presencacompradorappnf;

            $data['transportador_id'] = (int) $data['transportador_id'];
            $this->transportadora = DB::table('clientes as c')
                ->join('cidades as cid', 'cid.id', 'c.cidade_id')
                ->where('c.id', $data['transportador_id'])
                ->select('cid.descricao as cidade', 'c.complemento', 'c.id', 'c.inscricao_estadual', 'c.nome', 'c.cnpj', 'c.cpf', 'c.uf')->get()
                ->first();

            if ($presencacomprador == 4 && is_null($this->transportadora))
                throw new Exception("Erro ao buscar a transportadora.");

            $data['transportador_id'] = $data['transportador_id'] === 0 ? null : $data['transportador_id'];

            if ($data['formapagamento']) {
                $data['fretecondicaopagamento_id'] = $data['fretecondicaopagamento_id'] === 'null' ? null : $data['fretecondicaopagamento_id'];
            } else {
                $data['fretecondicaopagamento_id'] = null;
            }

            $ccfrete_id = $presencacomprador == 4 ? $empresaconfig->ccfrete_id : '';
            $pcfrete_id = $presencacomprador == 4 ? $empresaconfig->pcfrete_id : '';
            //quando a presença é 4, a taxa de entrega vira o frete
            $vFrete = $presencacomprador == 4 ? $pedido->entregataxa : 0;

            $data["grupo_id"] = $pedido->grupo_id;

            $data["nfmodelo"] = 55;
            $data["nftipoambiente"] = $empresa->nfcetipoambiente;
            $data["datahoraemissao"] = requestDataOracle(Carbon::now()->toDateTimeString()); // requestDataOracle($pedido->datahoraprevisaoentrega); Alterado por requisicao do gas, porque as notas sao criadas em horarios diferentes
            $data["datahoraentradasaida"] = requestDataOracle(Carbon::now()->toDateTimeString()); // requestDataOracle($pedido->datahoraprevisaoentrega); Alterado por requisicao do gas, porque as notas sao criadas em horarios diferentes
            $data["nfefinalidade"] = 1; //1-Normal
            $data["presencacomprador"] = $presencacomprador; //1 - Operação PresencialF
            $data["nfoperacao_id"] = $nfoperacao->id;
            $data["empresa_id"] = $empresa->id;
            //$data["informacaocomplementar"] = "";
            $data["informacaocomplementar"] = isset($data["appinformacaocomplementar"]) ? $data["appinformacaocomplementar"] : "";
            $data["informacaoadicionalfisco"] = $nfoperacao->informacoesadicionalfisco;
            $data["emitrazaosocial"] = $empresa->razao_social;
            $data["emitnomefantasia"] = $empresa->nome_fantasia;
            $data["emitie"] = $empresa->inscricao_estadual;
            $data["emitcnpj"] = $empresa->cnpj;
            $data["emitcpf"] = "";
            $data["emitinscricaomunicipal"] = $empresa->inscricao_municipal;
            $data["emitcnae"] = $empresa->inscricao_municipal ? $empresa->cnae : "";
            $data["codcrt"] = $empresa->nfcecrt;
            $data["emitpaiscodigoibge"] = $empresa->codigoibgepais;
            $data["emitpaisnome"] = "Brasil";
            $data["emitufcodigoibge"] = $empresa->estado->cod_ibge;
            $data["emituf"] = $empresa->estado->uf;
            $data["emitcidadecodigoibge"] = $empresa->cidade->cod_ibge;
            $data["emitcidadenome"] = $empresa->cidade->descricao;
            $data["emitcidade_id"] = $empresa->cidade->id;
            $data["emitbairro"] = $empresa->bairro->descricao;
            $data["emitendereco"] = $empresa->rua->descricao;
            $data["emitnumero"] = $empresa->numero;
            $data["emitcep"] = $empresa->cep;
            $data["emitcomplemento"] = $empresa->complemento;
            $data["emittelefone"] = $empresa->telefone1;
            $data["nfc_tpag"] = $request->get("nfc_tpag", null);
            if (!is_null($pedido->cliente->convenioempresa) && $pedido->cliente->convenioempresa->clienteConvenio) {
                $data['conveniovencimento'] = $pedido->cliente->convenioempresa->clienteConvenio->diavencimento;
            }
            $cliente = $clientepedido;
            if (!$cliente->cep) {
                $cliente->cep = str_replace("-", "", $pedido->entregacep);
            }

            $data = $this->getDataDest($cliente, $data);
            $data["destpaiscodigoibge"] = $empresa->codigoibgepais; // RECEBE DA EMPRESA
            $data["fretemodalidade"] = $fretemodalidade;
            $data["fretecpf"] = $this->getDataTransp('cpf');
            $data["fretecnpj"] = $this->getDataTransp('cnpj');
            $data["freterazaosocial"] = $this->getDataTransp('nome');
            $data["fretie"] = $this->getDataTransp('inscricao_estadual');
            $data["fretuf"] = $this->getDataTransp('uf');
            $data["fretecidadenome"] = $this->getDataTransp('cidade');
            $data["freteenderecocompl"] = $this->getDataTransp('complemento');
            $data["fretecliente_id"] = $this->getDataTransp('id');
            $data["fretecentrocusto_id"] = $ccfrete_id;
            $data["freteplanoconta_id"] = $pcfrete_id;

            $processaItems = $this->processaItemsPedidoAppnf($pedido, $nfoperacao);

            if (!is_array($processaItems)) {
                throw new Exception($processaItems);
            }

            $produtos = json_encode($processaItems[0]);
            $totalvalorproduto = $processaItems[1];

            $pars = [
                "prod"       => $produtos,
                "destuf"     => $data['emituf'],
                "cliente_id" => $data['destcliente_id'],
                "indfinal"   => $cliente->consumidor_final || $cliente->indicador_ie == 9 || $cliente->tipopessoa->tipopessacadastro == 'F' ? "1" : "0",
                "iddest"     => $data['emituf'] != $cliente->uf ? "2" : "1",
                "empresa_id" => $pedido->empresa_id,
                "vprod"      => $totalvalorproduto,
                "vfrete"     => $vFrete,
                "vipi"       => "R$ 0,00",
                "vseg"       => "R$ 0,00",
                "voutro"     => "R$ 0,00",
                "nfmodelo"   => "55",
                "tiponf"     => "E",
                'vdesc'      => requestNumeroDecimalOracle($pedido->valordesconto)
            ];

            $tributos = new NfeImpostoProcessor(null, $pars);
            $calculoImpostos = $tributos->calculaImposto(false);
            $impFloat = $calculoImpostos[1];
            $calculoImpostos = $calculoImpostos[0];

            $data["produtos"] = $produtos;
            $data['vprod'] = requestNumeroDecimalOracle($totalvalorproduto);
            $data['vfrete'] = $vFrete;
            $data['vseg'] = "R$ 0,00";
            $data['voutro'] = "R$ 0,00";
            $data['vicmsdeson'] = "R$ 0,00";
            $data['vfcp'] = "R$ 0,00";
            $data['vfcpst'] = "R$ 0,00";
            $data['vipi'] = "R$ 0,00";
            $data['vbruto'] = requestNumeroDecimalOracle($impFloat->vprod);
            $data['vdesc'] = requestNumeroDecimalOracle($pedido->valordesconto);
            $data['vnf'] = requestNumeroDecimalOracle($impFloat->vprod + $vFrete);

            $data["vbc"] = $calculoImpostos->vbc;
            $data["vicms"] = $calculoImpostos->vicms;
            $data["vbcst"] = $calculoImpostos->vbcst;
            $data["vst"] = $calculoImpostos->vst;
            $data["vcofins"] = $calculoImpostos->vcofins;
            $data["vpis"] = $calculoImpostos->vpis;
            $data["iddest"] = $data['emituf'] != $cliente->uf ? "2" : "1";
            $data["indfinal"] = $cliente->consumidor_final || $cliente->indicador_ie == 9 || $cliente->tipopessoa->tipopessacadastro == 'F' ? "1" : "0";
            $data['descricaofinanceiro'] = "Pedido " . $pedido->id;
            $data['condicaopagamento_id'] = $pedido->condicaopagamento_id;
            $data['centrocusto_id'] = $empresaconfig->centrocusto_id;
            $data['planoconta_id'] = $empresaconfig->planoconta_id;
            $request = new Request();
            $request->replace($data);
            $nf = $this->processaStore($request->all(), $pedido->id);

            if ($nf instanceof Nfemitida) {

                if (!$pedido->update(['nfcegerou' => true, 'nfce_id' => $nf->id]))
                    throw new Exception("NF-e foi gerada mas não foi possível vinculá-la ao pedido, contate o suporte.");

                DB::commit();
                return response()->json([
                    'status'    => "OK",
                    'msg'       => "NF-e gerada: " . $nf->id . ":" . $nf->nfnumero,
                    'id'        => $this->nf_id,
                    'nfnumero'  => $nf->nfnumero
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'status'    => "NO",
                    'msg'       => $nf,
                    'id'        => $this->nf_id
                ]);
            }
        } catch (Exception $e) {
            DB::rollBack();
            Util::log($e->getTraceAsString(), $e->getCode());
            return response()->json([
                'status'    => "NO",
                'msg'       => $e->getMessage(),
                'id'        => 0
            ]);
        }
    }
    private function processaItemsPedidoAppnf($pedido, $nfoperacao)
    {
        $produtos = [];
        $totalvalorproduto = 0;
        foreach ($pedido->pedidoitem as $pedidoitem) {
            $prod = Produto::find($pedidoitem->produto_id);
            $nfoperacaoproduto = Nfoperacaoproduto::where([
                ['nfoperacao_id', $nfoperacao->id],
                ['produto_id', $prod->id]
            ])->get()->first();
            if ($nfoperacaoproduto != null) {
                $produto = [];
                $produto[0] = $nfoperacaoproduto->nfoperacaoapp_id;
                $produto[1] = "";
                $produto[2] = $pedido->entregasetor_id;
                $produto[3] = "";
                $produto[4] = $prod->id;
                $produto[5] = $prod->descricao;
                $produto[6] = requestNumeroDecimalOracle($pedidoitem->precovendaunitario);
                $produto[7] = $pedidoitem->quantidade;
                $produto[8] = "";
                $produto[9] = $prod->pesoliquido;
                $produto[10] = $prod->pesobruto;
                $produto[11] = $prod->ncm;
                $produto[12] = $prod->unidademedida->sigla;
                $produto[13] = $prod->ean;
                $produto[14] = $prod->nfeextipi;
                $produto[15] = $prod->nfcest;
                $produto[16] = $prod->nfedescricaofiscal;
                $produto[17] = $prod->nfgrupofiscal_id;
                $produto[18] = intval($pedidoitem->quantidade);
                $produto[19] = number_format(($pedidoitem->quantidade * $prod->pesoliquido), 3, '.', '');
                $produto[20] = number_format(($pedidoitem->quantidade * $prod->pesobruto), 3, '.', '');
                $produto[21] = number_format($prod->pgni, 4, ',', '');
                $produto[22] = number_format($prod->pgnn, 4, ',', '');
                $produto[23] = number_format($prod->pglp, 4, ',', '');

                $produtos[] = $produto;

                $totalvalorproduto += $pedidoitem->quantidade * $pedidoitem->precovendaunitario;
            } else {
                return "Erro: Produto " . $prod->descricao . " não tem operação para NF no App.";
            }
        }
        return [$produtos, $totalvalorproduto];
    }

    private function updateRetornoXml($nfs)
    {
        DB::beginTransaction();
        try {
            foreach ($nfs as $nf) {
                $sefaz = new SefazEvento($nf);

                $sefaz->addAuthorized();
            }

            DB::commit();

            return true;
        } catch (\Exception $ex) {
            DB::rollBack();
            return false;
        }
    }

    public function processorFechamentoConvenionf($fechamento)
    {
        DB::beginTransaction();
        try {

            $empresa = Empresa::findOrFail($fechamento->empresa_id);
            $empresaconfig = Empresaconfig::where('empresa_id', $fechamento->empresa_id)->first();
            $cliente = Cliente::with('telefones', 'bairro', 'cidade', 'rua', 'convenioempresa.clienteConvenio')->find($fechamento->cliente_id);
            $clientepedido = Cliente::with('telefones', 'bairro', 'cidade', 'rua')->find($fechamento->cliente_id);
            $nfoperacao = Nfoperacao::findOrFail($empresaconfig->nfoperacaoconvenio_id);
            throwIf($nfoperacao->movimentaestoque != 0 || $nfoperacao->movimentafinanceiro != 0, 'Atenção! A operação para geração de NF do convênio não pode movimentar estoque ou financeiro. Verifique a configuração da empresa');
            $fretemodalidade = $empresaconfig->fretemodalidadeconvenionf;
            if ($fretemodalidade !== 9 && $fretemodalidade !== 1) {
                throwIf(!$empresaconfig->veiculoconvenio_id, 'Para a modalidade de frete configurada na empresa, é necessário configurar o veículo padrão');
                throwIf(!$empresaconfig->transportadorconvenionf_id, 'Para a modalidade de frete configurada na empresa, é necessário configurar a transportadora');
            }
            $presencacomprador = $empresaconfig->presencacompradorconvenionf;

            $data = [
                "indicador_ie" => $fechamento->cliente->indicador_ie,
                "transportador_id" => $empresaconfig->transportadorconvenionf_id,
                "freteplaca" => $fretemodalidade !== 9 && $fretemodalidade !== 1?$empresaconfig->veiculoConvenionf->placa:'',
                "freteplacauf" => $fretemodalidade !== 9 && $fretemodalidade !== 1?$empresaconfig->veiculoConvenionf->placauf:'',
                "fretecondicaopagamento_id" => "null",
                "formapagamento" => "0",
                "empresa_documento" => $fechamento->cliente->empresa_id,
                "nfoperacao_id" => $empresaconfig->nfoperacaoconvenio_id,
                "appinformacaocomplementar" => ''
            ];

            $data['transportador_id'] = $fretemodalidade !== 9 && $fretemodalidade !== 1?(int) $data['transportador_id'] : null;

            $this->transportadora = DB::table('clientes as c')
                ->join('cidades as cid', 'cid.id', 'c.cidade_id')
                ->where('c.id', $data['transportador_id'])
                ->select('cid.descricao as cidade', 'c.complemento', 'c.id', 'c.inscricao_estadual', 'c.nome', 'c.cnpj', 'c.cpf', 'c.uf')->get()
                ->first();

            if ($presencacomprador == 4 && is_null($this->transportadora))
                throw new Exception("Erro ao buscar a transportadora.");

            $data['transportador_id'] = $data['transportador_id'] === 0 ? null : $data['transportador_id'];

            if ($data['formapagamento']) {
                $data['fretecondicaopagamento_id'] = $data['fretecondicaopagamento_id'] === 'null' ? null : $data['fretecondicaopagamento_id'];
            } else {
                $data['fretecondicaopagamento_id'] = null;
            }

            $ccfrete_id = $presencacomprador == 4 ? $empresaconfig->ccfreteconvenio_id : '';
            $pcfrete_id = $presencacomprador == 4 ? $empresaconfig->pcfreteconvenio_id : '';
            //quando a presença é 4, a taxa de entrega vira o frete
            $vFrete = 0;

            $data["grupo_id"] = $fechamento->grupo_id;

            $data["nfmodelo"] = 55;
            $data["nftipoambiente"] = $empresa->nfcetipoambiente;
            $data["datahoraemissao"] = requestDataOracle(Carbon::now()->toDateTimeString()); // requestDataOracle($pedido->datahoraprevisaoentrega); Alterado por requisicao do gas, porque as notas sao criadas em horarios diferentes
            $data["datahoraentradasaida"] = requestDataOracle(Carbon::now()->toDateTimeString()); // requestDataOracle($pedido->datahoraprevisaoentrega); Alterado por requisicao do gas, porque as notas sao criadas em horarios diferentes
            $data["nfefinalidade"] = 1; //1-Normal
            $data["presencacomprador"] = $presencacomprador; //1 - Operação PresencialF
            $data["nfoperacao_id"] = $nfoperacao->id;
            $data["empresa_id"] = $empresa->id;
            //$data["informacaocomplementar"] = "";
            $data["informacaocomplementar"] = isset($data["convenioinformacaocomplementar"]) ? $data["convenioinformacaocomplementar"] : "";
            $data["informacaoadicionalfisco"] = $nfoperacao->informacoesadicionalfisco;
            $data["emitrazaosocial"] = $empresa->razao_social;
            $data["emitnomefantasia"] = $empresa->nome_fantasia;
            $data["emitie"] = $empresa->inscricao_estadual;
            $data["emitcnpj"] = $empresa->cnpj;
            $data["emitcpf"] = "";
            $data["emitinscricaomunicipal"] = $empresa->inscricao_municipal;
            $data["emitcnae"] = $empresa->inscricao_municipal ? $empresa->cnae : "";
            $data["codcrt"] = $empresa->nfcecrt;
            $data["emitpaiscodigoibge"] = $empresa->codigoibgepais;
            $data["emitpaisnome"] = "Brasil";
            $data["emitufcodigoibge"] = $empresa->estado->cod_ibge;
            $data["emituf"] = $empresa->estado->uf;
            $data["emitcidadecodigoibge"] = $empresa->cidade->cod_ibge;
            $data["emitcidadenome"] = $empresa->cidade->descricao;
            $data["emitcidade_id"] = $empresa->cidade->id;
            $data["emitbairro"] = $empresa->bairro->descricao;
            $data["emitendereco"] = $empresa->rua->descricao;
            $data["emitnumero"] = $empresa->numero;
            $data["emitcep"] = $empresa->cep;
            $data["emitcomplemento"] = $empresa->complemento;
            $data["emittelefone"] = $empresa->telefone1;
            $data["nfc_tpag"] = null;
            if (!is_null($fechamento->cliente->convenioempresa) && $fechamento->cliente->convenioempresa->clienteConvenio) {
                $data['conveniovencimento'] = $fechamento->cliente->convenioempresa->clienteConvenio->diavencimento;
            }
            $cliente = $clientepedido;

            $data = $this->getDataDest($cliente, $data);
            $data["destpaiscodigoibge"] = $empresa->codigoibgepais; // RECEBE DA EMPRESA
            $data["fretemodalidade"] = $fretemodalidade;
            $data["fretecpf"] = $this->getDataTransp('cpf');
            $data["fretecnpj"] = $this->getDataTransp('cnpj');
            $data["freterazaosocial"] = $this->getDataTransp('nome');
            $data["fretie"] = $this->getDataTransp('inscricao_estadual');
            $data["fretuf"] = $this->getDataTransp('uf');
            $data["fretecidadenome"] = $this->getDataTransp('cidade');
            $data["freteenderecocompl"] = $this->getDataTransp('complemento');
            $data["fretecliente_id"] = $this->getDataTransp('id');
            $data["fretecentrocusto_id"] = $ccfrete_id;
            $data["freteplanoconta_id"] = $pcfrete_id;

            $processaItems = $this->processaItemsFechamentoConvenionf($fechamento, $nfoperacao, $empresaconfig);

            if (!is_array($processaItems)) {
                throw new Exception($processaItems);
            }

            $produtos = json_encode($processaItems[0]);
            $totalvalorproduto = $processaItems[1];

            $pars = [
                "prod"       => $produtos,
                "destuf"     => $data['emituf'],
                "cliente_id" => $data['destcliente_id'],
                "indfinal"   => $cliente->consumidor_final || $cliente->indicador_ie == 9 || $cliente->tipopessoa->tipopessacadastro == 'F' ? "1" : "0",
                "iddest"     => $data['emituf'] != $cliente->uf ? "2" : "1",
                "empresa_id" => $fechamento->empresa_id,
                "vprod"      => $totalvalorproduto,
                "vfrete"     => $vFrete,
                "vipi"       => "R$ 0,00",
                "vseg"       => "R$ 0,00",
                "voutro"     => "R$ 0,00",
                "nfmodelo"   => "55",
                "tiponf"     => "E",
                'vdesc'      => requestNumeroDecimalOracle(0)
            ];

            $tributos = new NfeImpostoProcessor(null, $pars);
            $calculoImpostos = $tributos->calculaImposto(false);
            $impFloat = $calculoImpostos[1];
            $calculoImpostos = $calculoImpostos[0];

            $data["produtos"] = $produtos;
            $data['vprod'] = requestNumeroDecimalOracle($totalvalorproduto);
            $data['vfrete'] = $vFrete;
            $data['vseg'] = "R$ 0,00";
            $data['voutro'] = "R$ 0,00";
            $data['vicmsdeson'] = "R$ 0,00";
            $data['vfcp'] = "R$ 0,00";
            $data['vfcpst'] = "R$ 0,00";
            $data['vipi'] = "R$ 0,00";
            $data['vbruto'] = requestNumeroDecimalOracle($impFloat->vprod);
            $data['vdesc'] = requestNumeroDecimalOracle(0);
            $data['vnf'] = requestNumeroDecimalOracle($impFloat->vprod + $vFrete);

            $data["vbc"] = $calculoImpostos->vbc;
            $data["vicms"] = $calculoImpostos->vicms;
            $data["vbcst"] = $calculoImpostos->vbcst;
            $data["vst"] = $calculoImpostos->vst;
            $data["vcofins"] = $calculoImpostos->vcofins;
            $data["vpis"] = $calculoImpostos->vpis;
            $data["iddest"] = $data['emituf'] != $cliente->uf ? "2" : "1";
            $data["indfinal"] = $cliente->consumidor_final || $cliente->indicador_ie == 9 || $cliente->tipopessoa->tipopessacadastro == 'F' ? "1" : "0";
            $data['descricaofinanceiro'] = "Fechamento de Convênio " . $fechamento->id;
            $data['condicaopagamento_id'] = $fechamento->condicaopagamento_id;
            $data['centrocusto_id'] = $empresaconfig->centrocustoconvenio_id;
            $data['planoconta_id'] = $empresaconfig->planocontaconvenio_id;
            $request = new Request();
            $request->replace($data);
            $nf = $this->processaStore($request->all(), null);
            if ($nf instanceof Nfemitida) {
                if (!$fechamento->update(['nfemitida_id' => $nf->id]))
                    throw new Exception("NF-e foi gerada mas não foi possível vinculá-la ao fechamento, contate o suporte.");

                DB::commit();
                return response()->json([
                    'status'    => "OK",
                    'msg'       => "NF-e gerada: " . $nf->id . ":" . $nf->nfnumero,
                    'id'        => $this->nf_id,
                    'nfnumero'  => $nf->nfnumero
                ]);
            } else {
                DB::rollBack();
                return response()->json([
                    'status'    => "NO",
                    'msg'       => $nf,
                    'id'        => $this->nf_id
                ]);
            }
        } catch (Exception $e) {
            DB::rollBack();
            Util::log($e->getTraceAsString(), $e->getCode());
            return response()->json([
                'status'    => "NO",
                'msg'       => $e->getMessage(),
                'id'        => 0
            ]);
        }
    }

    private function processaItemsFechamentoConvenionf($fechamento, $nfoperacao, $empresaconfig)
    {
        $produtos = [];
        $totalvalorproduto = 0;
        $items = DB::table('conveniofechamentos as f')
                   ->join('conveniofechamentopedidos as fp', 'fp.conveniofechamento_id', 'f.id')
                   ->join('pedidos as p', 'p.id', 'fp.pedido_id')
                   ->join('pedidoitems as i', 'i.pedido_id', 'p.id')
                   ->where('f.id', $fechamento->id)
                   ->groupBy('i.produto_id')
                   ->select('i.produto_id', DB::Raw('SUM(i.quantidade) as quantidade'), DB::Raw('SUM(i.precovendatotal) as valortotal'))
                   ->get();

        $totalprodutos = $items->reduce(function ($carry, $item) {
            return $carry + $item->valortotal;
        }, 0);
        $diferenca = $fechamento->valor - $totalprodutos;
        $percentualdiferenca = $totalprodutos==0?0:$diferenca/$totalprodutos;

        foreach ($items as $item) {
            $prod = Produto::find($item->produto_id);
            $nfoperacaoprodutoconvenio = Nfoperacaoprodutoconvenio::where([
                ['nfoperacao_id', $nfoperacao->id],
                ['produto_id', $prod->id]
            ])->get()->first();
            if ($nfoperacaoprodutoconvenio != null) {
                $valorproduto = $item->valortotal + $item->valortotal*$percentualdiferenca;
                $valorunitario = $valorproduto/$item->quantidade;
                $produto = [];
                $produto[0] = $nfoperacaoprodutoconvenio->nfoperacaoconvenio_id;
                $produto[1] = "";
                $produto[2] = $empresaconfig->setorconvenio_id;
                $produto[3] = "";
                $produto[4] = $prod->id;
                $produto[5] = $prod->descricao;
                $produto[6] = requestNumeroDecimalOracle($valorunitario);
                $produto[7] = $item->quantidade;
                $produto[8] = "";
                $produto[9] = $prod->pesoliquido;
                $produto[10] = $prod->pesobruto;
                $produto[11] = $prod->ncm;
                $produto[12] = $prod->unidademedida->sigla;
                $produto[13] = $prod->ean;
                $produto[14] = $prod->nfeextipi;
                $produto[15] = $prod->nfcest;
                $produto[16] = $prod->nfedescricaofiscal;
                $produto[17] = $prod->nfgrupofiscal_id;
                $produto[18] = intval($item->quantidade);
                $produto[19] = number_format(($item->quantidade * $prod->pesoliquido), 3, '.', '');
                $produto[20] = number_format(($item->quantidade * $prod->pesobruto), 3, '.', '');
                $produto[21] = number_format($prod->pgni, 4, ',', '');
                $produto[22] = number_format($prod->pgnn, 4, ',', '');
                $produto[23] = number_format($prod->pglp, 4, ',', '');

                $produtos[] = $produto;

                $totalvalorproduto += $valorproduto;
            } else {
                return "Erro: Produto " . $prod->descricao . " não tem operação para NF no convênio.";
            }
        }
        return [$produtos, $totalvalorproduto];
    }

}
