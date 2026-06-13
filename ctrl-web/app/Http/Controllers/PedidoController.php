<?php

namespace App\Http\Controllers;

use App\Cliente;
use App\Condicaopagamento;
use App\Condicaopagamentoparcela;
use App\Financeiro;
use App\Helpers\Utils\NfUtil;
use App\Processors\MobileAppProcessor;
use Illuminate\Support\Facades\Session;
use App\Pedido;
use App\Valegassituacao;
use App\Pedidosituacao;
use App\Services\CarbonCustom as Carbon;
use App\Http\Requests\PedidoRequest;
use Illuminate\Support\Facades\DB;
use App\Pedidoitem;
use App\Estoquesetorhistorico;
use App\Processors\EstoqueProcessor;
use App\Empresa;
use Illuminate\Http\Request;
use App\Pedidosituacaohistorico;
use App\Valegas;
use App\Helpers\Utils\PedidoUtil as Util;
use App\Vendaativacliente;
use App\Empresaconfig;
use App\Financeiroparcela;
use App\Financeirorateio;
use App\Processors\financeiroProcessor;
use App\Setor;
use Exception;
use Input;
use App\Repository\PedidoRepository as Repository;

class PedidoController extends Controller
{

    protected $empresaConfig;
    protected $movimentaEstoque;
    protected $movimentaFinanceiro;
    protected $user;
    protected $empresa_id;
    protected $grupo_id;
    protected $editPedidoItems;

    /**
     * @param $config
     * @throws Exception
     */
    function setEmpresaConfig($config)
    {
        $this->empresaConfig = $config;
        $this->setMovimentaEstoqueFinanceiro($config);
    }

    /**
     * @param $config
     * @throws Exception
     */
    private function setMovimentaEstoqueFinanceiro($config)
    {
        if (!is_null($config) && !is_null($config->pedidoOp)) {
            $this->movimentaEstoque = $config->pedidoOp->movimentaestoque;
            $this->movimentaFinanceiro = $config->pedidoOp->movimentafinanceiro;
        } else {
            if (is_null($config))
                throw new Exception("As configurações da empresa ainda não foram definidas.");
            throw new Exception("A configuração da operação que define se o pedido gera financeiro e movimenta estoque ainda não foi selecionada.");
        }
    }

    /**
     * @param Pedido $pe
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Pedido $pe)
    {
        // dd(url('/'));
        // dd(sha1(env("APP_API_KEY")));
        $this->authorize('view', $pe);
        $grupo_id = Session::get('empresa_padrao')->grupo_id;
        $condicaoPagamento = Repository::getCondPgto($grupo_id);
        $status = Repository::getPedidoSituacao($grupo_id);
        $motivosatrasos = Repository::getMotAtraso($grupo_id);
        $empresas = Repository::getEmpresasByUser($grupo_id, $this->getUser());
        $setores = Repository::getAllSetoresAllowedUser($grupo_id, $empresas);
        $nfc_tpag = collect(NfUtil::getTPag(false));
        $colaboradores = Repository::getColaboradoresBySetores($setores);
        return view('pedido.pedidos', compact('motivosatrasos', 'nfc_tpag', 'status', 'empresas', 'condicaoPagamento', 'setores', 'colaboradores'));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|string
     */
    public function getPedidosMonitoramento()
    {
        try {
            $resultsPerPage = 100;

            $page = ((int) Input::get('page', 1)) - 1;

            $pedidos = Repository::getPedidosMonitoramento($this->getUser());

            $sum = Util::sumFilterIndex($pedidos);

            $pedidosTable = Util::generateTableMonitoramento(paginate($pedidos, $page, $resultsPerPage));
        } catch (Exception $e) {
            return getErrorsException($e);
        }

        return view('pedido.pedidosmonitoramento_table', compact('pedidosTable', 'resultsPerPage', 'sum'));
    }

    /**
     * @param Pedido $pe
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    function create(Pedido $pe)
    {
        $this->authorize('create', $pe);
        $empPadrao = Session::get('empresa_padrao');
        $empresa_id = $empPadrao->id;
        $situacao = Pedidosituacao::where('grupo_id', $empPadrao->grupo_id)->where('ativo', 1)->get();
        $arrayStatusFechadoConcluido = Util::getJsonSituacaoFecConcluido($situacao);
        $data = Repository::getDataByGroupToFormPedido($empPadrao->grupo_id, $this->getUser());
        $data['bairros'] = Repository::getBairros($empPadrao->cidade_id, null, $this->getUser());
        $data['cidades'] = Repository::getCidadesByUf($empPadrao->uf);
        $data['cidade_id'] = $empPadrao->cidade_id;
        $data['ufEmpresa'] = $empPadrao->uf;
        $data['creating'] = true;
        $data['config'] = collect($data['allConfigs']->where('empresa_id', $empresa_id)->first());
        $data['empresa_id'] = $empresa_id;
        $data['nfc_tpag'] = collect(NfUtil::getTPag(false));
        $data['arrayStatusFechadoConcluido'] = $arrayStatusFechadoConcluido;
        return view('pedido.pedido_form', $data);
    }

    /**
     * @param $id
     * @param Pedido $pe
     * @return mixed
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit($id, Pedido $pe)
    {
        $this->authorize('update', $pe);
        return $this->form($id, true);
    }

    /**
     * @param $id
     * @param Pedido $pe
     * @return mixed
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    function show($id, Pedido $pe)
    {
        $this->authorize('view', $pe);
        return $this->form($id);
    }

    /**
     * @param $id
     * @param bool $edit
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function form($id, $edit = false)
    {
        $pedido = Repository::getPedidoForm($id);
        $this->authorize('igualdade', $pedido);

        $empPadrao = $pedido->empresa;
        $data = Repository::getDataByGroupToFormPedido($empPadrao->grupo_id, $this->getUser());
        $data['bairros'] = Repository::getBairros($empPadrao->cidade_id, null, $this->getUser());
        $data['cidades'] = Repository::getCidadesByUf($pedido->entregacidade->uf);
        $data['cidade_id'] = $empPadrao->cidade_id;
        $data['ufEmpresa'] = $empPadrao->uf;
        $data['creating'] = true;
        $config = $data['allConfigs']->where('empresa_id', $empPadrao->id)->first();
        //enviando collection porque dá erro se mandar um objeto pra view dá erro
        $data['config'] = collect($config);
        $data['empresa_id'] = $empPadrao->id;
        $pedidoitens = $pedido->pedidoitem;
        $cli = Repository::getClientById($pedido->cliente_id);
        $data['condicao_pagamento_cli'] = $cli->condicaoPagamento;
        $situacao = Pedidosituacao::where('grupo_id', $empPadrao->grupo_id)->where('ativo', 1)->get();

        $searchController = new SearchController();
        $hist = $searchController->historicoCliente($pedido->cliente_id, $id);

        $condPgtoCli = count($cli->condicaopagamento) ? $cli->condicaopagamento : '';
        $pedido->encerrado = !!$pedido->pedidosituacao->fechadoconcluido;
        $produtosConvenio = isset($cli->convenioempresa) && isset($cli->convenioempresa->produtoconvenio) ? $cli->convenioempresa->produtoconvenio->toJson() : "[]";
        $pedidoController = Util::getJsonPedidoForm($pedido, $hist, $cli->clienteProduto, $condPgtoCli, $produtosConvenio);

        if (!$pedidoController) {
            Session::flash('message_danger', "Não foi possível carregar o pedido, contate o suporte!");
            return redirect()->to('pedido');
        }

        $arrayStatusFechadoConcluido = Util::getJsonSituacaoFecConcluido($situacao);
        $arrayStatusFinalizado = Util::getJsonSituacaoEntregaFin($situacao);
        $arrayStatusFechadoCancelado = Util::getJsonSituacaoCancelado($situacao);

        $pedido->pedidomotivoatraso_id = strval($pedido->pedidomotivoatraso_id);

        $tempoEntregaUrgente = Carbon::parse($pedido->datahoraprevisaoentrega)->addMinutes($config->tempourgente);
        $tempoEntregaConfig = Carbon::parse($pedido->datahoraprevisaoentrega)->addMinutes($config->tempoentrega);

        $clienteSelectize = Util::getClienteSelectize($cli);
        $ruaSelectize = Util::getRuaSelectize($pedido);

        $pedeSenha = Util::isConclCanc($pedido->pedidosituacao);

        $data = array_merge([
            'pedidoitens'                 => $pedidoitens,
            'pedido'                      => $pedido,
            'pedidoController'            => $pedidoController,
            'clienteSelectize'            => json_encode($clienteSelectize),
            'ruaSelectize'                => json_encode($ruaSelectize),
            'nfc_tpag'                    => NfUtil::getTPag(false),
            'pedeSenha'                   => $pedeSenha,
            'arrayStatusFechadoConcluido' => $arrayStatusFechadoConcluido,
            'arrayStatusFechadoCancelado' => $arrayStatusFechadoCancelado,
            'arrayStatusFinalizado'       => $arrayStatusFinalizado,
            'tempoEntregaConfig'          => $tempoEntregaConfig,
            'tempoEntregaUrgente'         => $tempoEntregaUrgente
        ], $data);
        if (!$edit) {
            $data['show'] = true;
        }
        return view('pedido.pedido_form', $data);
    }

    /**
     * @param PedidoRequest $request
     * @param Pedido $pe
     * @return string
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws Exception
     */
    public function store(PedidoRequest $request, Pedido $pe, $isFromApi = false)
    {
        $this->authorize('create', $pe);
        $isApi = starts_with(request()->path(), 'api') || $isFromApi;
        DB::beginTransaction();
        try {
            $data = array_merge($request->all(), $this->dadosExtras($request->all()));
            $data["datahoraacao"] = $data["datahora"];
            $data["datahoraprevisaoentrega"] = $data["datahora"];
            $situacao = Repository::findOrFail($data['pedidosituacao_id'], 'Pedidosituacao');

            if (Util::isRealizada($situacao)) {
                $data['entregadatahora'] = Carbon::now()->toDateTimeString();
            }
            if (Util::useSameDateEntregaEntregador($situacao)) {
                $data['datahoraenvioentregador'] = $data['datahoraprevisaoentrega'];
            }

            $pedido = Pedido::create($data);

            $this->updateStatus($pedido->id, $data['pedidosituacao_id'], $pedido);
            $condicao = Repository::findOrFail($data['condicaopagamento_id'], 'Condicaopagamento');
            if ($this->insertProdFromStoreUpdate($pedido, $data) === "valegas") {
                $data['precovenda'] = $pedido->precovenda;
            }

            $movimentacao = $situacao->fechadoconcluido ? "SAIDA" : false;
            $this->movimentarEstoque($movimentacao, $data['produtospedido'], $pedido);

            $this->insertFinanceiroStoreUpdate($situacao, $condicao, $data, $pedido);

            if (!empty($data["vendaativa_id"])) {
                $this->updateVendaAtiva($data, $pedido);
            }

            $cliente = $pedido->cliente;

            if ($pedido->gasdopovo || $cliente->gasdopovo == 1) {
                //se é gás do povo, gera financeiro da taxa de entrega
                $this->insertFinanceiroEntregaGasdoPovo($situacao, $condicao, $pedido);
            }
        } catch (Exception $e) {
            DB::rollback();

            if ($isApi) {
                return internalResponseError($e);
            } else {
                return getErrorsException($e);
            }
        }
        DB::commit();

        if (empty($data["vendaativa_id"]) && !$isApi) {
            return "OK|" . $pedido->id;
        } else if (!$isApi) {
            return "vendaativa";
        } else {
            return internalResponseSuccess($pedido, "Success!");
        }
    }

    /**
     * @param PedidoRequest $request
     * @param $id
     * @return string
     * @throws Exception
     */
    public function update(PedidoRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $pedido = Repository::findOrFail($id, 'Pedido');
            $this->authorize('update', $pedido);
            $this->authorize('igualdade', $pedido);
            $data = $this->dadosExtras($request->all(), $pedido);
            $situacaoAtual = Repository::findOrFail($data['pedidosituacao_id'], 'Pedidosituacao');
            $condicaoPagamento = Repository::findOrFail($data['condicaopagamento_id'], 'Condicaopagamento');

            $this->checkForParcelas($pedido, $condicaoPagamento, $data);

            $pedido->pedidoitem()->delete();
            $data['situacao'] = $situacaoAtual;
            if ($this->insertProdFromStoreUpdate($pedido, $data) === "valegas") {
                $data['valorvenda'] = $pedido->valorvenda;
            }

            $produtos = json_encode($this->editPedidoItems);

            $pedFin = clone $pedido;
            $pedFin->valordesconto = $data["valordesconto"];
            $pedFin->entregataxa = $data["entregataxa"];
            $pedFin->valorvenda = $data["valorvenda"];
            $this->validateMovFinanceiro($pedFin, $data, $condicaoPagamento, $situacaoAtual);
            $this->validateMovEstoque($pedido, $situacaoAtual, $produtos, $data);

            $dataUp = $this->updateGeneral($pedido, $data, $situacaoAtual, $condicaoPagamento);

            if (is_array($dataUp)) {
                $data = array_merge($data, (array) $dataUp);
            }
            $pedido->update($data);
        } catch (Exception $e) {
            DB::rollback();
            return getErrorsException($e);
        }

        DB::commit();
        return "OK|" . $pedido->id;
    }

    /**
     * @param $pedido_id
     * @param $pedidosituacao_id
     * @param null|Pedido $pedido
     * @param null $pedidomotivoatraso_id
     * @param null $latitude
     * @param null $longitude
     * @return string
     */
    public function updateStatus($pedido_id, $pedidosituacao_id, $pedido = null, $pedidomotivoatraso_id = null, $latitude = null, $longitude = null, $cartao_autorizacao = "")
    {
        try {


            $dados = [];
            $dados['pedidosituacao_id'] = $pedidosituacao_id;

            if ($latitude !== null && $longitude !== null) {
                $dados['entregalatitude'] = $latitude;
                $dados['entregalongitude'] = $longitude;
            }

            if (!is_null($pedidomotivoatraso_id)) {
                $this->justificaMotivoAtraso($pedido_id, $pedidomotivoatraso_id);
            }

            if ($pedido !== null && $pedido !== 'null') {
                $pedido->update($dados);
            } else {
                $pedido = Repository::findOrFail($pedido_id, 'Pedido');
                $pedido = $pedido->update($dados);
            }
            $dados['pedido_id'] = $pedido_id;
            $dados['datahora'] = Carbon::now();
            $dados['apipedido_id'] = $pedido->apipedido_id;
            $dados['enviadoapi'] = 0;

            Pedidosituacaohistorico::create($dados);
            //dd($cartao_autorizacao);
            if ($cartao_autorizacao != "") {
                $financeiro = $pedido->financeiro;
                if ($financeiro != null) {
                    if (trim($financeiro->cartaoautorizacao) == '') {
                        $financeiro->update(['cartaoautorizacao' => $cartao_autorizacao]);
                    }
                }
            }
        } catch (Exception $e) {
            return getErrorsException($e);
        }
        return "OK|";
    }

    /**
     * @param Pedido $pedido
     * @param $data
     * @param $situacao
     * @param $condicaoPagamento
     * @return string
     * @throws Exception
     */
    private function updateGeneral($pedido, $data, $situacao, $condicaoPagamento)
    {

        if (!is_null($pedido->pedidomotivoatraso_id) && array_key_exists('pedidomotivoatraso_id', $data)) {
            unset($data['pedidomotivoatraso_id']);
            $motivoatraso_id = null;
        } else {
            $motivoatraso_id = isset($data['pedidomotivoatraso_id']) ? $data['pedidomotivoatraso_id'] : null;
        }

        $statusDiff = $pedido->pedidosituacao_id != $data['pedidosituacao_id'];

        $condDiff = $pedido->condicaopagamento_id != $data['condicaopagamento_id'];

        if ($condDiff && $condicaoPagamento->tipo == "4") {
            $isDisponivel = Util::checkForConvenio($pedido);

            if (!$isDisponivel) {
                throw new Exception("Convênio indisponível para este cliente!");
            }
        }

        $this->updateStatus($pedido->id, $data['pedidosituacao_id'], $pedido, $motivoatraso_id);

        if ($situacao->entregapendente) {
            $pedido->update([
                'entregadatahora'           => null,
                'datahoraenvioentregador'   => null,
                'entregalatitude'           => null,
                'entregalongitude'          => null,
                'pedidomotivoatraso_id'     => null
            ]);
            try {
                $this->sendPedidoPendente($pedido->id);
            } catch (Exception $e) {
            }
        }

        if (!$this->hasChanges($pedido, $data) && !$statusDiff) {
            return "NOCHANGE";
        }

        if (Util::isRealizada($situacao) && $statusDiff)
            $data['entregadatahora'] = is_null($pedido->entregadatahora) ? Carbon::now()->toDateTimeString() : $pedido->entregadatahora;

        if (Util::useSameDateEntregaEntregador($situacao) && $statusDiff) {
            if ($pedido->pedidosituacao->entregapendente && $situacao->fechadoconcluido) {
                $dataenvioentregador = $pedido->datahoraprevisaoentrega;
            } else {
                $dataenvioentregador = Carbon::now()->toDateTimeString();
            }
            $data['datahoraenvioentregador'] = is_null($pedido->datahoraenvioentregador) ? $dataenvioentregador : $pedido->datahoraenvioentregador;
        }

        //        if ($pedido->condicaopagamento->tipo == '5') {
        //            if ($condicaoPagamento->tipo != '5' && $pedido->pedidosituacao->fechadoconcluido) {
        //                $rollback = $this->rollbackPedidoItensPrecoOriginal($pedido->pedidoitem, $pedido->cliente_id, $pedido);
        //                if (!is_bool($rollback) && !$rollback)
        //                    throw new Exception($rollback);
        //            }
        if (!$situacao->fechadoconcluido || $condicaoPagamento->tipo != '5') {
            $rollback = $this->rollbackValegas($pedido->id);

            if (!is_bool($rollback) && !$rollback)
                throw new Exception($rollback);
        }
        //        }

        return $data;
    }

    /**
     * insere os itens e movimenta estoque.
     * Usado somente no metodo Store e Update pela tela de pedidos e não pela de monitorameno
     *
     * @param $pedido
     * @param $data
     * @return bool|string
     * @throws Exception
     */
    private function insertProdFromStoreUpdate($pedido, &$data)
    {
        if (isset($data['produtosvalegas']) && $data['produtosvalegas'] != '[]') {
            $valegas = $this->updateValeGasPedido([
                'pedido'   => $pedido,
                'produtos' => $data['produtosvalegas'],
                'situacao' => array_key_exists('situacao', $data) ? $data['situacao'] : $pedido->pedidosituacao
            ]);
            if ($valegas != "OK|")
                throw new Exception($valegas);
            return "valegas";
        } else {
            $insertProdutos = $this->insertProdutos($data, $pedido->id);
            if (!is_bool($insertProdutos) || $insertProdutos == false)
                throw new Exception($insertProdutos);
            return true;
        }
    }

    /**
     * @param $situacao
     * @param $condicao
     * @param $data
     * @param Pedido $pedido
     * @param bool $ignoreValidation
     * @throws Exception
     */
    private function insertFinanceiroStoreUpdate($situacao, $condicao, $data, $pedido, $ignoreValidation = false)
    {
        if ($ignoreValidation || Util::allwedToCreateFinanceiro($condicao, $situacao)) {
            $financeiro = $this->gerarFinanceiro($data, $pedido, $condicao);
            if (!is_null($financeiro) && !is_numeric($financeiro)) {
                throw new Exception($financeiro);
            } else {
                unset($pedido->cartaoautorizacao);
                unset($pedido->cartaonsu);
                $pedido->update(['financeiro_id' => $financeiro]);
            }
        }
    }

    /**
     * @param Financeiro $financeiroModel
     * @throws Exception
     */
    private function deleteFinanceiro($financeiroModel)
    {
        $financeiroModel->parcelas()->delete();
        $financeiroModel->rateios()->delete();
        $financeiroModel->delete();
    }

    /**
     * @param Produto $produtos
     * @param $cliente_id
     * @param Pedido $pedido
     * @return bool
     * @throws Exception
     */
    private function rollbackPedidoItensPrecoOriginal($produtos, $cliente_id, $pedido)
    {
        $cliente = Repository::findOrFail($cliente_id, 'Cliente');
        $valorVenda = 0;
        foreach ($produtos as $prod) {
            $valor = $prod->produto->precovenda;
            foreach ($cliente->clienteProduto as $clienteproduto) {
                if ($prod->produto_id == $clienteproduto->produto_id)
                    $valor = $clienteproduto->preco;
            }
            $produto = [];
            $produto['precovendaunitario'] = $valor;
            $produto['precovendatotal'] = $valor;
            $valorVenda += $valor;
            $prod->update($produto);
        }
        $pedido->update(['valorvenda' => $valorVenda]);
        return true;
    }

    /**
     * @param $pedido_id
     * @return bool
     */
    private function rollbackValegas($pedido_id)
    {
        $valeGasSituacao_id = Valegassituacao::where('descricao', 'Impresso')->orWhere('descricao', 'IMPRESSO')
            ->orWhere('descricao', 'impresso')->get()->first()->id;

        Valegas::where('pedido_id', $pedido_id)->update([
            'databaixa'          => null,
            'valegassituacao_id' => $valeGasSituacao_id,
            'pedido_id'          => null
        ]);

        return true;
    }

    /**
     * @param $data
     * @param null|Pedido $pedido
     * @return array
     * @throws Exception
     */
    protected function dadosExtras($data, $pedido = null)
    {
        $searc = new SearchController();
        $config = $searc->searchDadosPedidoEmpresa($data['empresa_id'], true);
        $setor = Setor::findOrFail($data['entregasetor_id']);
        if ($setor->pedidooperacao_id != null) {
            $config->pedidooperacao_id = $setor->pedidooperacao_id;
        }
        if ($config === false)
            throw new Exception("As configurações da empresa não foram encontradas!");

        $this->setEmpresaConfig($config);

        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;

        return Util::dadosExtras($data, $this->getUser(), $this->grupo_id, $pedido, $config->condicaopagamentogp_id);
    }

    /**
     * @param $data
     * @param $pedido_id
     * @param null $produtos
     * @return bool|string
     */
    private function insertProdutos($data, $pedido_id, $produtos = null)
    {
        try {
            if ((!is_null($data) && strlen($data["produtospedido"]) > 3) || (!is_null($produtos) && count($produtos) > 0)) {
                if ($produtos == null)
                    $produtos = json_decode($data["produtospedido"]);

                if (count($produtos) === 0)
                    throw new Exception('Ao menos um produto deve ser adicionado!');

                $this->editPedidoItems = collect([]);
                foreach ($produtos as $produto) {
                    $produto_id = str_replace('P', '', $produto[0]);
                    $produtoDB = Repository::findOrFail($produto_id, 'Produto');
                    $valorUnt = insertNumeroDecimalOracle($produto[2]);
                    $valorTotal = $valorUnt * $produto[3];
                    $pedidoItem = new Pedidoitem();
                    $pedidoItem->pedido_id = $pedido_id;
                    $pedidoItem->produto_id = $produto_id;
                    $pedidoItem->quantidade = $produto[3];
                    $pedidoItem->customedio = $produtoDB->customedio;
                    $pedidoItem->precovendaunitario = $valorUnt;
                    $pedidoItem->precovendatotal = $valorTotal;
                    $pedidoItem->save();

                    $this->editPedidoItems->push($pedidoItem);
                }
                return true;
            } else {
                throw new Exception('Ao menos um produto deve ser adicionado!');
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param $data
     * @param $pedido
     * @param $condicaoPagamento
     * @return null
     * @throws Exception
     */
    private function gerarFinanceiro($data, $pedido, $condicaoPagamento)
    {
        if ($this->movimentaFinanceiro == 0)
            return null;

        $config = $this->empresaConfig;
        return Util::gerarFinanceiro($data, $pedido, $condicaoPagamento, $config);
    }

    /**
     * @param Request $request
     * @param $pedido_id
     * @param $justificouAtraso
     * @param $informouValegas
     * @param bool $multiple
     * @return string
     * @throws Exception
     */
    public function editFromMonitoramento(Request $request, $pedido_id, $justificouAtraso, $informouValegas, $multiple = false)
    {
        if (!$multiple)
            DB::beginTransaction();
        try {
            $pedido = Repository::findOrFail($pedido_id, 'Pedido')->load('pedidoitem');
            $this->authorize('update', $pedido);
            $this->authorize('igualdade', $pedido);
            $data = $this->dadosExtrasMonitoramento($request, $pedido);
            $config = Repository::getConfig($pedido->empresa_id);
            if (!$this->hasChanges($pedido, $data)) {
                return "OK|";
            }

            $pedidosituacao = Repository::findOrFail($data['pedidosituacao_id'], 'Pedidosituacao');
            $condicaoPagamento = Repository::findOrFail($data['condicaopagamento_id'], 'Condicaopagamento');

            $this->checkForParcelas($pedido, $condicaoPagamento, $data);

            if ($pedido->condicaopagamento_id != $data['condicaopagamento_id'] && in_array($condicaoPagamento->tipo, ['2', '3', '5'])) {
                $tipo = null;
                if (in_array($condicaoPagamento->tipo, ['2', '3']) && $config->pedidovalidacartao) {
                    $tipo = "Cartão";
                } else if ($condicaoPagamento->tipo == '5' && $config->validagasbolso) {
                    $tipo = "Vale Gás";
                }

                if (!is_null($tipo))
                    return "Para informar uma condição de pagamento do tipo " . $tipo . ", você deve ir até a tela de pedidos.";
            }

            if ($this->empresaConfig !== null && $this->empresaConfig !== false) {
                $tempoentrega = $pedido->entregaurgente ? $this->empresaConfig->tempourgente * 60 : $this->empresaConfig->tempoentrega * 60;
                $datahoraenvioentregador = !is_null($pedido->datahoraenvioentregador) ? $pedido->datahoraenvioentregador : $pedido->datahoraprevisaoentrega;
                $atrasado = !is_null($datahoraenvioentregador) && (strtotime($datahoraenvioentregador) + $tempoentrega) < strtotime(Carbon::now());
                $finaliza = $pedidosituacao->entregafinalizada || $pedidosituacao->fechadoconcluido;
                $validaAtraso = !!$this->empresaConfig->validaatraso;

                if ($validaAtraso && !$justificouAtraso && $atrasado && $finaliza && !$pedido->pedidomotivoatraso_id)
                    return 'motivoatraso';
            }

            if (!$informouValegas && $condicaoPagamento->tipo == '5' && $pedido->condicaopagamento_id !== $condicaoPagamento->id && Util::pedeValegas($pedidosituacao)) {
                $pedidoitens = collect([]);
                foreach ($pedido->pedidoitem as $item) {
                    for ($i = 0; $item->quantidade > $i; $i++) {
                        $dados = (object) [];
                        $dados->pedido_id = $pedido->id;
                        $dados->produto_descricao = $item->produto->descricao;
                        $dados->produto_id = $item->produto_id;
                        $pedidoitens->push($dados);
                    }
                }
                return 'valegas' . json_encode($pedidoitens);
            }

            if (isset($data['produtosvalegas']) && !is_null($data['produtosvalegas']) && $data['produtosvalegas'] !== '[]' && $informouValegas) {
                $valegas = $this->updateValeGasPedido([
                    'pedido'   => $pedido,
                    'produtos' => $data['produtosvalegas'],
                    'situacao' => $pedidosituacao
                ]);
                $data['precovenda'] = $pedido->precovenda;
                if ($valegas != "OK|")
                    throw new Exception($valegas);
            }

            $this->validateMovFinanceiro($pedido, $data, $condicaoPagamento, $pedidosituacao);
            $this->validateMovEstoque($pedido, $pedidosituacao, $pedido->pedidoitem, $data);
            $dataUp = $this->updateGeneral($pedido, $data, $pedidosituacao, $condicaoPagamento);

            if (is_array($dataUp)) {
                $pedido->update($dataUp);
            }
            if (!$multiple) {
                DB::commit();
            }
        } catch (Exception $e) {
            if (!$multiple) {
                DB::rollback();
            }
            return getErrorsException($e);
        }

        return "OK|";
    }

    /**
     * @param $pedido_id
     */
    private function sendPedidoPendente($pedido_id)
    {
        $api = new ApiController();
        $api->sendPedidoPendente(null, $pedido_id);
    }

    /**
     * @param $request
     * @param $pedido
     * @return array
     * @throws Exception
     */
    private function dadosExtrasMonitoramento($request, $pedido)
    {
        $config = Empresaconfig::where('empresa_id', $pedido->empresa_id)->get()->first();
        $setor = Setor::findOrFail($request->all()["modalsetor_id"]);
        if ($setor->pedidooperacao_id != null) {
            $config->pedidooperacao_id = $setor->pedidooperacao_id;
        }

        $this->setEmpresaConfig($config);

        return Util::dadosExtrasMonitoramento($request->all(), $pedido, $config);
    }

    /**
     * @param Pedido $pedido
     * @param $data
     * @param $condicao
     * @param $situacao
     * @return bool
     * @throws Exception
     */
    private function validateMovFinanceiro($pedido, $data, $condicao, $situacao)
    {
        $transacaoOnline = DB::connection("sgcm_api")->table('transacoesonline')
            ->where('erppedido_id', $pedido->id)->first();

        if (!is_null($transacaoOnline)) {
            if (($situacao->entregacancelada || $situacao->fechadocancelado) && !$transacaoOnline->cancelado) {
                MobileAppProcessor::estornarPagamentoOnline($transacaoOnline->tid, $pedido->valorvenda);
                DB::connection("sgcm_api")->table('transacoesonline')
                    ->where('erppedido_id', $pedido->id)->update(["cancelado" => true]);
            } else if ($transacaoOnline->cancelado && !$situacao->entregacancelada && !$situacao->fechadocancelado) {
                throw new Exception("O pedido foi feito com pagamento online que já foi cancelado, portanto não pode ser alterado");
            }
        }

        $statusDiff = $pedido->pedidosituacao_id != $data['pedidosituacao_id'];
        //nova validação devido a operação por pedido

        $alterouOpSetor = false;
        if ($data["entregasetor_id"] != $pedido->entregasetor_id) {
            $setorAnt = Setor::findOrFail($pedido->entregasetor_id);
            $setorAtu = Setor::findOrFail($data["entregasetor_id"]);
            $alterouOpSetor = $setorAnt->pedidooperacao_id != $setorAtu->pedidooperacao_id;
        }
        //a chave 'novoFinanceiro' vem somente da tela que possui formulario completo
        $alterou = (isset($data["alterou"]) && $data["alterou"])
            || ((isset($data["novoFinanceiro"]) && $data["novoFinanceiro"]) || (int) $pedido->condicaopagamento_id !== (int) $condicao->id);

        $geraFinanceiro = false;
        //se mudar a condição precisa excluir e gerar novamente o financeiro, somente se não ser fechadocancelado
        if ($statusDiff || $alterou) {
            $isValeGas = $condicao->tipo == '5';
            //se condição não for vale gás e novo status não é fechadocancelado e situação antiga é fechado cancelado, insere o financeiro
            if (!$isValeGas && !$situacao->fechadocancelado && Util::isFechadoCancelado($pedido->pedidosituacao)) {
                $geraFinanceiro = 'INSERE';
                //se for vale gás ou fechado concluído e tem financeiro, deve só excluir
            } elseif ($isValeGas || $situacao->fechadocancelado) {
                $geraFinanceiro = 'EXCLUI';
                //se condição mudou e novo status não é fechadocancelado, insere o financeiro
            } elseif ($alterou && !$situacao->fechadocancelado) {
                $geraFinanceiro = 'INSERE';
            }
        }
        //nova validação devido a operação por pedido
        if ($alterouOpSetor) {
            $movfinant = false;
            $movfinatu = false;
            $searc = new SearchController();
            $config = $searc->searchDadosPedidoEmpresa($pedido->empresa_id, true);
            if ($setorAnt->pedidooperacao_id == null) {
                $movfinant = $config->pedidoOp->movimentafinanceiro != "0";
            } else {
                $movfinant = $setorAnt->pedidoOp->movimentafinanceiro != "0";
            }
            if ($setorAtu->pedidooperacao_id == null) {
                $movfinatu = $config->pedidoOp->movimentafinanceiro != "0";
            } else {
                $movfinatu = $setorAtu->pedidoOp->movimentafinanceiro != "0";
            }
            if ($movfinant != $movfinatu) {
                if ($movfinant) {
                    $geraFinanceiro = 'EXCLUI';
                } else {
                    if (!$situacao->fechadocancelado) {
                        $geraFinanceiro = 'INSERE';
                    }
                }
            }
        }

        //se financeiro for 'INSERE' vai estornar e inserir novamente, senão vai só estornar
        if ($geraFinanceiro) {
            $autorizacao = "";
            $financeiroModel = $pedido->financeiro;
            $baixado = !is_null($financeiroModel) && !is_null($financeiroModel->parcelas()->get()->where('baixado', 1)->first());
            $pedido->update(['financeiro_id' => null]);
            $nsu = "";
            if ($financeiroModel) {
                $autorizacao = "";
                $nsu = "";
                if (in_array($financeiroModel->condicaoPagamento->tipo, ['2', '3']) && in_array($condicao->tipo, ['2', '3'])) {
                    $autorizacao = $financeiroModel->cartaoautorizacao;
                    $nsu = $financeiroModel->cartaonsu;
                }
                if ($baixado) {
                    $msg = "Uma ou mais parcela (s) referente (s) ao pedido número " . $pedido->id . " %s e não pode ser estornada.";
                    throw new Exception(sprintf($msg, "já está baixada"));
                }

                if (!Util::allowedEstornoParcela($financeiroModel->id)) {
                    return true;
                }

                if (!$baixado) {
                    $this->deleteFinanceiro($financeiroModel);
                }
            }
            if ($geraFinanceiro === 'INSERE' && !$baixado) {
                // Validação necessario caso o update venha da tela de monitoramento, se vier o data vira o proprio pedido
                // senão passar o data por causa do campo alterou para determinar mudanças de produto ou valor
                // para criar financeiro.
                if (!isset($data["valorvenda"])) {
                    $data = $pedido;
                }
                $data['cartaoautorizacao'] = $autorizacao;
                $data['cartaonsu'] = $nsu;
                $this->insertFinanceiroStoreUpdate(null, $condicao, $data, $pedido, true);
            }
        }
        if ($pedido->gasdopovo && $statusDiff) {
            if (Util::isCancelado($situacao) && !Util::isCancelado($pedido->pedidosituacao)) {
                if ($pedido->financeiroentregagp_id) {
                    $financeiroModelGp = $pedido->financeiroentregagp;
                    $baixadoentrega = !is_null($financeiroModelGp) && !is_null($financeiroModelGp->parcelas()->get()->where('baixado', 1)->first());
                    if (!$baixadoentrega) {
                        $this->deleteFinanceiro($financeiroModelGp);
                    }
                }
            }
        }
        return true;
    }

    /**
     * @param Pedido $pedido
     * @param $situacao
     * @param $produtos
     * @param $data
     * @return bool
     * @throws Exception
     */
    private function validateMovEstoque($pedido, $situacao, $produtos, $data)
    {
        $movimentaEstoque = false;
        $alterou = false;

        $pedFechadoConc = Util::isFechadoConcl($pedido->pedidosituacao);
        $sitFechadoConc = Util::isFechadoConcl($situacao);
        $motivo = '';
        $revertOnly = $pedFechadoConc && !$sitFechadoConc;
        $insertOnly = !$pedFechadoConc && $sitFechadoConc;
        if (isset($data["alterou"]) && $data["alterou"])
            $alterou = true;

        $revertInsert = ($data['entregasetor_id'] !== $pedido->entregasetor_id || $alterou) && $pedFechadoConc;

        if ($this->hasChanges($pedido, $data)) {
            //se pedido não é fechado concluido e nova situação é fechado concluido, produto sai do estoque
            if ($insertOnly) {
                $movimentaEstoque = 'SAIDA';
                //se pedido é fechado concluido e nova situação não é fechado concluido, faz estorno do produto
            } elseif ($revertOnly) {
                $motivo = (Util::isCancelado($situacao) ? 'Cancelamento' : 'Estorno')
                    . " do pedido nº " . $pedido->id;
                $movimentaEstoque = 'ENTRADA';
                //se não mudou status, mas o pedido é fechado concluido e setores diferentes, estorna produto e insere no outro setor
            } elseif ($revertInsert) {
                $motivo = "Estorno do pedido nº " . $pedido->id . ' por troca de setor';
                $this->movimentarEstoque('ENTRADA', $produtos, $pedido, $motivo);
                $movimentaEstoque = 'SAIDA';
            }
        }
        //troca o setor pois se não faz a movimentação no setor novo e não estorna o do setor antigo
        if (!$revertOnly) {
            $pedido->entregasetor_id = $data['entregasetor_id'];
        }

        $this->movimentarEstoque($movimentaEstoque, $produtos, $pedido, $motivo);
        return true;
    }

    /**
     * @param null|array $data
     * @return string
     */
    public function updateValeGasPedido($data = null)
    {
        $pedido = $data['pedido'];
        $situacao = $data['situacao'];

        $produtos_valegas = json_decode($data['produtos']);
        $databaixa = Carbon::now();
        $valeGasSituacao_id = Valegassituacao::where('descricao', 'Baixado')
            ->orWhere('descricao', 'BAIXADO')
            ->orWhere('descricao', 'baixado')->get()->first()->id;

        $precovendatotal = 0;

        $pedido->pedidoitem()->delete();
        $produtos = [];

        try {

            foreach ($produtos_valegas as $prod) {
                //só atualiza o vale gás quando é fechadoconcluido
                if ($situacao->fechadoconcluido) {
                    $dataUp = [
                        'databaixa'          => $databaixa,
                        'valegassituacao_id' => $valeGasSituacao_id,
                        'pedido_id'          => $pedido->id
                    ];
                    Valegas::where('codigo', $prod[0])->get()->first()->update($dataUp);
                }
                $produto = [];
                $produto[0] = $prod[2];
                $produto[1] = null;
                $produto[2] = $prod[1];
                $produto[3] = 1;
                $precovendatotal += insertNumeroDecimalOracle($prod[1]);
                array_push($produtos, $produto);
            }

            $insertProdutos = $this->insertProdutos(null, $pedido->id, $produtos);

            $precovendatotal += $pedido->entregataxa;
            $pedido->update(['valorvenda' => $precovendatotal]);

            if (!is_bool($insertProdutos) || $insertProdutos == false)
                throw new Exception($insertProdutos);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return "OK|";
    }

    /**
     * @param $movimentacao
     * @param $produtos
     * @param $pedido
     * @param string $motivo
     * @return bool
     * @throws Exception
     */
    private function movimentarEstoque($movimentacao, $produtos, $pedido, $motivo = '')
    {
        if ($this->movimentaEstoque == 0 || !$movimentacao) {
            return true;
        }

        if (is_string($produtos)) {
            $produtos = json_decode($produtos);
        }

        $estoquesetorhistoricos = [];
        foreach ($produtos as $prod) {
            if (is_array($prod)) {
                $produto = (object) [];
                $produto->quantidade = $prod[3];
                $produto->produto_id = str_replace('P', '', $prod[0]);
            } else {
                $produto = $prod;
            }

            $estoquesetorhistorico = new Estoquesetorhistorico();
            $estoquesetorhistorico->user_id = $this->getUser()->id;
            $estoquesetorhistorico->setor_id = $pedido->entregasetor_id;
            $estoquesetorhistorico->produto_id = $produto->produto_id;
            $estoquesetorhistorico->movimentacao = $movimentacao;
            $estoquesetorhistorico->quantidade = $produto->quantidade;
            $estoquesetorhistorico->motivo = $motivo == '' ? "Pedido número: " . $pedido->id : $motivo;
            $estoquesetorhistorico->datahora = Carbon::now(); //$pedido->datahora;
            $estoquesetorhistorico->datahoracompetencia = $pedido->datahoraprevisaoentrega;
            $estoquesetorhistorico->entidade = 'Pedido';
            $estoquesetorhistorico->entidade_id = $pedido->id;
            $estoquesetorhistorico->grupo_id = $pedido->grupo_id;
            $estoquesetorhistorico->empresa_id = $pedido->empresa_id;
            if (isset($produto->customedio) && $movimentacao === 'ENTRADA')
                $estoquesetorhistorico->customedioestorno = floatval($produto->customedio);

            array_push($estoquesetorhistoricos, $estoquesetorhistorico);
        }
        $estoqueProcessor = new EstoqueProcessor();
        if (!$estoqueProcessor->movimentarEstoque($estoquesetorhistoricos)) {
            throw new Exception(implode(" ", $estoqueProcessor->getErrors()));
        }
        return true;
    }

    /**
     * @param Request $request
     * @param $justificouAtraso
     * @param $informouValegas
     * @return string
     */
    public function updateVariosStatus(Request $request, $justificouAtraso, $informouValegas)
    {
        $data = $request->all();
        $arrayPedidos = collect(json_decode($data['arraypedidos_id']));
        $j = (int) $data['lastEdited'];
        $count = count($arrayPedidos);
        for ($i = $j; $i < $count; $i++) {
            try {
                DB::beginTransaction();
                $pedido = Repository::findOrFail($arrayPedidos[$i], 'Pedido');
                $motivoatraso_id = $pedido->pedidomotivoatraso_id === null ? $data['motivoatraso_id'] : null;
                $request->request->add(['modalpedido_id' => $arrayPedidos[$i]]);
                $request->request->add(['modalcolaborador_id' => $pedido->colaborador_id]);
                $request->request->add(['modalcondicaopagamento_id' => $pedido->condicaopagamento_id]);
                $request->request->add(['modalsetor_id' => $pedido->entregasetor_id]);
                $request->request->add(['modalpedidosituacao_id' => $data['pedidosituacao_id']]);
                $request->request->add(['pedidomotivoatraso_id' => $motivoatraso_id]);
                $edit = $this->editFromMonitoramento($request, $arrayPedidos[$i], $justificouAtraso, $informouValegas, true);
                if ($edit !== 'OK|') {
                    throw new Exception($edit . '<|>' . $i);
                } else {
                    DB::commit();
                }
                $informouValegas = false;
            } catch (Exception $e) {
                DB::rollback();
                return getErrorsException($e);
            }
        }
        return "OK|";
    }

    /**
     * @param $pedido
     * @param $motivoatraso_id
     * @return string
     * @throws Exception
     */
    public function justificaMotivoAtraso($pedido, $motivoatraso_id)
    {
        if ($pedido instanceof Pedido)
            $pedido->update(['pedidomotivoatraso_id' => $motivoatraso_id]);
        else
            Repository::findOrFail($pedido, 'Pedido')->update(['pedidomotivoatraso_id' => $motivoatraso_id]);
        return "OK|";
    }

    /**
     * @return string
     */
    function validaCartao()
    {
        $config = Empresaconfig::where('empresa_id', $_POST['empresa_id'])->get()->first();

        $cartaoMascarado = Util::mascaraCartao($_POST['numero']);
        $grupo_id = Session::get('empresa_padrao')->grupo_id;
        $date = Carbon::now()->subDays($config->pedidovalidacartaodias);

        $id = Input::get('id', 0);

        if (count(Repository::getPedidosByCartao($grupo_id, $cartaoMascarado, $date, $id))) {
            return 'NOT';
        } else {
            return 'OK|';
        }
    }

    /**
     * @param $id
     * @param $nfce_id
     * @return string
     * @throws Exception
     */
    function nfceGerou($id, $nfce_id)
    {
        Repository::findOrFail($id, "Pedido")->update(['nfcegerou' => 1, 'nfce_id' => $nfce_id]);
        return "OK|";
    }

    /**
     * @param $pedido_id
     * @return string
     * @throws Exception
     */
    public function checaStatusPedido($pedido_id)
    {
        if (Repository::findOrFail($pedido_id, "Pedido")->pedidosituacao->entregapendente == '1') {
            $this->sendPedidoPendente($pedido_id);
        }
        return 'OK|';
    }

    /**
     * @param Request $request
     * @return array|string
     */
    public function validaGasBolso(Request $request)
    {
        $data = $request->all();
        $valegas = Valegas::where([
            ['codigo', $data['codigo']],
            ['produto_id', $data['produto_id']],
            ['empresa_id', $data['empresa_id']]
        ])->get()->first();

        if (is_null($valegas)) {
            return "Código do Vale Gás informado não foi encontrado para este produto!";
        } elseif (strtoupper($valegas->valeGasSituacao->descricao) !== 'IMPRESSO') {
            return "Vale Gás não pode ser aceito pois está com a situação " . strtoupper($valegas->valeGasSituacao->descricao);
        } else {
            return ['codigo' => $valegas->codigo, 'produto' => $valegas->produto->descricao, 'produto_id' => $valegas->produto_id, 'valor' => requestNumeroDecimalOracle($valegas->valegasvenda->valorunitario)];
        }
    }

    /**
     * @return string
     */
    public function isFechado()
    {
        if (!isset($_GET['ids'])) {
            return "Nenhum pedido encontrado";
        }

        $ids = json_decode($_GET['ids']);
        $selectSituacao = "SELECT id FROM PEDIDOSITUACAOS S "
            . "WHERE (FECHADOCANCELADO = 1 OR FECHADOCONCLUIDO = 1) "
            . "AND grupo_id = " . Session::get('empresa_padrao')->grupo_id;

        $count = Pedido::whereIn('id', $ids)->whereRaw("pedidosituacao_id in ($selectSituacao)")
            ->select(DB::raw("count(*) as count"))->get()->first()->count > 0;

        return $count ? 'senha' : "OK|";
    }

    /**
     * @param $id
     * @param bool $senha
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function buscaPorId($id, $senha = false)
    {
        $pedido = Pedido::where('id', $id)->with('pedidosituacao', 'cliente.condicaoPagamento')->get()->first();

        if (Util::isConclCanc($pedido->pedidosituacao) && $senha === 'false')
            return 'senha' . $pedido->empresa_id;
        if (isset($pedido->cliente->condicaoPagamento) && $pedido->cliente->condicaoPagamento) {
            $select = \Form::select('cond', $pedido->cliente->condicaoPagamento->pluck('descricao', 'id'), null);
            $select = str_replace('<select name="cond">', '', $select);
            $select = str_replace('</select>', '', $select);
            $pedido->condicaoPagamento = $select;
        } else {
            $pedido->condicaoPagamento = "";
        }
        unset($pedido->cliente);
        return response()->json($pedido);
    }

    /**
     * @param $cliente_id
     * @param int $limit
     * @return array
     */
    public function historicoToCliente($cliente_id, $limit = 15)
    {
        $pedidos = Pedido::with('pedidosituacao', 'condicaopagamento', 'pedidoitem')->where([
            ['cliente_id', $cliente_id],
        ])->select('datahoraprevisaoentrega', 'id', 'pedidosituacao_id', 'condicaopagamento_id')
            ->orderBy('id', 'desc')->limit($limit)->get();

        $historico = [];
        foreach ($pedidos as $pedido) {
            $allowed = Util::isConclCanc($pedido->pedidosituacao);
            if (!is_null($pedido->pedidosituacao) && $allowed) {
                foreach ($pedido->pedidoitem as $item) {
                    $hist = (object) [];
                    $hist->pedido_id = $pedido->id;
                    $hist->data = requestDataOracle($pedido->datahoraprevisaoentrega, false);
                    $hist->produto = $item->produto->descricao;
                    $hist->status = $pedido->pedidosituacao->descricao;
                    $hist->status_tipo = $pedido->pedidosituacao->fechadocancelado == '1' ? 'cancelado' : 'concluido';
                    $hist->quantidade = $item->quantidade;
                    $hist->condicao = $pedido->condicaopagamento->descricao;
                    $hist->valor = requestNumeroDecimalOracle($item->precovendatotal);
                    array_push($historico, $hist);
                }
            }
        }
        return $historico;
    }

    /**
     * @param $id
     * @return string
     */
    public function geraEmite($id)
    {
        try {
            $pedido = Repository::findOrFail($id, 'Pedido');

            $nfEmitidaController = new NfemitidaController();
            if (is_null($pedido->nfce_id)) {
                $this->createNf($nfEmitidaController, $pedido);
            }
            return $this->transmitirNF($nfEmitidaController, $pedido);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param NfemitidaController $nfEmitidaController
     * @param Pedido $pedido
     * @throws Exception
     */
    private function createNF($nfEmitidaController, $pedido)
    {
        $gerar = $nfEmitidaController->processorPedidoNf((new Request()), $pedido->id);
        if (substr($gerar, 0, 7) !== 'Sucesso')
            throw new Exception("Problemas ao gerar NFCe.");

        $nfce_id = isset(explode(':', $gerar)[2]) ? explode(':', $gerar)[2] : null;
        if (is_null($nfce_id))
            throw new Exception("NFCe foi gerada mas não foi possível vinculá-la ao pedido, contate o suporte.");

        // if (!$pedido->update(['nfcegerou' => true, 'nfce_id' => $nfce_id]))
        //     throw new Exception("NFCe foi gerada mas não foi possível vinculá-la ao pedido, contate o suporte.");
    }

    /**
     * @param NfemitidaController $nfEmitidaController
     * @param $pedido
     * @return string
     * @throws Exception
     */
    public function transmitirNF($nfEmitidaController, $pedido)
    {
        $nfemitida = Repository::findOrFail($pedido->nfce_id, 'Nfemitida');

        if ($nfemitida->nfsituacao_id == 100) {
            return "OK|" . $pedido->nfce_id;
        } else {
            $transmite = $nfEmitidaController->transmitirnf($pedido->nfce_id);
            if (substr($transmite, 0, 7) !== 'Sucesso') {
                $cod_erro = isset(explode(':', $transmite)[2]) ? explode(':', $transmite)[2] : null;
                if (is_null($cod_erro))
                    throw new Exception("Erro ao validar NFCe");
                throw new Exception($transmite);
            }
        }
        return "OK|" . $pedido->nfce_id;
    }

    /**
     * @param Request $request
     * @param $id
     * @return string
     * @throws Exception
     */
    public function updateDadosNf(Request $request, $id, $isnfe = false)
    {
        DB::beginTransaction();
        try {
            $data = $request->all();

            $pedido = Repository::findOrFail($id, 'Pedido');

            if ($pedido->nfcegerou) {
                return "Já foi gerado uma nota fiscal para este pedido. "
                    . "Se você clicou 2 vezes no botão, verifique as notas geradas.";
            }

            if (($data['nftipo'] === '1' || $data['nftipo'] === "2") && !empty($data['nfcpfcnpj']) && !$isnfe) {
                $cliente = Cliente::where("empresa_id", $pedido->empresa_id)
                    ->where("id", "<>", $pedido->cliente_id);
                if (strlen($data['nfcpfcnpj']) === 14) {
                    $dataClient['cpf'] = $data['nfcpfcnpj'];
                    $cliente->where("cpf", $data['nfcpfcnpj']);
                } else {
                    $dataClient['cnpj'] = $data['nfcpfcnpj'];
                    $cliente->where("cnpj", $data['nfcpfcnpj']);
                }
                $cliente = $cliente->first();
                if ($cliente) {
                    return "Já existe um cliente com o CPF/CNPJ informado: " . $cliente->nome;
                }
                $cli = Repository::findOrFail($pedido->cliente_id, 'Cliente'); //->update($dataClient);

                if (is_null($cli->indicador_ie) || $cli->indicador_ie == 0) {
                    return "Cliente não possui um Indicador de I.E, favor corrigir no cadastro.";
                }
                $cli->update($dataClient);
            }
            $pedido->update($data);
        } catch (Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
        DB::commit();
        return "OK|";
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function comanda($id)
    {
        try {
            $pedido = Pedido::with([
                'pedidoitem',
                'cliente',
                'entregarua',
                'entregabairro',
                'entregacidade',
                'entregaSetor',
                'atendenteUser',
                'pedidosituacao',
                'colaborador',
                'pedidoOperacao',
                'condicaopagamento'
            ])->find($id);
            $empresa = Empresa::with('cidade', 'bairro', 'rua', 'empresaConfigs')
                ->select('uf', 'razao_social', 'cidade_id', 'bairro_id', 'rua_id', 'telefone1', 'numero', 'cnpj', 'id')
                ->find($pedido->empresa_id);

            $config = $empresa->empresaConfigs->first();

            if (is_null($config)) {
                throw new Exception("Configurações da empresa ainda não definidas");
            }
            $pedido->pedidoitem->load('produto');

            $printer = $this->getUser()->impressoratermica;

            if (strlen($printer) === 0) {
                throw new Exception("A impressora padrão para a impressão do pedido ainda não foi cadastrada para seu usuário");
            }

            $qde = !is_null($config->impressaoqtdviaspedido) ? $config->impressaoqtdviaspedido : 1;

            return response()->json([
                'empresa' => (object) $empresa->toArray(),
                'pedido'  => (object) $pedido->toArray(),
                'qde'     => $qde,
                'printer' => $printer
            ]);
        } catch (Exception $e) {
            return getErrorsException($e);
        }
    }

    /**
     * @return \App\User|null
     */
    private function getUser()
    {
        if (is_null($this->user)) {
            $this->user = \Auth::user();
        }
        return $this->user;
    }

    /**
     * @param $data
     * @param $pedido
     * @throws Exception
     */
    private function updateVendaAtiva($data, $pedido)
    {
        $vendaativa = Vendaativacliente::where([
            "vendaativa_id" => $data["vendaativa_id"],
            "cliente_id"    => $data["cliente_id"]
        ])->get()->first();
        if (is_null($vendaativa)) {
            throw new Exception("Nao foi possível encontrar a venda ativa");
        }
        $vendaativa->update(["pedido_id" => $pedido->id]);
    }

    /**
     * @param $pedido
     * @param $data
     * @return bool
     */
    private function hasChanges($pedido, $data)
    {
        $statusDiff = $pedido->pedidosituacao_id != $data['pedidosituacao_id'];
        $condPgtoDiff = $pedido->condicaopagamento_id != $data['condicaopagamento_id'];
        $colaboradorDiff = $pedido->colaborador_id != $data['colaborador_id'];
        $setorDiff = $pedido->entregasetor_id != $data['entregasetor_id'];

        $validacao = $statusDiff || $condPgtoDiff || $colaboradorDiff || $setorDiff;

        // Checagem da variavel alterou mandada caso o produto seja removido ou inserido produto novo
        // e status ou outras não sejão mudado.
        if (isset($data["alterou"]) && $data["alterou"])
            return true;

        return $validacao;
    }

    private function checkForParcelas($pedido, $condicao, $data = null)
    {
        //vale gás
        if ($condicao->tipo == "5") return;

        $financeiroModel = $pedido->financeiro;
        $baixado = !is_null($financeiroModel) && !is_null($financeiroModel->parcelas()->where('baixado', 1)->where("agrupamento_status", "<=", 1)->first());
        if ($financeiroModel) {
            $msg = "Uma ou mais parcela (s) referente (s) ao pedido número " . $pedido->id . " %s e não pode ser estornada.";
            if ($baixado) {
                throw new Exception(sprintf($msg, "já está baixada"));
            }
            if (!Util::allowedEstornoParcela($financeiroModel->id)) {
                $isEqual = $this->isEqual($pedido, $data);
                $isEntregaNaoRealizada = $this->isEntregaNaoRealizada($pedido, $data);
                if (!$isEqual || $isEntregaNaoRealizada)
                    throw new Exception(sprintf($msg, "está vinculada a um cheque ou boleto"));
            }
        }
    }

    private function isEqual($pedido, $data)
    {
        $condicao = $pedido->condicaopagamento_id === (isset($data["condicaopagamento_id"]) ? $data["condicaopagamento_id"] : "");
        $valorIgual = true;

        if (isset($data["valorvenda"])) $valorIgual = $pedido->valorvenda == $data["valorvenda"];

        return $condicao && $valorIgual;
    }

    private function isEntregaNaoRealizada($pedido, $data)
    {
        if (isset($data["pedidosituacao_id"])) {
            $situacao = Pedidosituacao::find($data["pedidosituacao_id"]);
            if ($situacao) {
                if ($situacao->entregacancelada == "1" || $situacao->fechadocancelado == "1") {
                    return true;
                }
            }
        }
        return false;
    }

    private function insertFinanceiroEntregaGasdoPovo($situacao, $condicao, $pedido)
    {
        if ($pedido->pedidoOperacao->portaria == 1) return;

        $isPedGp = $pedido->gasdopovo == 1;
        $condAllowFin = Util::allwedToCreateFinanceiro($condicao, $situacao);
        $isValeGas = $condicao->tipo == '5';
        // ? Se forma de pagamento permite gerar financeiro
        // ? Checar se o pedido é gas do povo, se não não gerar taxa de entrega
        $isAllowed = ($condAllowFin && $isPedGp) || $isValeGas;

        if ($isAllowed) {
            $financeiro_id = $this->gerarFinanceiroEntregaGasdoPovo($pedido);
            $pedido->update(['financeiroentregagp_id' => $financeiro_id]);
        }
    }

    private function gerarFinanceiroEntregaGasdoPovo($pedido)
    {
        if (
            !$this->empresaConfig->condicaopagamentofretegp_id || !$this->empresaConfig->ccfretegp_id || !$this->empresaConfig->pcfretegp_id ||
            !$this->empresaConfig->produtogp_id || !$this->empresaConfig->condicaopagamentogp_id || !$this->empresaConfig->valorfretegp
        ) {
            return;
        }
        $processor = new financeiroProcessor();
        $raton = new Financeirorateio();
        $dataemissao = $pedido->datahoraprevisaoentrega;
        $valortotal = $this->empresaConfig->valorfretegp;
        //financeiro
        $financeiro = new Financeiro();
        $financeiro->empresa_id = $pedido->empresa_id;
        $financeiro->grupo_id = $pedido->grupo_id;
        $financeiro->cliente_id = $pedido->cliente_id;
        $financeiro->pagarreceber = "R";
        $financeiro->documento = 'ENT' . $pedido->id;
        $financeiro->descricao = ("taxa de entrega: " . $pedido->id);
        $financeiro->cartaonsu = null;
        $financeiro->cartaoautorizacao = null;
        $financeiro->valor = $valortotal;
        $financeiro->condicaopagamento_id = $this->empresaConfig->condicaopagamentogp_id;
        $financeiro->datacompetencia = $dataemissao;
        $financeiro->dataemissao = $dataemissao;
        $processor->setFinanceiro($financeiro);
        //rateio
        $rato = [];
        $raton["centrocusto_id"] = $this->empresaConfig->ccfretegp_id;
        $raton["planoconta_id"] = $this->empresaConfig->pcfretegp_id;
        $raton["valor"] = $valortotal;
        $raton["financeiro_id"] = $processor->getFinanceiro()->id;
        $raton["percentual"] = 1;
        array_push($rato, $raton);
        $processor->setRateios($rato);
        //parcelas
        $processor->setParcelas($this->gerarFinanceiroParcelasEntregaGasdoPovo($financeiro));
        $parc = [];
        $financeiroparcela = new Financeiroparcela();
        $financeiroparcela["datavencimento"] = $dataemissao;
        $financeiroparcela["valor"] = $valortotal;
        $financeiroparcela["empresa_id"] = Session::get('empresa_padrao')->id;
        $financeiroparcela["grupo_id"] = Session::get('empresa_padrao')->grupo_id;
        $financeiroparcela["financeiro_id"] = $processor->getFinanceiro()->id;
        $financeiroparcela["multa"] = 0;
        $financeiroparcela["juros"] = 0;
        $financeiroparcela["desconto"] = 0;
        $financeiroparcela["valorefetivado"] = $valortotal;
        $financeiroparcela["numero"] = 1;
        $financeiroparcela["baixado"] = false;
        $financeiroparcela["datacompetencia"] = $dataemissao;
        $financeiroparcela["pagarreceber"] = $processor->getFinanceiro()->pagarreceber;
        array_push($parc, $financeiroparcela);
        //dados do processor
        $processor->setAgrupar(false);
        $processor->setParcelas($parc);
        $processor->setBaixar(false);
        //gravar
        $gravar = $processor->gravar();
        if (!$gravar) {
            $erro = $processor->getErrors();
            throw new exception(implode(" | ", $erro));
        }
        return $processor->getFinanceiro()->id;
    }

    private function gerarFinanceiroParcelasEntregaGasdoPovo($financeiro)
    {
        $previsao = Carbon::parse($financeiro->dataemissao);
        $parcelasnovo = [];
        $condicaoPagamento = Condicaopagamento::find($this->empresaConfig->condicaopagamentofretegp_id);
        $parcelas = Condicaopagamentoparcela::where('condicaopagamento_id', $this->empresaConfig->condicaopagamentofretegp_id)->get();
        if (count($parcelas) > 0) {
            $valorRestanteParcelas = $financeiro->valor;
            $i = 0;
            $diasParcela = 0;
            foreach ($parcelas as $parcela) {
                $diasParcela += (int) $parcela->dias;
                $valorParcela = round((($parcela->percentualvalor / 100) * $financeiro->valor), 2);
                $valorParcela = $i == count($parcelas) - 1 ? $valorRestanteParcelas : $valorParcela;
                $desconto = 0;
                $valorRestanteParcelas = $valorRestanteParcelas - $valorParcela;
                $parcn = Util::generateParcela($i + 1, $previsao->copy(), $previsao->copy()->addDays($diasParcela), $valorParcela, $desconto);
                $i++;
                array_push($parcelasnovo, $parcn);
            }
        } else if (count($parcelasnovo) == 0 and $condicaoPagamento->num_parcelas > 0) {
            $parcelas = $condicaoPagamento->num_parcelas;
            $diasParcela = $condicaoPagamento->dias_primeira;
            $valorParcelaOriginal = round($financeiro->valor / $parcelas, 2);
            $valorRestanteParcelas = $financeiro->valor;
            for ($i = 1; $i <= $parcelas; $i++) {
                $valorParcela = $i == (int) $parcelas ? $valorRestanteParcelas : $valorParcelaOriginal;
                $valorRestanteParcelas = $valorRestanteParcelas - $valorParcela;
                $desconto = 0;
                $parcn = Util::generateParcela($i, $previsao->copy(), $previsao->copy()->addDays($diasParcela), $valorParcela, $desconto);
                $diasParcela += $condicaoPagamento->dias_primeira;
                array_push($parcelasnovo, $parcn);
            }
        } else if (count($parcelasnovo) == 0) {
            $vencimento = $previsao;
            $parcn = Util::generateParcela(1, $previsao->copy(), $vencimento, $financeiro->valor, 0);
            array_push($parcelasnovo, $parcn);
        }
        return $parcelasnovo;
    }
}
