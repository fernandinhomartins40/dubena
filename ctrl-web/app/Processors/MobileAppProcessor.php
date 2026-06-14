<?php

namespace App\Processors;

use DB;
use App\{
    Bairro,
    Cliente,
    Setor,
    Pedidosituacao as Situacao,
    Rua,
    Valegas,
    Financeiro,
    Pixpedido,
    Pixtransaction
};
use Exception;
use Rede\eRede;
use Rede\Store;
use Rede\Environment;
use Rede\Transaction;
use App\Condicaopagamento;
use App\Helpers\Utils\Util;
use App\Repository\MobileRepository;
use App\Exceptions\RejectedException;
use App\Http\Controllers\AuthController;
use App\Http\Resources\ApiResources;
use App\Services\CarbonCustom as Carbon;
use App\Http\Resources\Classes\AppConfig;
use App\Services\PixService;

class MobileAppProcessor
{
    /**
     * @var AppConfig
     */
    protected $config;

    /**
     * @var string
     */
    protected $msgNotify = "";

    public function createOrder($orderRequest)
    {
        DB::beginTransaction();
        try {
            list($order, $setor) = $this->createOrderFromRequest($orderRequest, false);

            DB::commit();

            if ($orderRequest->pagamento->nfc_tpag == "17") {
                return responseSuccess($order);
            }

            $placa = $this->getOrderVeiculo($order);

            return responseSuccess([
                "cod_pedido"    => $order->id,
                "setor_id"      => $order->entregasetor_id,
                "placa"         => $placa,
                "latLngSetor"   => (object) [
                    "lat" =>    $setor->latitude,
                    "lng" =>    $setor->longitude,
                ]
            ], "OK");
        } catch (RejectedException $e) {
            DB::rollback();
            $withFile = "";
            if ($e->getCode() === 101) {
                Util::notify("Erro ao criar pedido: " . $e->getMessage());
                $withFile = " File: " . $e->getFile() . " Line: " . $e->getLine();
            }

            return responseReject($e->getMessage() . $withFile);
        } catch (Exception $e) {
            DB::rollback();
            $withFile = "";
            if ($e->getCode() !== 101) {
                Util::notify("Erro ao criar pedido: " . $e->getCode() . " " . $e->getMessage() . " File: " . $e->getFile() . " Line: " . $e->getLine());
            }

            if ($e->getCode() !== 102) $withFile = " File: " . $e->getFile() . " Line: " . $e->getLine();

            return responseError($e->getMessage() . $withFile);
        }
    }

    private function createOrderFromRequest(&$orderRequest, $isFromPix)
    {
        $orderClone = null;

        if (!$isFromPix)
            $orderClone = clone $orderRequest;

        $exists = MobileRepository::checkForPendency($orderRequest->cliente->id, $orderRequest->pedidosituacao_id);

        $this->throwIf($exists, "Já existe um pedido pendente, favor consultar a revenda", 102);

        $this->setConfig();

        $this->setPaymentTerms($orderRequest);

        $this->setSector($orderRequest);

        $this->setStatus($orderRequest);

        $this->setAddress($orderRequest->endereco);

        // ! Em 2021-11-10 Por conta de problemas que foi avisado foi requisitado para voltar
        // $this->setClientByAddress($orderRequest);

        // ! Comentado dia 2021-05-10 Quando foi requisitado que o cliente se tornasse o endereço.
        $this->setClient($orderRequest);

        $setor = $orderRequest->setor;
        if (is_null($setor) || $orderRequest->cliente->setor_id != $setor->id) {
            $this->setSector($orderRequest, true);
            $setor = $orderRequest->setor;
        }

        $this->setItems($orderRequest);

        $this->checkForConvenio($orderRequest);

        $this->setDefaults($orderRequest);

        if ($orderRequest->pagamento->nfc_tpag == "17" && !$isFromPix) {
            info("creating pix order " . $orderRequest->pedido_id);
            $pix = $this->processPixOrder($orderRequest, $orderClone);

            return [$pix, null];
        }

        $order = MobileRepository::createOrder($orderRequest, $this->config);

        if (
            isset(((array) $orderRequest)["pagamento_online"])
            && $orderRequest->pagamento_online == true
            && isset($orderRequest->dados_pagamento)
        ) {
            $transacao = static::pagarOnline($order->id, $order->valorvenda, $orderRequest->dados_pagamento);

            if (is_string($transacao)) {
                DB::rollback();

                Util::notify("O cliente: " . $orderRequest->cliente->nome . " Telefone: " . $orderRequest->cliente->phone . " Obteve o seguinte erro ao tentar pagar online: " . $transacao);

                return response()->json([
                    'msg'       => utf8FormatJson($transacao),
                    'rejection' => 'ERRO_PAGAMENTO',
                    'status'     => 'NOK'
                ]);
            }
            DB::connection("sgcm_api")->table('transacoesonline')->insert([
                "erppedido_id"  => $order->id,
                "tid"           => $transacao->getTid()
            ]);

            $financeiro = Financeiro::find($order->financeiro_id);
            if ($financeiro != null) {
                $financeiro->update([
                    "cartaoautorizacao" => $transacao->getNsu(),
                    "cartaonsu"         => $transacao->getNsu()
                ]);
                info("Pedido online NSU" . $transacao->getNsu() . " authorization: " . $transacao->getAuthorizationCode());
            }
        }

        Util::notify("Pedido registrado: " . $order->id . $this->msgNotify, "info");

        return [$order, $setor];
    }

    /**
     * @throws Exception
     */
    private static function pagarOnline($idPedido, float $valorTotal, $cardData)
    {
        $cardNumber = str_replace(" ", "", $cardData->card_number);
        $expirationMonth = str_pad($cardData->expiration_month, 2, '0');

        if (!$cardNumber) {
            throw new Exception('Informe o número do cartão');
        }

        if (!$cardData->holder_name) {
            throw new Exception('Informe o nome impresso cartão');
        }

        if (!$cardData->card_cvv) {
            throw new Exception('Informe o número do CVV');
        }

        $transaction = new Transaction($valorTotal, $idPedido);
        // Transação que será autorizada
        if ($cardData->type === "credito") {
            $transaction = $transaction->creditCard(
                $cardNumber,
                $cardData->card_cvv,
                $expirationMonth,
                $cardData->expiration_year,
                $cardData->holder_name
            );
        } else if ($cardData->type === "debito") {
            $transaction = $transaction->debitCard(
                $cardNumber,
                $cardData->card_cvv,
                $expirationMonth,
                $cardData->expiration_year,
                $cardData->holder_name
            );
        } else {
            throw new \Exception('Não suportado tipo de transação: ' . $cardData['type']);
        }

        $erede = self::getERede();
        try {
            // Autoriza a transação
            $transactionResponse = $erede->create($transaction);

            // Sucesso
            if ($transactionResponse->getReturnCode() == '00') {
                return $transactionResponse;
            } else {
                return static::getError($transactionResponse);
            }
        } catch (Exception $e) {
            return static::getError($transaction);
        }
    }

    private static function getERede()
    {
        $env = env('APP_ENV', "local");
        if (strtolower($env) === 'production') {
            $redePv = env('PRODUCTION_REDE_PV');
            $redeToken = env('PRODUCTION_REDE_TOKEN');
            $environment = Environment::production();
        } else {
            $redePv = env('SANDBOX_REDE_PV');
            $redeToken = env('SANDBOX_REDE_TOKEN');
            $environment = Environment::sandbox();
        }

        $store = new Store($redePv, $redeToken, $environment);
        return new eRede($store);
    }

    /**
     * @throws Exception
     */
    public static function estornarPagamentoOnline($tid, float $valorTotal): Transaction
    {
        $transaction = (new Transaction($valorTotal))->setTid($tid);
        try {

            $erede = self::getERede();
            // Autoriza a transação

            $transactionCancel = $erede->cancel($transaction);

            // Sucesso
            if (
                $transactionCancel->getReturnCode() == '359'
                || $transactionCancel->getReturnCode() == '360'
                || $transactionCancel->getReturnCode() == '355'
            ) {
                return $transactionCancel;
            } else {
                throw new \Exception($transactionCancel->getReturnMessage());
            }
        } catch (Exception $e) {
            if ($transaction->getReturnCode() == '355') {
                return $transaction;
            } else {
                throw new \Exception($transaction->getReturnMessage());
            }
        }
    }

    private static function getError(Transaction $transaction): string
    {
        Util::log("Erro na comunicação com a rede: " . $transaction->getReturnMessage());
        switch ((int) $transaction->getReturnCode()) {
            case 1:
            case 2:
            case 3:
                return "Ano de expiração inválido";
            case 15:
            case 16:
                return "Campo de segurança inválido";
            case 17:
            case 18:
                return "distributorAffiliation: inválido";
            case 25:
            case 26:
                return "affiliation: inválido";
            case 33:
                return "Mês de expiração inválido";
            case 36:
            case 37:
            case 38:
                return "Número do cartão inválido";
            case 51:
            case 53:
                return "Contate a revenda";
            case 55:
            case 59:
                return "Nome impresso no cartã inválido";
            case 83:
            case 84:
            case 82:
            case 80:
            case 58:
                return "Compra não autorizada";
            case 64:
                return "Transação não processada, tente novamente";
            case 89:
            case 65:
                return "Token inválido";
            case 70:
            case 71:
            case 73:
                return "Valor inválido";
            case 72:
                return "Erro: Contate o emissor do cartão.";
            case 74:
                return "Erro de comunicação. Tente novamente";
            case 86:
            case 79:
                return "Cartão expirado";
            case 110:
                return "Tipo de transação não permitida para este cartão";
            case 150:
                return "Tempo esgotado, tente novamente";
            case 153:
                return "Número do documento: número inválido";
            case 173:
                return "Autorização expirada";
            case 899:
                return "Contate a revenda.";
            default:
                return "Erro desconhecido: " . $transaction->getReturnMessage();
        }
    }

    private function setConfig()
    {
        $this->config = new AppConfig();
        $this->config->setConfig()->setDescriptions();
    }

    private function setPaymentTerms(&$order)
    {
        $order->pagamento = Condicaopagamento::find($order->condicaopagamento_id);
        $this->throwIf(
            !$order->pagamento,
            "Condição de Pagamento não encontrada na base de dados ID: " . $order->condicaopagamento_id,
            101
        );
    }

    private function setSector(&$order, $useClientSector = false)
    {
        if (!$useClientSector) {
            $order->setor_id = MobileRepository::findSectorByLatLgn($order->endereco->latitude, $order->endereco->longitude, $this->config);
        } else {
            $order->setor_id = $order->cliente->setor_id;
        }
        $order->setor = Setor::find($order->setor_id);

        $this->throwIf(
            !$order->setor,
            "Setor de entrega não encontrado no endereço selecionado. lat: " . $order->endereco->latitude . " lgn: " . $order->endereco->longitude,
            101
        );

        $order->colaborador_id = MobileRepository::getColaborador($order);
        $this->throwIf(
            !$order->colaborador_id,
            "Colaborador não encontrado no Setor: " . $order->setor->id . " - " . $order->setor->descricao,
            101
        );
    }

    /**
     * @param $pedido
     * @throws RejectedException
     */
    private function setStatus(&$order)
    {
        $order->situacao = Situacao::find($order->pedidosituacao_id);
        $this->throwIf(
            !$order->situacao,
            "Situação de Pedido não encontrada na base de dados ID: " . $order->pedidosituacao_id,
            101
        );
    }

    private function setAddress(&$address)
    {
        $address->cidade_id = MobileRepository::getCityId($this->config, $address);

        $street = MobileRepository::findRoadPossibilities($address->rua, [$address->cidade_id], $address->cep);
        if (!$street) {
            $address->rua_id = $this->config->rua_id;
            $this->msgNotify .= " - Rua: " . $address->rua . " não encontrada no sistema. ";
        } else {
            $address->rua_id = $street->id;
        }

        $district = MobileRepository::findDistrictPossibilities($address->bairro, [$address->cidade_id]);
        if (!$district) {
            $address->bairro_id = $this->config->bairro_id;
            $this->msgNotify .= " - Bairro " . $address->bairro . " não encontrado no sistema.";
        } else {
            $address->bairro_id = $district->id;
        }
    }

    /**
     * Metodo antigo usado quando o cliente era o endereço, mudança que foi retornada
     */
    private function setClientByAddress(&$order, $fromOrder = true)
    {
        $msg = "";
        try {
            $clienteApi = collect($order->cliente);
            $clienteApi->address = $order->endereco;
            $client = MobileRepository::findClientByAddress($order->endereco);

            $clienteApi->setor_id = 103;

            if (is_null($client)) {
                $client = MobileRepository::createClient($clienteApi, $this->config);
            } else {
                $client = MobileRepository::updateClient($client, $clienteApi, true);
            }

            MobileRepository::findOrCreateClientPhone($clienteApi, $client, $this->config, true, true);

            $order->cliente = $client;
            $order->cliente->phone = $clienteApi->get("telefone");
        } catch (Exception $ex) {
            if ($fromOrder) {
                Util::notify($ex->getMessage());
            }

            $msg = "Não foi possível vincular o cadastro ao sistema da revenda." .
                " Cliente: " . $order->cliente->nome .
                " Telefone: " . $order->cliente->telefone;

            $order->cliente = null;
        }

        if ($fromOrder) {
            $this->throwIf(
                !$order->cliente,
                $msg,
                101
            );
        }
    }

    /**
     * Metodo antigo utilizado para quanto o cliente é o numero de telefone e nome
     */
    private function setClient(&$order, $fromOrder = true)
    {
        $msg = "";
        try {
            if ($fromOrder) {
                $clienteApi = collect($order->cliente);
                $clienteApi->address = $order->endereco;
            } else {
                $clienteApi = collect($order);
                $clienteApi->address = $order->endereco;
            }

            $setor = $order->setor;

            $cliente = MobileRepository::findClientPossibilities($clienteApi);

            if (!is_null($cliente) && !is_null($cliente->setor_id)) {
                $clienteApi->setor_id = $cliente->setor_id;
            } else if (!is_null($setor)) {
                $clienteApi->setor_id = $setor->id;
            } else {
                $clienteApi->setor_id = $this->config->setor_id;
            }

            $order->cliente = MobileRepository::createOrUpdateClient($cliente, $clienteApi, $this->config);

            $order->cliente->phone = $clienteApi->get("telefone");

            $order->cliente->load("clienteProduto");

            if ($order->cliente->nome !== $clienteApi->get("nome") && $fromOrder) {
                $this->msgNotify .= " - Cliente vinculado com nome diferente do cadastrado no Sistema Gás em Casa.";
            }
            if ($order->cliente->nome === "Cliente inexistente") {
                $this->msgNotify .= " - Cliente salvo como Cliente inexistente, favor verificar!";
            }
        } catch (Exception $ex) {
            if ($fromOrder) {
                Util::notify($ex->getMessage());
            } else {
                $this->msgNotify = "Erro ao migrar o cliente: $order->nome; id: $order->id; Erro: " . $ex->getMessage();
            }

            $msg = "Não foi possível vincular o cadastro ao sistema da revenda." .
                " Cliente: " . $order->cliente->nome .
                " Telefone: " . $order->cliente->telefone;

            $order->cliente = null;
        }

        if ($fromOrder) {
            $this->throwIf(
                !$order->cliente,
                $msg,
                101
            );
        }
    }

    public function checkForConvenio($order)
    {
        $tipo = $order->pagamento->tipo;

        if ($tipo != "4") return;

        $cliente = $order->cliente->load("convenioempresa.clienteConvenio", "convenioempresa.produtoconvenio");
        $cliente_id = $cliente->id;

        $empresa = $cliente->convenioempresa;
        $convenio = $empresa && $empresa->clienteConvenio ? $empresa->clienteConvenio : null;

        // checa se há convenio e as compras que foram feitas com o convenio
        $this->checkConvenioDisponivel($cliente, $convenio, $empresa);

        $convenioDisponivel = false;
        $fechamento = getProximoVencimento($convenio->diafechamento);
        $fechamentoAnterior = Carbon::parse($fechamento)->subMonth()->toDateTimeString();

        $totalcomprasConvenio = intVal(DB::table('pedidos as p')->join('pedidosituacaos as s', 's.id', 'p.pedidosituacao_id')
            ->join('condicaopagamentos as cond', function ($join) {
                $join->on('cond.id', 'p.condicaopagamento_id')->where('tipo', '4');
            })
            ->join('pedidoitems as it', function ($join) {
                $join->on('it.pedido_id', 'p.id')->whereRaw("fechadocancelado <> 1 AND entregacancelada <> 1");
            })
            ->where([
                ['cliente_id', $cliente_id],
                ['p.id', '!=', null]
            ])->whereBetween('datahoraprevisaoentrega', [$fechamentoAnterior, $fechamento])
            ->selectRaw("sum(quantidade) as quantidade")->get()->first()->quantidade);

        $qtdeProd = 0;
        $prodNotFound = false;
        $produtoFailed = "";
        foreach ($order->items as $item) {
            $qtdeProd += $item->quantidade;

            $prod = $empresa->produtoconvenio->first(function ($prod) use ($item) {
                return $prod->produto_id == $item->product->id;
            });

            if (is_null($prod)) {
                $prodNotFound = true;
                $produtoFailed = $item->product->descricao;
            }
        }

        $this->throwIf($prodNotFound, "Produto $produtoFailed não disponivel para convênio.", 102);

        $totalcomprasConvenio += $qtdeProd;

        if ($cliente->convenio && $totalcomprasConvenio <= $convenio->limitecompra) {
            $convenioDisponivel = true;
        } else {
            if ($totalcomprasConvenio <= $cliente->conveniolimite) {
                $convenioDisponivel = true;
            }
        }

        $this->throwIf(
            !$convenioDisponivel,
            "Não há limite o suficiente no convênio para realizar a compra.",
            102
        );
    }

    private function setItems(&$order)
    {
        $productsDb = MobileRepository::getProductsOrder(array_pluck($order->items, "produto_id"));

        foreach ($order->items as &$item) {
            $item->product = $productsDb->where("id", $item->produto_id)->first();

            $this->throwIf(
                !$item->product,
                "Produto com o código " . $item->produto_id . " foi encontrado na base de dados.",
                101
            );
        }
    }

    private function setDefaults(&$order)
    {
        $order->pedidooperacao_id = $this->config->pedidooperacao_id;
        $this->throwIf(
            !$order->pedidooperacao_id,
            "Operação Padrão de pedido não definida",
            101
        );
    }

    /**
     * @param $apiorder_id
     * @param string $from
     * @return mixed
     */
    public function getLastPosition($apiorder_id, $from = "inside")
    {
        $order = MobileRepository::trackPedido($apiorder_id);
        if ($from === "external") {
            return $order;
        } else if ($order && $order->placa) {
            $setor = MobileRepository::trackFromPlaca($order->placa);

            if ($setor && $order->monitorado_aplicativo) {
                $order->motorista = $setor->motorista ? $setor->motorista : "Motorista não encontrado.";
                $order->location = $setor->latitude && $setor->longitude ? (object) [
                    "latitude"  => $setor->latitude,
                    "longitude" => $setor->longitude,
                    "azimuth"   => $setor->azimute
                ] : null;
            } else {
                $order->motorista = "Motorista não encontrado.";
                $order->location = null;
            }
        }
        return $order;
    }

    private function throwIf($condition, $msg, $code = 500)
    {
        if ($condition) {

            switch ($code) {
                case 101:
                    throw new RejectedException($msg, $code);

                default:
                    throw new Exception($msg, $code);
                    break;
            }
        }
    }

    public function getClientHistory($prodApi, $client_id)
    {
        $cliente = Cliente::whereApiId($client_id)->first();

        $this->throwIf(!$cliente, "Cliente não encontrado.", 101);

        $pedidosDB = MobileRepository::getOrderHistory($cliente->id, $prodApi->pluck("erp_id"));
        $pedidos = collect([]);
        $unique = $pedidosDB->unique("pedido_id");

        foreach ($unique as $pedido) {
            $ped = (object) [
                "codigo_pedido" => $pedido->pedido_id,
                "data"          => $pedido->datahora,
                "cancelado"     => $pedido->cancelado == 1,
            ];
            $ped->items = collect([]);
            $this->filterHistory($pedidosDB, $pedido, $ped, $prodApi);

            $pedidos->push($ped);
        }

        return $pedidos;
    }

    private function filterHistory(&$pedidosDB, $pedido, $ped, $prodApi)
    {
        $pedidosDB->reject(function ($item) use ($pedido, $ped, $prodApi) {
            if ($item->pedido_id === $pedido->pedido_id) {
                $item = collect($item)->only([
                    "produto_id",
                    "precovendaunitario",
                    "quantidade",
                    "precovendatotal",
                    "pedido_id"
                ]);

                $item->put("descricao", $prodApi->first(function ($itemfirst) use ($item) {
                    return $itemfirst->erp_id === $item->get("produto_id");
                }));

                if ($pedido->pedido_id === $item->get("pedido_id")) {
                    $ped->items->push($item);
                }
                return true;
            }
            return false;
        });
    }

    public static function getGBByCode($code)
    {
        return Valegas::where("codigo", $code)
            ->join("valegassituacao as sit", "sit.id", "valegassituacao_id")
            ->selectRaw("produto_id, sit.descricao as situacao, codigo")
            ->first();
    }

    public function migrateAddress($enderecos)
    {
        $this->setConfig();

        if ($enderecos) {
            $enderecos = json_decode($enderecos);
        } else {
            return;
        }

        $bairros = collect([]);
        $ruas = collect([]);
        foreach ($enderecos as $end) {
            $bairro_exists = $bairros->filter(function ($item) use ($end) {
                return $item["descricao"] === $end->bairro;
            })->first();
            if (!$bairro_exists) {
                $bairro = MobileRepository::findDistrictPossibilities($end->bairro, [$this->config->cidade_id]);
                if ($bairro) {
                    $bairros->push($bairro);
                } else {
                    $bairro = $this->createBairro($end);
                    $bairros->push($bairro);
                }
            }

            $rua_exists = $ruas->filter(function ($item) use ($end) {
                return $item["descricao"] === $end->rua;
            })->first();
            if (!$rua_exists) {
                $rua = MobileRepository::findRoadPossibilities($end->rua, [$this->config->cidade_id], $end->cep);
                if ($rua) {
                    $ruas->push($rua);
                } else {
                    $rua = $this->createRua($end, $bairro);
                    $ruas->push($rua);
                }
            }
            $message = "Bairro e Rua migrado: Bairro: $bairro->descricao; Id: $bairro->id; " .
                "Rua: $rua->descricao; Id: $rua->id";
            Util::log($message);
        }

        return;
    }

    private function createBairro($endereco)
    {
        $dado = [];
        $dado["cidade_id"] = $this->config->cidade_id;
        $dado["grupo_id"] = $this->config->grupo_id;
        $dado["descricao"] = $endereco->bairro;

        $bairro = Bairro::create($dado);

        return $bairro;
    }

    private function createRua($endereco, $bairro)
    {
        $dado = [];

        $dado["grupo_id"] = $this->config->grupo_id;
        $dado["empresa_id"] = $this->config->empresa_id;
        $dado["bairro_id"] = $bairro->id;
        $dado["cidade_id"] = $this->config->cidade_id;
        $dado["descricao"] = $endereco->rua;
        $dado["ativo"] = true;
        $dado["cep"] = $endereco->cep;
        $dado["nfecompl"] = 'Rua';
        $dado["importacaocep_id"] = 0;

        $rua = Rua::create($dado);

        return $rua;
    }

    public function migrateClients($clients)
    {
        $this->setConfig();

        if ($clients) {
            $clients = json_decode($clients);
        } else {
            return;
        }

        foreach ($clients as $client) {
            $endereco = (object) [];
            $endereco->rua = $client->rua;
            $endereco->complemento = $client->complemento;
            $endereco->numero = $client->numero;
            $endereco->longitude = $client->longitude;
            $endereco->latitude = $client->latitude;
            $endereco->cep = $client->cep;
            $endereco->cidade = $client->cidade;
            $endereco->uf = $client->uf;
            $endereco->bairro = $client->bairro;
            $endereco->pontoreferencia = $client->pontoreferencia;
            $this->setAddress($endereco);

            $clientApi = (object) [];
            $clientApi->id = $client->id;
            $clientApi->nome = $client->nome;
            $clientApi->email = $client->email;
            $clientApi->cpf = null;
            $clientApi->user_id = $client->user_id;
            $clientApi->ativo = 1;
            $clientApi->datanascimento = $client->datanascimento;
            $clientApi->enderecopadrao_id = $client->enderecopadrao_id;
            $clientApi->sexo = $client->sexo;
            $clientApi->telefone = $client->telefone;
            $clientApi->endereco = $endereco;
            $this->setClient($clientApi, false);

            $message = "Cliente: Nome App: $clientApi->nome; Id App: $clientApi->id;";

            if ($clientApi->cliente) {
                $message .= " Nome web: " . $clientApi->cliente->nome . "; Id web: " . $clientApi->cliente->id;
            }

            $message .= " Avisos: " . $this->msgNotify;
            Util::log($message);

            $this->msgNotify = "";
        }

        return;
    }

    public function getClienteErp($cpf, $telefone)
    {
        $cpf = onlyNumbers($cpf);
        $telefone = onlyNumbers($telefone);

        return MobileRepository::getClientByCpf($cpf, $telefone);
    }

    public function checkConveniado($clienteApi)
    {
        $cliente = $this->getClienteErp($clienteApi->cpf, $clienteApi->telefone);

        $this->throwIf(is_null($cliente), "Cpf não vinculado a nenhum convênio. Entre em contato com a revenda!");

        $cliente = $cliente->load("convenioempresa.clienteConvenio");

        $empresa = $cliente->convenioempresa;
        $convenio = $empresa && $empresa->clienteConvenio ? $empresa->clienteConvenio : null;

        $this->checkConvenioDisponivel($cliente, $convenio, $empresa);

        return $cliente;
    }

    public function checkConvenioDisponivel($cliente, $convenio, $empresa)
    {
        if ($cliente->convenio_id && $convenio && $empresa->convenioativo == "1" && $cliente->convenio == "1") return true;

        $this->throwIf(
            !is_null($empresa) && $empresa->convenioativo != "1" && $cliente->convenio == "1",
            "Infelizmente o convênio da empresa foi desativado, entre em contato com a revenda.",
            102
        );

        $this->throwIf(
            !is_null($empresa) && $empresa->convenioativo == "1" && $cliente->convenio != "1",
            "Infelizmente seu convênio está inativo, entre em contato com a revenda.",
            102
        );

        $this->throwIf(
            true,
            "Você não está vinculado a nenhum convênio.",
            102
        );
    }

    private function getOrderVeiculo($order)
    {
        $placa = null;
        try {
            $veiculo = $this->getLastPosition($order->apipedido_id, "external");

            $this->throwIf(!$veiculo, "Veículo não encontrado!");

            $placa = $veiculo->placa;
        } catch (Exception $e) {
            Util::notify("Erro desconhecido ao buscar dados do veículo de entrega para o pedido código [" . $order->id . "]:" . $e->getMessage());
        }

        return $placa;
    }

    public function checkForApiId($cliente, $clienteApi)
    {
        $api_id = $clienteApi->id;
        $cliente_id = $cliente->id;

        $qryClientes = Cliente::where("api_id", $api_id)
            ->where("id", "<>", $cliente_id);

        $clientesSameApiId = $qryClientes->get();

        if ($clientesSameApiId->isEmpty()) return;

        $data["api_id"] = null;
        $data["endereco_app"] = null;
        $data["nome_app"] = null;
        $data["latitude_app"] = null;
        $data["longitude_app"] = null;

        $updated = $qryClientes->update($data);

        return $updated;
    }

    private function processPixOrder($order, $orderClone)
    {
        if ($this->checkForExistance($order)) {
            throw new \Exception("Pix para este pedido já existe");
        }

        unset($order->endereco->cliente);

        MobileRepository::makeProductsArray($order, false);

        $data = [];
        $data["clienteapi_id"] = $orderClone->cliente->id;
        $data["pedidoapi_id"] = $order->pedido_id;
        $data["valorvenda"] = $order->valorvenda;
        $data["empresa_id"] = $this->config->empresa_id;
        $data["grupo_id"] = $this->config->grupo_id;
        $data["json_data"] = json_encode($orderClone);

        $pixpedido = Pixpedido::create($data);
        info("pixpedido criado " . $pixpedido->id . " generating pix...");

        return $this->generatePix($pixpedido);
    }

    private function generatePix($order)
    {
        try {
            $service = new PixService($order, null, true);

            return $service->getTransaction();
        } catch (\Exception $e) {
            Util::log($e);
            throw new \Exception("Erro ao gerar o PIX!", 101);
        }
    }

    private function checkForExistance($order)
    {
        return Pixpedido::where("pedidoapi_id", $order->pedido_id)->exists();
    }

    public function getPix($pedido_id)
    {
        $pedido = Pixpedido::where("pedidoapi_id", $pedido_id)->first();

        if (is_null($pedido))
            throw new Exception("Nenhum pagamento pendente encontrado para o pedido");

        $service = new PixService($pedido, null, true);

        info("getting transaction but throwing if not found");
        return $service->getTransaction(true);
    }

    public function createPixOrder($pedido_id)
    {
        DB::beginTransaction();
        try {
            $pixPedido = Pixpedido::where("pedidoapi_id", $pedido_id)->first();
            $transaction = Pixtransaction::where("pixpedido_id", $pixPedido->id)->first();

            $this->loginFromApi();

            $jsonData = json_decode($pixPedido->json_data);

            list($order, $setor) = $this->createOrderFromRequest($jsonData, true);

            $placa = $this->getOrderVeiculo($order);

            $pedido = [
                "cod_pedido"    => $order->id,
                "pedidoapi_id"  => $pedido_id,
                "setor_id"      => $order->entregasetor_id,
                "placa"         => is_null($placa) ? " " : $placa,
                "latLngSetor"   => (object) [
                    "lat" =>    $setor->latitude,
                    "lng" =>    $setor->longitude,
                ]
            ];

            $config = new AppConfig();

            $config->setConfig();

            $url = str_finish($config->api_url, '/');

            $api = new ApiResources($url);

            $api->setMethod("POST");

            $api->setAuthorizationCode($config->api_authorization);

            $api->post($pedido, "api/order/pixpaid");

            $transaction->update([
                "pedido_id" => $order->id
            ]);

            $pixPedido->delete();

            DB::commit();
        } catch (Exception $ex) {
            DB::rollback();
            Util::log($ex);
            Util::notify("Um erro aconteceu ao tentar gerar um pedido pago pelo PIX.");
            throw $ex;
        }
    }

    private function loginFromApi()
    {
        $auth = new AuthController();
        $authData = [
            "email"     => env("DEFAULT_USER_SYSTEM"),
            "password"  => env("DEFAULT_PASSWORD_SYSTEM")
        ];

        return $auth->loginFromApi($authData);
    }
}
