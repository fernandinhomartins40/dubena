<?php

namespace App\Repository;

use App\Empresaconfig;
use App\Helpers\Utils\Util;
use App\{
    Rua,
    Bairro,
    Cidade,
    Cliente,
    Clientetelefone,
    Empresa,
    Pedido,
    Produto,
    Setorcolaboradores
};
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PedidoController;
use App\Http\Requests\ClienteRequest;
use App\Http\Requests\PedidoRequest;
use App\Http\Resources\Classes\AppConfig;
use App\Services\CarbonCustom as Carbon;

class MobileRepository extends BaseRepository
{

    private static $toReplace = [
        "rua ",
        "avenida ",
        "rodovia ",
        "r. ",
        "rod. ",
        "av. ",
        "r ",
        "rod ",
        "av "
    ];
    /**
     * @param $lat
     * @param $lgn
     * @return null|string|int|float
     * @throws \Exception
     */
    public static function findSectorByLatLgn($lat, $lgn, $config)
    {
        $setor_id = null;
        $cercasAll = \DB::connection("monitora")
            ->table('cercapoligonos as pol')
            ->join('cercas as cer', 'pol.cerca_id', 'cer.id')
            ->join('setors as set', 'cer.setor_id', 'set.id')
            ->selectRaw('pol.latitude, pol.longitude, pol.cerca_id, set.setor_ctrlmais as setor_id')
            ->get();

        $setores = $cercasAll->unique('setor_id');
        foreach ($setores as $setor) {
            $cercas = $cercasAll->where('setor_id', $setor->setor_id);
            if (Util::pointInPolygon($lat, $lgn, $cercas)) {
                $setor_id = $setor->setor_id;
                break;
            }
        }

        if (!$setor_id) {
            $setor_id = static::getSectorPlataforma($config);
            if ($setor_id) {
                Util::notify(
                    "Nenhum setor encontrado para a latitude e longitude do cliente, pedido criado usando o SETOR PRINCIPAL DA EMPRESA ",
                    "alert"
                );
            }
        }

        return $setor_id;
        // return 103;
    }

    /**
     * @return mixed
     */
    public static function getSectorPlataforma($appConfig)
    {
        $config = Empresaconfig::whereEmpresaId($appConfig->empresa_id)->first();

        if (!$config) {
            throw new \Exception("Configurações da empresa não encontrada");
        }

        return $config->setorprincipal_id;
    }

    public static function findRoadPossibilities($desc, $cities, $cep)
    {
        $results = static::getRoadByDesc($desc, $cities, $cep);

        $filtered = $results->filter(function ($street) use ($desc) {
            return strtoupper($street->descricao) === strtoupper($desc);
        })->first();

        $replaced = str_replace(static::$toReplace, "", strtolower($desc));
        if (!$filtered) {
            $filtered = $results->filter(function ($street) use ($replaced) {
                return strtoupper($street->descricao) === strtoupper($replaced);
            })->first();
        }

        if (!$filtered) {
            $rep = static::$toReplace;
            $filtered = $results->filter(function ($street) use ($replaced, $rep) {
                $desc = str_replace($rep, "", strtolower($street->descricao));
                return strtoupper($desc) === strtoupper($replaced);
            })->first();
        }

        $exploded = explode(" ", $desc);
        array_shift($exploded);

        $descricaoPart = implode(" ", $exploded);
        if (!$filtered) {
            $filtered = $results->filter(function ($street) use ($descricaoPart) {
                return strtoupper($street->descricao) === strtoupper($descricaoPart);
            })->first();
        }

        $street = $filtered ? $filtered : $results->first();

        if (!is_null($street)) return $street;

        $config = new AppConfig();
        $config->setConfig();

        return static::createStreet($desc, $cities[0], $config);
    }

    private static function getRoadByDesc($desc, $city, $cep = null)
    {
        $desc = str_replace("'", "''", $desc);
        $exploded = explode(" ", $desc);
        array_shift($exploded);

        $replaced = str_replace(static::$toReplace, "", strtolower($desc));

        $descricaoPart = implode(" ", $exploded);
        $raw = "((" . rawTranslateSpecialChars("descricao") . " LIKE '%" . str_encode_to_query($desc) . "%' ";

        if ($descricaoPart) {
            $raw .= ") OR (" . rawTranslateSpecialChars("descricao") . " LIKE '%" . str_encode_to_query($descricaoPart) . "%' ";
        }

        $raw .= ") OR (" . rawTranslateSpecialChars("descricao") . " LIKE '%" . str_encode_to_query($replaced) . "%')";

        if ($cep) {
            $raw .= " OR (cep = '$cep')) ";
        } else {
            $raw .= ") ";
        }

        $results = Rua::whereRaw($raw)
            ->selectRaw("id, descricao")
            ->orderBy("descricao");

        if ($city) {
            is_array($city) ? $results->whereIn("cidade_id", $city) : $results->whereRaw("cidade_id = " . $city);
        }

        return $results->get();
    }

    public static function findDistrictPossibilities($desc, $cities)
    {
        $results = static::getDistrictByDesc($desc, $cities);

        $filtered = $results->filter(function ($district) use ($desc) {
            return strtoupper($district->descricao) === strtoupper($desc);
        })->first();

        $district = $filtered ? $filtered : $results->first();

        if (!is_null($district)) return $district;

        $config = new AppConfig();
        $config->setConfig();

        return static::createDistrict($desc, $cities[0], $config);
    }

    /**
     * @param $desc
     * @param $city
     * @return mixed
     */
    private static function getDistrictByDesc($desc, $city)
    {
        $desc = "'%$desc%'";
        $results = Bairro::whereRaw(rawTranslateSpecialChars("descricao") . " LIKE " . rawTranslateSpecialChars($desc))
            ->selectRaw("id, descricao")
            ->orderBy("descricao");

        if ($city) {
            is_array($city) ? $results->whereIn("cidade_id", $city) : $results->whereRaw("cidade_id = " . $city);
        }

        return $results->get();
    }

    public static function findClientPossibilities($clientApi)
    {
        $raw = "api_id = " . $clientApi->get("id");

        if ($clientApi->get("cpf")) {
            $raw .= " OR (regexp_replace(cpf, '[^0-9]', '') = '" . onlyNumbers($clientApi->get("cpf")) . "')";
        }
        $clientsA = Cliente::from("clientes as cli")->whereRaw($raw)->get();

        $raw = "";
        if ($clientApi->get("telefone")) {
            $selectTel = "(SELECT cliente_id FROM clientetelefones WHERE " .
                "(regexp_replace(telefone, '[^0-9]', '') = '" . onlyNumbers($clientApi->get("telefone")) . "'))";
            $raw = "id IN $selectTel";
        }

        $clientsB = collect([]);
        if (!empty($raw)) {
            $clientsB = Cliente::from("clientes as cli")->whereRaw($raw)->get();
        }

        $clients = $clientsA->merge($clientsB);

        $first = null;
        $filtered = $clients->filter(function ($client) use ($clientApi) {
            return (int) $client->api_id === (int) $clientApi->get("id");
        });

        if (!$filtered->count()) {
            $filtered = $clients->filter(function ($client) use ($clientApi) {
                return $clientApi->get("cpf") && !is_null($client->cpf) ? onlyNumbers($client->cpf) == onlyNumbers($clientApi->get("cpf")) : false;
            });
        }

        if (!$filtered->count()) {
            $filtered = $clients;
        }

        $first = $filtered->first();

        return $first;
    }

    public static function createOrUpdateClient($client, $clientApi, $config, $isApi = true)
    {
        if ($client) {
            static::updateClient($client, $clientApi);
        } else {
            static::validaEndereco($clientApi);
            $client = static::createClient($clientApi, $config);
        }

        $phones = static::findOrCreateClientPhone($clientApi, $client, $config, $isApi);

        return $isApi ? $client : $phones;
    }

    private static function validaEndereco(&$clientApi, $try = 1)
    {
        try {
            $cont = new ClienteController();
            $data = [];
            $data["cidade_id"] = $clientApi->address->cidade_id;
            $data["bairro_id"] = $clientApi->address->bairro_id;
            $data["rua_id"] = $clientApi->address->rua_id;
            $data["numero"] = $clientApi->address->numero;
            $data["complemento"] = $clientApi->address->complemento;
            $data["ponto_referencia"] = $clientApi->address->pontoreferencia;
            $cont->verificaEndereco($data);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $brkMsg = explode(':', $msg);
            $nome = isset($brkMsg[1]) ? $brkMsg[1] : "";

            if ($try > 4) return;

            if ($try > 1) {
                $str = "Cliente App";

                if ($try > 2) {
                    $val = $try - 1;
                    $str = "Cliente App $val";
                }

                $clientApi->address->complemento = str_replace($str, "", $clientApi->address->complemento);
            }

            Util::log($msg);
            if ($try == 1) {
                Util::log("Inserindo complemento 'Cliente App'");
                $clientApi->address->complemento .= " Cliente App";
            } else if ($try > 1) {
                Util::log("Cliente já possui 'Cliente App' no complemento, adicionando 'Cliente App $try'");
                $clientApi->address->complemento .= " Cliente App $try";
                Util::notify("Um cliente de APP se cadastrou no mesmo endereço do cliente:$nome - favor verificar!");
            }

            $nextTry = intval($try) + 1;
            static::validaEndereco($clientApi, $nextTry);
        }
    }

    public static function updateClient(Cliente $client, $clientApi, $replace = false)
    {
        $data = [];
        $data["api_id"] = $clientApi->get("id");

        $nasc = $clientApi->get("datanascimento");
        if ($nasc) $data["datanascimento"] = $nasc;

        $cpf = $clientApi->get("cpf");
        if ($cpf) $data["cpf"] = $cpf;

        $sexo = $clientApi->get("sexo");
        if ($sexo) $data["sexo"] = substr($sexo, 0, 1);

        $gasdopovo = $clientApi->get("gasdopovo");
        if ($gasdopovo) $data["gasdopovo"] = !!$gasdopovo;

        if ($clientApi->setor_id) $data["setor_id"] = $clientApi->setor_id;

        $data["endereco_app"]  = Util::formatAddress(collect($clientApi->address));
        $data["latitude_app"]  = $clientApi->address->latitude;
        $data["longitude_app"] = $clientApi->address->longitude;
        $data["nome_app"] = $clientApi->get("nome");

        if ($replace) {
            $data["nome"] = $clientApi->get("nome");
            $telefone = $clientApi->get("telefone");

            $jsonCli = json_encode($client);
            $jsonDat = json_encode($data);

            $msg = "Cliente: $jsonCli foi substituído por $jsonDat com telefone: $telefone";

            Util::logDifferentFile($msg . PHP_EOL);

            $msg = "Cliente: $client->nome com id $client->id foi substituído por " . $data["nome"];

            Util::notify($msg);

            static::deleteOtherApiId($client->id, $clientApi->get("id"));
        }

        $client->update($data);

        return $client;
    }

    public static function createClient($client, AppConfig $config)
    {
        $sexo = $client->get("sexo") ? substr($client->get("sexo"), 0, 1) : null;

        $nascimento = $client->get("datanascimento");
        if ($nascimento)
            $nascimento = Carbon::parse($nascimento)->format("d/m/Y");

        $data = [];

        $data["api_id"] = $client->get("id");
        $data["endereco_app"] = Util::formatAddress(collect($client->address));
        $data["latitude_app"] = $client->address->latitude;
        $data["longitude_app"] = $client->address->longitude;
        $data["nome"] = strtoupper($client->get("nome"));
        $data["segmento_id"] = $config->segmento_id;
        $data["cpf"] = $client->get("cpf");
        $data["rg"] = null;
        $data["sexo"] = $sexo;
        $data["datanascimento"] = $nascimento;
        $data["cliente"] = 1;
        $data["fornecedor"] = 0;
        $data["ativo"] = 1;
        $data["cep"] = $client->address->cep;
        $data["uf"] = $config->uf;
        $data["cidade_id"] = $client->address->cidade_id;
        $data["bairro_id"] = $client->address->bairro_id;
        $data["rua_id"] = $client->address->rua_id;
        $data["numero"] = $client->address->numero;
        $data["complemento"] = $client->address->complemento;
        $data["ponto_referencia"] = $client->address->pontoreferencia;
        $data["setor_id"] = $client->setor_id;
        $data["email"] = $client->get("email");
        $data["tipopessoa_id"] = $config->tipoPessoa_id;
        $data["gasdopovo"] = !!$client->get("gasdopovo");

        $req = new ClienteRequest();
        $req->replace($data);

        $cont = new ClienteController();
        $response = $cont->store($req, (new Cliente()));
        if ($response["status"] === "OK") {
            return $response["data"];
        } else {
            $msg = $response["msg"];
            throw new \Exception($msg);
        }
    }

    public static function findOrCreateClientPhone($clientApi, $client, $config, $notify, $replaceIfFound = false)
    {
        $phoneNumber = onlyNumbers($clientApi->get("telefone"));

        if (strlen($phoneNumber) === 0) return null;

        $telefones = $client->telefones()->whereRaw("regexp_replace(telefone, '[^0-9]', '') <> '$phoneNumber'")->get();

        if ($telefones->isNotEmpty()) {
            $numeros = [];

            foreach ($telefones as $tel) {
                array_push($numeros, $tel->telefone);
                $tel->delete();
            }

            $numeros = implode(", ", $numeros);
            Util::logDifferentFile("Cliente id: $client->id foram deletado os seguintes telefones: $numeros");
            Util::notify("Cliente id: $client->id foram deletado os seguintes telefones: $numeros");
        }

        $phones = static::findPhonePossibilities($phoneNumber);

        if ($phones->isNotEmpty()) {
            return static::validatePhones($phones, $clientApi, $client, $notify, $replaceIfFound);
        } else {
            return static::createPhone($clientApi, $client, $config);
        }
    }

    private static function findPhonePossibilities($number)
    {
        return Clientetelefone::whereRaw("regexp_replace(telefone, '[^0-9]', '') = '$number'")
            ->get();
    }

    private static function validatePhones($phones, $clientApi, $client, $notify, $replaceIfFound)
    {
        $phone = $phones->first();

        if ($phones->count() > 1) {
            if ($replaceIfFound) {
                static::deletePhonesAndNotify($phones, $client);
            }

            if (!$notify) {
                return "Telefone encontrado para mais de um cliente! Verifique os registros e tente novamente.";
            }
        }

        if (!$phone) {
            return null;
        }

        $isDifferent = (int) $phone->cliente_id !== (int) $client->id;

        if ($replaceIfFound && $isDifferent) {
            $msg = "Telefone " . $clientApi->get("telefone") .
                " já registrado para outro cliente existente: $phone->cliente_id  será vinculado ao cliente: $client->id";

            Util::notify($msg);

            Util::logDifferentFile($msg . PHP_EOL);

            $phone->update(["cliente_id" => $client->id]);

            return;
        }

        if ($isDifferent) {
            $msg = "Telefone " . $clientApi->get("telefone") .
                " já registrado para outro cliente existente: " .
                $phone->cliente_id;
            if (!$notify) {
                return $msg;
            }
        } else {
            if (!$notify) {
                return $phone->cliente_id;
            }
        }

        return null;
    }

    private static function createPhone($clientApi, $client, $config)
    {
        $phone = Clientetelefone::create([
            "grupo_id"          => $config->grupo_id,
            "empresa_id"        => $config->empresa_id,
            "cliente_id"        => $client->id,
            "telefone"          => $clientApi->get("telefone"),
            "telefonetipo_id"   => $config->tipoTelefone_id,
            "whatsapp"          => 0
        ]);

        return $phone;
    }

    public static function getProductsOrder($ids)
    {
        return Produto::whereIn("id", $ids)->select("id", "descricao")->get();
    }

    /**
     * @param stdClass $order
     * @param AppConfig $config
     * @param bool $isappNf
     *
     * @return Pedido $pedido
     */
    public static function createOrder($order, AppConfig $config, $isAppNf = false)
    {
        try {
            $produtos = static::makeProductsArray($order, $isAppNf);
            $observacao = makeObs($order->cliente, true);
            $data = Carbon::parse($order->datahoraprevisao)->format("d/m/y H:i:s");
            $data = [
                "datahoraacao"              => $data,
                "datahoraprevisaoentrega"   => $data,
                "cliente_id"                => $order->cliente->id,
                "entregacep"                => $order->endereco->cep,
                "ufentrega"                 => $order->endereco->uf,
                "entregacidade_id"          => $order->endereco->cidade_id,
                "entregabairro_id"          => $order->endereco->bairro_id,
                "entregarua_id"             => $order->endereco->rua_id,
                "entreganumero"             => $order->endereco->numero,
                "entregacomplemento"        => str_limit($order->endereco->complemento, 40),
                "entregapontoreferencia"    => $order->endereco->pontoreferencia,
                "entregatelefone"           => $order->cliente->phone,
                "pedidooperacao_id"         => $order->pedidooperacao_id,

                "entregasetor_id"           => $order->setor->id,
                // "entregasetor_id"           => $isAppNf ? $order->setor->id : $config->setor_id,
                "colaborador_id"            => $order->colaborador_id,
                // "colaborador_id"            => $isAppNf ? $order->colaborador_id : $config->colaborador_id,

                "condicaopagamento_id"      => $order->pagamento->id,
                "produtospedido"            => json_encode($produtos),
                "empresa_id"                => $order->setor->empresa_id,
                "apipedido_id"              => $order->pedido_id,
                "valorvenda"                => requestNumeroDecimalOracle($order->valorvenda - (isset($order->valordesconto) ? $order->valordesconto : 0)),
                "entregataxa"               => 0,
                "numerocartao"              => "",
                "pedidosituacao_id"         => $order->pedidosituacao_id,
                "valordesconto"             => isset($order->valordesconto) ? requestNumeroDecimalOracle($order->valordesconto) : 0,
                "nfweb"                     => isset($order->nfweb),
                "nfweb_vencimento"          => isset($order->nfweb_vencimento) ? $order->nfweb_vencimento : null,
                "cartaoautorizacao"         => isset($order->cartaoautorizacao) ? $order->cartaoautorizacao : null,
                "observacao"                => $observacao
            ];
            $pedidoC = new PedidoController();
            $request = new PedidoRequest();
            $request->replace($data);
            $response = $pedidoC->store($request, (new Pedido()), true);

            if ($response["status"] === "OK") {
                $pedido = $response["data"];

                if (!$isAppNf) {
                    $pedidoC->checaStatusPedido($pedido->id);
                }

                return $pedido;
            } else {
                $msg = $response["msg"];
                throw new \Exception($msg);
            }
        } catch (\Exception $ex) {
            Util::notify($ex->getMessage(), "error");
        }
    }

    public static function makeProductsArray(&$order, $isAppNf)
    {
        $produtos = [];
        $valorvenda = 0;
        foreach ($order->items as $prod) {
            $preco = static::getPreco($prod, $order, $isAppNf);
            $unit = str_replace('.', ',', $preco);
            array_push($produtos, [
                $prod->produto_id,
                $prod->product->descricao,
                $unit,
                $prod->quantidade
            ]);
            $valorvenda += floatval($preco * $prod->quantidade);
        }
        $order->valorvenda = $valorvenda;
        return $produtos;
    }

    private static function getPreco($produto, $order, $isAppNf)
    {
        $preco = $produto->precovendaunitario;

        $prod_id = $produto->produto_id;

        if ($isAppNf) return $preco;

        $clienteProduto = $order->cliente->clienteProduto;

        $tipo = $order->pagamento->tipo;

        if ($tipo != "4") {
            $novoPreco = static::getPrecoEspecial($clienteProduto, $prod_id, $preco);

            if ($preco != $novoPreco)
                info("Cliente ID: {$order->cliente->id} Antigo preço: {$preco} Novo preço: {$novoPreco}" . PHP_EOL);

            return $novoPreco;
        }

        return static::getPrecoConvenio($produto, $order->cliente);
    }

    private static function getPrecoEspecial($clienteProduto, $prod_id, $preco)
    {
        $precoEspecial = $clienteProduto->first(function ($clipro) use ($prod_id) {
            return $clipro->produto_id == $prod_id;
        });

        if (is_null($precoEspecial)) return $preco;

        if ($precoEspecial->descontopara == 3) return $preco;

        if ($precoEspecial->tipo == 2) {
            return $preco - ($preco * $precoEspecial->desconto);
        }

        if ($precoEspecial->desconto == 0) {
            $preco = $precoEspecial->preco;
        } else {
            $preco = $preco - $precoEspecial->desconto;
        }

        return $preco;
    }

    private static function getPrecoConvenio($produto, $cliente)
    {
        $empresa = $cliente->convenioempresa;

        $comissaodestino = $empresa->clienteConvenio->comissaodestino;

        $comissao = $empresa->clienteConvenio->comissao;

        $preco = $produto->precovendaunitario;

        if ($comissaodestino != "1") return $preco;

        return $preco - ($preco * ($comissao / 100));
    }

    public static function trackPedido($apiorder_id)
    {
        return Pedido::from("pedidos as pe")
            ->join("setors se", "se.id", "pe.entregasetor_id")
            ->leftJoin("veiculos as vei", "vei.colaborador_id", "pe.colaborador_id")
            ->whereRaw("pe.apipedido_id is not null and pe.apipedido_id = $apiorder_id")
            ->selectRaw("pe.apipedido_id as cod_pedido_api, pe.colaborador_id as cod_colaborador, vei.placa, se.usarastreamento as monitorado_aplicativo")
            ->orderBy("pe.id", "DESC")
            ->first();
    }

    public static function getColaborador($order)
    {
        $colab = Setorcolaboradores::from("setorcolaboradores as seco")
            ->join("colaboradors cola", "cola.id", "seco.colaborador_id")
            ->whereSetorId($order->setor->id)
            ->select("cola.id")
            ->get()
            ->first();

        return is_null($colab) ? null : $colab->id;
    }

    public static function trackFromPlaca($placa)
    {
        $plaquinha = preg_replace('/[^A-Za-z0-9]+/', '', $placa);
        return \DB::connection("monitora")
            ->table("veiculos as vei")
            ->leftJoin("ultimaposicaos as pos", "pos.veiculo_id", "vei.id")
            ->where("placa", $placa)
            ->orWhere("placa", $plaquinha)
            ->selectRaw("vei.motorista, latitude, longitude, azimute")
            ->orderBy("datahora", "DESC")
            ->first();
    }

    public static function checkForPendency($api_id, $pedidosituacao_id)
    {
        return Cliente::from("pedidos pe")
            ->join("clientes cli", "cli.id", "pe.cliente_id")
            ->whereRaw("cli.api_id = $api_id and pe.pedidosituacao_id = $pedidosituacao_id")
            ->exists();
    }

    public static function orderTracking()
    {
        $selectRaw = "pe.id as cod_pedido_api, vei.id AS veiculo_id, pe.colaborador_id AS cod_colaborador, vei.placa, " .
            "hist.pedido_id AS cod_pedido, hist.pedidosituacao_id AS cod_pedido_status, hist.id AS codigo_pedidos_ativou_status, " .
            "(CASE WHEN sit.fechadocancelado = 1 THEN '1' ELSE '0' END) AS entrega_nao_realizada, " .
            "(CASE WHEN sit.entregapendente = 1 THEN '1' ELSE '0' END) AS pendente, " .
            "(CASE WHEN sit.entregapendente = 1 THEN '0' ELSE '1' END) AS notify, " .
            "(CASE WHEN sit.fechadoconcluido = 1 OR sit.entregafinalizada = 1 THEN '1' ELSE '0' END) AS entrega_realizada ";

        return Pedido::from("pedidos pe")
            ->join("pedidosituacaohistoricos hist", "hist.pedido_id", "pe.id")
            ->join("pedidosituacaos sit", "sit.id", "hist.pedidosituacao_id")
            ->leftJoin("veiculos vei", "vei.colaborador_id", "pe.colaborador_id")
            ->whereRaw("pe.apipedido_id IS NOT NULL AND pe.apipedido_id > 0 AND hist.enviadoapi = 0")
            ->selectRaw($selectRaw)
            ->orderBy("hist.datahora", "DESC")->get();
    }

    public static function updateClientAddress($client_id, $data)
    {
        $cliente = Cliente::whereApiId($client_id)->first();

        if (!$cliente)
            return null;

        try {
            $dataUp = [
                "endereco_app"  => Util::formatAddress($data),
                "latitude_app"  => $data->get("latitude"),
                "longitude_app" => $data->get("longitude")
            ];

            if ($data->get("setor_id"))
                $dataUp["setor_id"] = $data->get("setor_id");

            $cliente->update($dataUp);
        } catch (\Exception $e) {
            Util::notify("Falha nentativa de atualização de endereço padrao do aplicativo: " .
                "Cód. cliente do app: " . $client_id . " Erro: " . $e->getMessage());
            return null;
        }

        return $cliente;
    }

    public static function getOrderHistory($client_id, $produtos)
    {
        Carbon::setToStringFormat("Y-m-d");
        $now = Carbon::now()->endOfDay();
        $yearAgo = (clone $now)->subYear()->startOfDay();

        $joinFn = function ($join) {
            $join->on("status.id", "pe.pedidosituacao_id")
                ->whereRaw("(entregafinalizada = 1 OR entregacancelada = 1 " .
                    "fechadoconcluido = 1 OR fechadocancelado = 1)");
        };

        return Pedido::from("pedidos pe")
            ->join("pedidoitems i", "pe.id", "i.pedido_id")
            ->join("pedidosituacao status", $joinFn)
            ->where("pe.cliente_id", $client_id)
            ->whereIn("i.produto_id", $produtos)
            ->whereRaw("pe.datahora BETWEEN TO_DATE('$yearAgo', 'YYYY-MM-DD HH24:MI:SS') AND TO_DATE('$now', 'YYYY-MM-DD HH24:MI:SS')")
            ->selectRaw("pe.id AS pedido_id, i.precovendatotal, pe.valorvenda AS totalpedido, i.quantidade, pe.datahora " .
                "(CASE WHEN entregafinalizada = 1 OR fechadoconcluido = 1 THEN 1 ELSE 0 END) AS cancelado, " .
                "i.produto_id, i.precovendaunitario")
            ->orderBy("datahora", "DESC")
            ->get();
    }

    public static function findClientByAddress($address)
    {
        $address = (array) $address;
        $clientCon = new ClienteController();
        $client = $clientCon->verificaEndereco($address, null, true);

        return $client;
    }

    private static function deletePhonesAndNotify($phones, $client)
    {
        $first = $phones->first();
        foreach ($phones as $ph) {
            if ($ph->id == $first->id) continue;

            if ($ph->cliente_id == $client->id) continue;

            $msg = "Telefone: $ph->telefone do cliente_id $ph->cliente_id foi apagado por estar igual ao do novo usuario do aplicativo.";

            Util::notify($msg);

            Util::logDifferentFile($msg);

            $ph->delete();
        }
    }

    private static function deleteOtherApiId($cliente_id, $api_id)
    {
        $clientes = Cliente::where("api_id", $api_id)
            ->where("id", "<>", $cliente_id)
            ->get();

        if ($clientes->isEmpty()) return;

        $count = $clientes->count();
        $ids = [];
        foreach ($clientes as $cliente) {
            $cliente->update([
                "api_id"        => null,
                "endereco_app"  => null,
                "nome_app"      => null,
                "latitude_app"  => null,
                "longitude_app" => null
            ]);

            array_push($ids, $cliente->id);
        }

        $ids = implode(", ", $ids);

        Util::logDifferentFile("Foram enccontrado $count cliente (s), $ids, com o api_id $api_id, foram desvinculados" . PHP_EOL);
    }

    public static function getClientByCpf($cpf, $telefone)
    {
        return Cliente::from("clientes cli")
            ->join("clientetelefones tel", "tel.cliente_id", "cli.id")
            ->whereRaw("regexp_replace(cli.cpf, '[^0-9]', '') = '$cpf' AND regexp_replace(tel.telefone, '[^0-9]', '') = '$telefone'")
            // ->where(DB::raw("regexp_replace(cli.cpf, '[^0-9]', '')"), $cpf)
            // ->where(DB::raw("regexp_replace(tel.telefone, '[^0-9]', '')"), $telefone)
            ->select("cli.*")
            ->first();
    }

    /**
     * @param AppConfig $config
     * @param stdClass $address
     */
    public static function getCityId($config, $address)
    {
        $configCity = Cidade::find($config->cidade_id);
        $cidade = strtolower($address->cidade);

        if (strtolower($configCity->descricao) == $cidade) return $config->cidade_id;

        $addressCity = Cidade::whereRaw("lower(descricao) = '{$cidade}'")
            ->first();

        if (is_null($addressCity)) {
            Util::notify("Cidade do cliente: {$cidade} não encontrada na base, utilizando a cidade da distribuidora.");

            return $config->cidade_id;
        }

        return $addressCity->id;
    }

    /**
     * @param string $desc
     * @param int $cidade_id
     * @param AppConfig $config
     */
    private static function createStreet($desc, $city, $config)
    {
        $empresa = Empresa::find($config->empresa_id);

        $data = [
            "empresa_id"        => $empresa->id,
            "grupo_id"          => $empresa->grupo_id,
            "descricao"         => $desc,
            "cidade_id"         => $city,
            "nfecompl"          => "Rua",
            "importacaocep_id"  => -1,
            "ativo"             => true,
        ];

        $street = Rua::create($data);

        return $street;
    }

    /**
     * @param string $desc
     * @param int $cidade_id
     * @param AppConfig $config
     */
    private static function createDistrict($desc, $city, $config)
    {
        $data = [
            'descricao' => $desc,
            'grupo_id'  => $config->grupo_id,
            'cidade_id' => $city
        ];

        $district = Bairro::create($data);

        return $district;
    }
}
