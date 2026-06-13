<?php

namespace App\Http\Controllers;

use App\{
    Helpers\Util,
    Http\Requests\PedidoRequest,
    Http\Resources\ApiResources,
    Repository\PedidoAvaliacaoRepository as PedidoAvaliacao,
    Repository\ClienteRepository,
    Repository\PedidoItemRepository,
    Repository\PedidoRepository as Pedido,
    Repository\EnderecoRepository as Endereco,
    Repository\PedidoRepository,
    Repository\PedidoSituacaoRepository as Situacao,
    Repository\PedidoItemRepository as PedidoItem,
    Repository\PedidoSituacaoRepository,
    User,
    VehiclePosition
};
use App\Repository\CondPgtoImportacaoRepository;
use App\Repository\UserRepository;
use DB;
use Eloquent;
use Exception;
use App\Services\CarbonCustom as Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Input;
use stdClass;
use Storage;

class PedidoController extends Controller
{

    /**
     * @var bool
     */
    public $testing;

    public function __construct($ws = false)
    {
        $this->testing = (bool) env("TRACK_SIMULATOR", "0");
        parent::__construct($ws);
    }

    /**
     * @param PedidoRequest $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws Exception
     */
    public function store(PedidoRequest $request)
    {
        try {
            ini_set('default_socket_timeout', 30);
            DB::beginTransaction();

            $endereco = Endereco::findOrFail($request->get("endereco_id"), "Endereço");

            $data = $this->validateData($request, $endereco->uf);

            $sit = Situacao::wherePendente(true)->first();
            if (!$sit) {
                throw new Exception("Situação pendente do pedido não foi encontrada");
            }

            if (Pedido::wherePedidosituacaoId($sit->id)->whereClienteId($data["cliente_id"])->first() !== null) {
                throw new Exception("Já existe um pedido pendente", 999);
            }
            $data["pedidosituacao_id"] = $sit->id;
            $data["erp_id"] = 0;
            $user_id = Input::get("user_id", null);
            $revenda_id = Input::get("revenda_id", null);

            if (!$user_id && !$revenda_id) {
                $revenda = UserRepository::where("ativo", 1)->first();

                if (is_null($revenda))
                    throw new Exception("Erro ao criar seu pedido, por favor reinicie o aplicativo e tente novamente.", 250);

                $revenda_id = $revenda->id;
            }

            if ($this->checkIfClosed($revenda_id)) {
                throw new Exception("Desculpe, mas a revenda encontra-se fechada no momento.", 250);
                // return responseSuccess([], "Revenda Fechada");
            }

            $data["user_id"] = $revenda_id ? $revenda_id : $user_id;

            if ($request->get('codigo_cupom') != null && $request->get('codigo_cupom') != '') {
                $coupon = CouponsController::getCouponIfValid($request->get('codigo_cupom'), $data["cliente_id"]);

                if (is_string($coupon))
                    return responseReject($coupon, 422);

                $data['cupom_id'] = $coupon->id;
                $data['desconto_cupons'] = $request->get('desconto_cupons');
            } else {
                $data['coupon_id'] = null;
            }

            if ($data["gasdopovo"]) {
                $user = UserRepository::find($data["user_id"]);
                $data["valorfrete"] = $user->valorfretegp;

                if (!$user->gaspovoativado) {
                    throw new Exception("Programa Gás do Povo está indisponível no momento. Por favor, desative a opção no cadastro.", 250);
                }
            }

            $pedidoDb = Pedido::create($data);

            $pedido = clone $pedidoDb;

            $pedido->products = $this->createProducts($request->get("produtosJson"), $pedido);
            $requestToLink = $this->prepareRequestToLink($pedido, $request->get("pagamento"));

            try {
                $linkResponse = $this->linkTo($requestToLink->user, json_encode($requestToLink->request), "order");
                $isNok = isset($linkResponse->msg) && $linkResponse->status !== "OK";
                $type = "DEFAULT";

                if ($isNok && !str_contains($linkResponse->msg, "File")) $type = "REPORTABLE";

                if (isset($linkResponse->rejection) && $isNok) {
                    return responseReject($linkResponse->msg, $linkResponse->rejection, $type);
                } else if ($isNok) {
                    return responseReject($linkResponse->msg, $type);
                } else if ($linkResponse->status !== "OK") {
                    return responseReject("Erro desconhecido ao gerar pedido", $type);
                }

                $erpGenerated = $linkResponse->data;
            } catch (Exception $e) {
                // return response($e->getMessage(), 200, ["Content-Type" => "text/html"]);
                return responseError($e->getMessage());
            }

            unset($pedido->user);

            $response = json_decode($pedido->toJson());
            $response->items = $response->products;

            unset($response->products);

            if ($requestToLink->request->pedido->is_pix) {
                DB::commit();
                $response->pix = $erpGenerated;

                return responseSuccess($response, "Pedido registrado com sucesso!");
            }

            $pedidoDb->update([
                "erp_id"    => $erpGenerated->cod_pedido,
                "placa"     => $erpGenerated->placa,
            ]);

            if ($this->testing) {
                $this->addQueue($pedido, $erpGenerated->latLngSetor, $erpGenerated->setor_id);
            }

            $track = $this->track("");

            DB::commit();

            return responseSuccess($track, 'Pedido registrado com sucesso!');
        } catch (Exception $e) {
            DB::rollBack();
            if ($e->getCode() !== 999) {
                Util::notify("Impossível criar novo pedido: " . $e->getCode() . " " . $e->getMessage());
            }

            if ($e->getCode() === 250) {
                return responseReject($e->getMessage(), "REPORTABLE");
            }

            return responseError($e->getMessage());
        }
    }

    /**
     * @param $pedido
     * @param $setorInfo
     * @param $setor_id
     */
    private function addQueue($pedido, $setorInfo, $setor_id)
    {
        $numDeltas = 98;
        $currentPosition = (object) [
            "lat" => $setorInfo->lat,
            "lng" => $setorInfo->lng
        ];
        $deltaLat = ($pedido->endereco->latitude - $currentPosition->lat) / $numDeltas;
        $deltaLng = ($pedido->endereco->longitude - $currentPosition->lng) / $numDeltas;
        $path = [implode(",", (array) $currentPosition)];
        for ($i = 0; $i < $numDeltas; $i++) {
            $currentPosition->lat += $deltaLat;
            $currentPosition->lng += $deltaLng;
            array_push($path, implode(",", (array) $currentPosition));
        }
        array_push($path, implode(",", (array) $currentPosition));
        $urlBase = "https://roads.googleapis.com/v1/snapToRoads?interpolate=true&key=AIzaSyBlaYqOGBuXKdrRrB8KkyqbpvOG2AlRXxs&path=";
        $contents = json_decode(file_get_contents($urlBase . implode("|", $path)));
        $data = [];
        $index = 0;
        $allPoints = $contents->snappedPoints;
        foreach ($allPoints as $latLng) {
            array_push($data, [
                "latitude"      => $latLng->location->latitude,
                "longitude"     => $latLng->location->longitude,
                "index"         => $index++,
                "pedido_id"     => $pedido->id,
            ]);
        }
        array_push($data, [
            "latitude"      => $pedido->endereco->latitude,
            "longitude"     => $pedido->endereco->longitude,
            "index"         => $index + 1,
            "pedido_id"     => $pedido->id,
        ]);

        VehiclePosition::insert($data);

        DB::table("ordersqueue")->insert(["pedido_id" => $pedido->id, "setor_id" => $setor_id, "updated" => now("America/Sao_Paulo")]);
    }

    /**
     * @param $y1
     * @param $x1
     * @param $y2
     * @param $x2
     * @return float
     */
    private function degreeBearing($y1, $x1, $y2, $x2)
    {
        //rad2deg = ($lng2 - $lng1) * (pi() / 180)
        $dLon = rad2deg($x2 - $x1);
        $dPhi = log($this->tan($y2) / $this->tan($y1));
        if (abs($dLon) > pi()) {
            $dLon = $dLon > 0 ? - (2 * pi() - $dLon) : (2 * pi() + $dLon);
        }
        return $this->toBearing(atan2($dLon, $dPhi));
    }

    /**
     * @param $radians
     * @return int
     */
    private function toBearing($radians)
    {
        return (rad2deg($radians) + 360) % 360;
    }

    /**
     * @param $value
     * @return float
     */
    private function tan($value)
    {
        return tan(rad2deg($value) / 2 + pi() / 4);
    }

    /**
     * @param PedidoRepository|Collection|\App\Pedido $pedido
     * @return object
     * @throws Exception
     */
    private function prepareRequestToLink($pedido, $pagamento)
    {
        try {

            $loaded = clone $pedido;
            $loaded->load("condicaoPagamento.imported", "situacao.imported", "endereco", "user");

            $this->validateBeforeRequest($loaded);

            $request = new stdClass();

            $condicao = $loaded->condicaoPagamento->imported->first();
            $situacao = $loaded->situacao->imported->first();
            $endereco = (object) $loaded->endereco->only(
                "rua",
                "complemento",
                "numero",
                "longitude",
                "cliente",
                "latitude",
                "cep",
                "cidade",
                "uf",
                "bairro",
                "pontoreferencia"
            );

            $items = PedidoItem::toLink($pedido->id)->toArray();

            $this->throwIf(
                count($items) !== $pedido->items->count(),
                "não foi possível vincular os itens. [" . count($items) . ", {$pedido->items->count()}]"
            );
            $cliente = ClienteRepository::withPhone($pedido->cliente_id)->first();

            if (!$cliente || ($cliente && !$cliente->telefone)) {
                Storage::disk("public")->append(now()->toDateString() . "-log.txt", $cliente);
                throw new Exception(
                    "Não foi possível encontrar seu telefone, é possível que outra pessoa tenha efetuado o " .
                        "login de outro dispositivo com seu número. Desconecte-se do aplicativo e entre novamente com seu novo número de telefone"
                );
            }

            if ($loaded->condicaoPagamento->tipo == 4 && !$cliente->conveniado) {
                throw new Exception("Você precisa ser conveniado para utilizar essa opção de pagamento.", 250);
            }

            if ($loaded->condicaoPagamento->gasdopovo && !$cliente->gasdopovo) {
                throw new Exception("Você precisa estar no programa Gás do Povo para utilizar essa opção de pagamento.", 250);
            }

            $request->pedido = (object) [
                "pedido_id"             => $pedido->id,
                "datahoraprevisao"      => $pedido->datahoraprevisao,
                "observacoes"           => $pedido->observacoes,
                "condicaopagamento_id"  => $condicao->erp_id,
                "pagamento_online"      => $loaded->condicaoPagamento->tipo == 6,
                "is_pix"                => $loaded->condicaoPagamento->tipo == 7,
                "dados_pagamento"       => $pagamento,
                "pedidosituacao_id"     => $situacao->erp_id,
                "cliente"               => $cliente,
                "endereco"              => $endereco,
                "items"                 => $items,
                "valordesconto"         => $pedido->desconto_cupons,
                "gasdopovo"             => $pedido->gasdopovo
            ];

            $data = (object) [
                "request" => $request,
                "user"    => $pedido->user
            ];

            return $data;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $pedido
     * @throws Exception
     */
    private function validateBeforeRequest($pedido)
    {
        $this->throwIf(!$pedido->cliente, "Cliente inválido");
        $this->throwIf(!$pedido->endereco, "Endereço inválido");
        $this->throwIf(
            !$pedido->condicaoPagamento || ($pedido->condicaoPagamento && !$pedido->condicaoPagamento->imported->first()),
            "Condição de Pagamento inválida"
        );
        $this->throwIf(
            !$pedido->situacao || ($pedido->situacao && !$pedido->situacao->imported->first()),
            "Situação de Pedido inválida"
        );
    }

    protected function throwIf($condition, $message, $code = 0)
    {
        parent::throwIf($condition, "Impossível gerar/atualizar o pedido: " . $message, $code);
    }

    /**
     * @param PedidoRequest $request
     * @param $uf
     * @return array
     * @throws Exception
     */
    private function validateData(PedidoRequest $request, $uf)
    {
        $data = $request->only($this->getFieldsStoreUp());
        $data['datahoraprevisao'] = Carbon::now(getTimezone($uf))->format('Y-m-d H:i:s');

        $cliente = ClienteRepository::find($data["cliente_id"]);

        if ($cliente) {
            $data["gasdopovo"] = $cliente->gasdopovo;
        }

        return strNullToNullValue($data);
    }

    /**
     * @return array
     */
    private function getFieldsStoreUp()
    {
        return array_flatten(Pedido::getFillable());
    }

    /**
     * @param $json
     * @param PedidoRepository|Collection|Pedido $pedido
     * @return Collection
     * @throws Exception
     */
    private function createProducts($json, $pedido)
    {
        $msg = "Não foi possível gerar os produtos do pedido:";
        try {
            $products = json_decode($json);
            $this->throwIf(json_last_error() !== JSON_ERROR_NONE, "json informado é inválido");
        } catch (Exception $e) {
            throw new Exception($msg . " \"invalid json\"");
        }
        if (!count($products)) {
            throw new Exception($msg . " \"nenhum produto\"");
        }
        $needed = [
            'quantidade',
            'precovendaunitario',
            'precovendatotal',
            'produto_id'
        ];
        $items = collect([]);
        foreach ($products as $prod) {
            $item = validateParameters($prod, $needed);
            $item["pedido_id"] = $pedido->id;
            $item["codigogb"] = isset($prod->codigogb) && $prod->codigogb ? $prod->codigogb : " ";
            $item["precovendaunitario"] = moneyToDecimal($item["precovendaunitario"]);
            $item["precovendatotal"] = moneyToDecimal($item["precovendatotal"]);

            if ($pedido->gasdopovo && $item["quantidade"] > 1) {
                throw new Exception("Participantes do programa Gás do Povo não podem comprar mais de 1 produto no mesmo pedido.", 250);
            }

            $item = PedidoItem::create($item);
            $item->precovendaunitario = floatToMoney($item->precovendaunitario);
            $item->precovendatotal = floatToMoney($item->precovendatotal);
            $items->push($item);
        }

        return $items;
    }

    /**
     * @param bool $onOpen
     * @param null $cliente_id
     * @return PedidoRepository|Model|JsonResponse|null|object
     * @throws GuzzleException|Exception
     */
    public function track($onOpen = false, $cliente_id = null)
    {
        try {
            if (is_null($cliente_id)) {
                $cliente_id = getOrFail("cliente_id");
            }

            $pedido = Pedido::track($cliente_id);

            if (!$pedido) {
                return $onOpen ? null : responseSuccess(null);
            }
            $sit = Situacao::wherePendente(true)->first();

            // * Se for pix e não foi pago mandar requisição para ctrl web buscando dados do pix
            if ($pedido->tipo_pag == 7 && $pedido->pedidosituacao_id == $sit->id) {
                if ($pedido->pago == 0 && $pedido->expirado == 1) {
                    return $this->cancelOrder($pedido);
                } else {
                    return $this->getPixInfo($pedido, $onOpen);
                }
            }

            $user = $pedido->user;
            $positionReseller = collect(["latitude" => $user->latitude, "longitude" => $user->longitude]);
            $trackInfo = $this->getTrackInfo($pedido, $positionReseller, $user);

            if (is_string($trackInfo)) {
                if ($onOpen) {
                    throw new Exception($trackInfo);
                } else {
                    return responseReject($trackInfo);
                }
            }

            $pedido->products = PedidoItemRepository::getByOrder($pedido->id);

            unset($pedido->user);

            $total = $pedido->products->sum("precovendatotal");

            if ($pedido->gasdopovo) {
                $total += $pedido->valorfrete;
            }

            $pedido->track = $trackInfo;
            $pedido->reseller_position = $positionReseller;
            $pedido->reseller_name = $user->fantasia;
            $pedido->delivery_time = "Tempo de entrega é de " . $user->delivery_time_start . " a " . $user->delivery_time_end . " min.";
            $pedido->reseller_phone = $user->telefone;
            $pedido->whatsapp = env('REVENDA_WHATSAPP_NUMBER');
            $pedido->total_price = floatToMoney($total);

            $response = json_decode($pedido->toJson());
            $response->items = json_encode($response->products);
            unset($response->products);

            return $onOpen ? $response : responseSuccess($response);
        } catch (Exception $e) {
            Util::notify("Impossível monitorar o veículo: " . $e->getCode() . " " . $e->getMessage());
            if ($onOpen) {
                throw $e;
            }
            return responseError($e->getMessage());
        }
    }

    public function getLastestStatus()
    {
        try {
            $cliente_id = request()->get("cliente_id", null);

            if (is_null($cliente_id)) throw new Exception("Cliente não encontrado.");

            $pedido = Pedido::track($cliente_id);

            $user = $pedido->user;
            $positionReseller = collect(["latitude" => $user->latitude, "longitude" => $user->longitude]);
            $trackInfo = $this->getTrackInfo($pedido, $positionReseller, $user);

            if (is_string($trackInfo)) {
                return responseReject($trackInfo);
            }

            $response = new stdClass();
            $response->cancelado = $pedido->cancelado;
            $response->entregue = $pedido->entregue;
            $response->pendente = $pedido->pendente;
            $response->ementrega = $pedido->ementrega;
            $response->track = $trackInfo;

            return responseSuccess($response);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    public function getItems()
    {
        $order_id = request()->get("order_id", null);

        if (is_null($order_id)) throw new Exception("Pedido não encontrado.");

        $produtos = PedidoItemRepository::getItems($order_id);

        if ($produtos->isEmpty()) {
            return responseSuccess();
        }

        Util::convertImagesToBase64($produtos);

        return responseSuccess($produtos);
    }

    /**
     * @param PedidoRepository|Pedido|Eloquent $pedido
     * @param $positionReseller
     * @param $user
     * @return JsonResponse|Collection|mixed|null
     * @throws GuzzleException
     * @throws Exception
     */
    private function getTrackInfo($pedido, $positionReseller, $user)
    {
        if ($this->testing && $pedido->ementrega) {
            $trackInfo = $this->simulateTrack($pedido);
        } else {
            $trackInfo = $this->sgcTrack($user, $pedido, $positionReseller);

            if (is_string($trackInfo)) {
                return $trackInfo;
            }
        }

        if (!$trackInfo->get("placa")) {
            $trackInfo->put("placa", " ");
        }
        if (!$trackInfo->get("motorista")) {
            $trackInfo->put("motorista", "Sem informações sobre o entregador");
        }
        if (!$trackInfo->get("location")) {
            $trackInfo->put("location", $positionReseller);
        }

        return $trackInfo;
    }

    /**
     * @param PedidoRepository|Pedido|Eloquent $pedido
     * @return Collection
     * @throws Exception
     */
    private function simulateTrack($pedido)
    {
        $clientLocation = collect(["latitude" => $pedido->latitude, "longitude" => $pedido->longitude]);
        $trackInfoBase = collect([
            "motorista" => "Sem informações sobre o entregador",
            "placa"     => " ",
            "location"  => $clientLocation
        ]);

        $queued = DB::table("ordersqueue")->where("pedido_id", $pedido->id)->first();
        $lastIdx = VehiclePosition::where("pedido_id", $pedido->id)->max("index");
        $jump = (int) round((4 * $lastIdx) / 100);
        if (!$queued) {
            $trackInfo = $trackInfoBase;
        } else {
            $index = $queued->currentpositionindex <= $lastIdx ? $queued->currentpositionindex : $lastIdx;
            $now = now("America/Sao_Paulo");
            $diff = $now->diffInSeconds(Carbon::parse($queued->updated, "America/Sao_Paulo"));
            $times = ($diff > 0 ? $diff : 1) / 10;
            if ($times > 1) {
                $index = $index + ($jump * (int) $times);
                $index = $index <= $lastIdx ? $index : $lastIdx;
            }
            VehiclePosition::where("pedido_id", $pedido->id)->where("index", "<", $index - $jump)->delete();

            $position = VehiclePosition::where("pedido_id", $pedido->id)->where("index", $index)->first();
            $old = VehiclePosition::where("pedido_id", $pedido->id)->where("index", $index - $jump)->first();
            if ($old && $position) {
                $bearing = $this->degreeBearing($position->latitude, $position->longitude, $old->latitude, $old->longitude);
            } else {
                $bearing = 0;
            }
            if ($position) {
                $trackInfo = collect([
                    "location" => [
                        "latitude"          => $position->latitude,
                        "longitude"         => $position->longitude,
                        "azimuth"           => $bearing,
                    ],
                    "motorista"         => "Jeferson Almeida",
                    "placa"             => "ABC-1234",
                    "cod_pedido_api"    => $pedido->id,
                    "index"             => $index
                ]);
                $next = $index + $jump;
                DB::table("ordersqueue")
                    ->where("pedido_id", $pedido->id)
                    ->update([
                        "currentpositionindex" => $next <= $lastIdx ? $next : $lastIdx,
                        "updated"              => $now
                    ]);
            } else {
                $trackInfo = $clientLocation;
            }
            if ($index === $lastIdx) {
                $pedido->update(["pedidosituacao_id" => 4]);
                $trackInfo = $trackInfoBase;
            }
        }

        if (is_null($trackInfo)) {
            return $trackInfoBase;
        } else {
            return $trackInfo;
        }
    }

    /**
     * @param $user
     * @param $pedido
     * @param $positionReseller
     * @return JsonResponse|Collection|mixed|null
     * @throws GuzzleException
     */
    private function sgcTrack($user, $pedido, $positionReseller)
    {
        $url = substr($user->erpurl, -1) !== '/' ? $user->erpurl . '/' : $user->erpurl;
        $api = new ApiResources($url . "api/", $user);

        $trackInfo = $api->getData([
            "pedido_id" => $pedido->id
        ], "vehicle/lastPosition", "Veículos");

        if (is_string($trackInfo)) {
            return $trackInfo;
        } elseif (!is_null($trackInfo)) {
            $trackInfo = $trackInfo->has("cod_pedido_api") ? $trackInfo : null;
        }

        if (is_null($trackInfo) || !$pedido->ementrega) {
            $trackInfo = collect([
                "motorista" => "Sem informações sobre o entregador",
                "placa"     => " ",
                "location"  => $positionReseller
            ]);
        }
        return $trackInfo;
    }

    /**
     * @return JsonResponse
     * @throws Exception|GuzzleException
     */
    public function tracking()
    {
        try {
            $orders = json_decode(utf8Format(Input::get("orders", "[]")));
            $this->throwIf(JSON_ERROR_NONE !== json_last_error(), json_last_error_msg());

            //            $pendentes = json_decode(utf8Format(Input::get("pendentes", "[]")));
            //            $this->throwIf(JSON_ERROR_NONE !== json_last_error(), json_last_error_msg());

            //            if (! count($orders) && ! count($pendentes)) {
            //                return responseSuccess([]);
            //            }

            Log::info("Orders: " . strval(empty($orders)) . " " . Input::get("orders", "[]"));

            if (empty($orders)) {
                return responseSuccess([]);
            }

            DB::beginTransaction();
            $orders = collect($orders);
            if ($orders->isNotEmpty()) {
                $orders = Pedido::updateTrack($orders);
                $this->sendNotifications($orders);
            }
            //            $pendentes = collect($pendentes);
            //            if ($pendentes->isNotEmpty()) {
            //                Pedido::setVehicleIds($pendentes);
            //            }
            DB::commit();
            return responseSuccess([]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::info("Macacos: " . $e->getMessage() . " " . $e->getLine() . " " . $e->getFile());
            Util::notify("Impossível atualizar a localização e/ou status do pedido: " . $e->getCode() . " " . $e->getMessage());
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     * @throws Exception|GuzzleException
     */
    public function evaluate()
    {
        try {

            DB::beginTransaction();
            $order = Pedido::findOrFail(getOrFail("pedido_id", 0, "Código do pedido inválido"), "Pedido");

            $data = Input::only(["pedido_id", "mensagem", "rating", "ignored"]);

            if (PedidoAvaliacao::hasWithOrder($data["pedido_id"])) {
                return responseReject("Pedido já foi avaliado");
            }

            if (!isset($data["mensagem"]) || !$data["mensagem"] || $data["mensagem"] == "null") {
                $data["mensagem"] = " ";
            }

            if (strlen($data["mensagem"]) > 140) {
                return responseReject("Por favor, informe uma mensagem menor que 140 caracteres " . "(" . strlen($data["mensagem"]) . " informados)");
            }

            $ignored = isset($data["ignored"]) && ($data["ignored"] || $data["ignored"] === "true");

            if (
                !$ignored && (!isset($data["rating"])
                    || !$data["rating"]
                    || (float) $data["rating"] > 5
                    || (float) $data["rating"] < 1)
            ) {
                return responseReject("Por favor, avalie o pedido de um a cinco");
            }

            if ($ignored) {
                $order->update(["nao_avaliado" => true]);
            } else {
                $order->update(["nao_avaliado" => false]);

                if ($data["rating"] <= 3) {
                    $messageEvaluate = "Pedido [" . $order->erp_id . "] avaliado '" . $data["mensagem"] . "'" . " nota " . $data["rating"] . "/5 ";

                    try {
                        Util::notify($messageEvaluate, "avaliacao", $order->user);
                    } catch (Exception $e) {
                        Util::log("erro ao enviar avaliaçãao ao ctrl+: " . $e->getMessage());
                        Util::notify("erro ao enviar avaliaçãao ao ctrl+: " . $e->getMessage(), $order->user);
                    }
                }

                PedidoAvaliacao::create($data);
            }

            DB::commit();
            return responseSuccess([]);
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @param Collection $orders
     */
    private function sendNotifications($orders)
    {
        $anothers = $orders->filter(function ($order) {
            return !$order->entregue && !$order->cancelado;
        })->pluck("cliente_id");

        $clients = ClienteRepository::toNofify($anothers);
        $this->sendNotificationRequest("Pedido Atualizado", "Seu pedido foi atualizado pela revenda", $clients, [
            "action" => "orderUpdated"
        ]);

        $cancelados = $orders->filter(function ($order) {
            return $order->cancelado;
        })->pluck("cliente_id");
        $clients = ClienteRepository::toNofify($cancelados);
        $this->sendNotificationRequest("Pedido cancelado", "Seu pedido foi cancelado pela revenda", $clients, [
            "action" => "orderCanceled"
        ]);

        $entregues = $orders->filter(function ($order) {
            return $order->entregue;
        })->pluck("cliente_id");
        $clients = ClienteRepository::toNofify($entregues);
        $this->sendNotificationRequest("Pedido entregue", "Seu pedido foi entregue, conte-nos como foi seu atendimento", $clients, [
            "action" => "orderFinished"
        ]);
    }

    private function sendNotificationRequest($title, $body, $ids, $data)
    {
        foreach ($ids as $id) {
            ApiResources::notifyDevices($title, $body, $id, $data);
        }
    }

    /**
     * @return JsonResponse
     */
    public function history()
    {
        try {
            $cliente_id = getOrFail("cliente_id");

            $orders = Pedido::historyOf($cliente_id);
            $ordersFiltered = $orders->unique("id");
            $responseRequest = collect([]);

            foreach ($ordersFiltered as &$order) {
                $products = collect([]);
                $orders->filter(function (&$orderA) use ($order, &$products) {
                    if ($orderA->id === $order->id) {
                        $newObj = (object) [
                            "codigo_pedido"      => $orderA->id,
                            "precovendatotal"    => floatToMoney($orderA->precovendatotal),
                            "precovendaunitario" => floatToMoney($orderA->precovendaunitario),
                            "quantidade"         => (int) $orderA->quantidade,
                            "produto_id"         => $orderA->produto_id,
                            "descricao"          => $orderA->descricao,
                        ];
                        $products->push($newObj);
                    }
                });

                $total = $order->precovendatotal;

                if ($order->gasdopovo) {
                    $total += $order->valorfrete;
                }

                $responseRequest->push((object) [
                    "data"                  => Carbon::parse($order->data)->format("d/m/y H:i"),
                    "cancelado"             => $order->cancelado,
                    "entregue"              => $order->entregue,
                    "reseller"              => $order->reseller,
                    "avaliado"              => $order->avaliado,
                    "ignorado"              => $order->nao_avaliado,
                    "rating"                => $order->rating,
                    "mensagem_avaliacao"    => $order->mensagem_avaliacao,
                    "codigo_pedido"         => $order->id,
                    "id"                    => $order->id,
                    "erp_id"                => $order->erp_id,
                    "status"                => $order->status,
                    "gasdopovo"             => $order->gasdopovo,
                    "valorfrete"            => $order->valorfrete,
                    "total"                 => floatToMoney($total),
                    "produtos"              => $products,
                    "cupom"                 => $order->cupom
                ]);
            }

            return responseSuccess($responseRequest);
        } catch (Exception $e) {
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     * @throws GuzzleException|Exception
     */
    public function cancel()
    {
        try {
            DB::beginTransaction();
            $order = Pedido::findOrFail(getOrFail("id"));

            $this->cancelOrder($order);

            DB::commit();
            return responseSuccess([]);
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    /**
     * @return JsonResponse
     */
    public function root()
    {
        DB::beginTransaction();
        try {
            $data = new stdClass();
            // $ip = $request->ip();

            // if ($ip == "::1") $ip = "127.0.0.1";

            // $clienteC = new ClienteController();
            // $isLatestBuild = $clienteC->isLatestBuild($ip);

            $withOnline = Input::get("online_payment", false) == "true";
            $withPix = Input::get("with_pix", false) == "true";

            $product = new ProdutoController();
            $data->product = $product->getToOrder(true);

            $payment = new CondicaoPagamentoController();
            $data->payment = $payment->getToOrder(true, $withOnline, $withPix);
            $data->price = $payment->getPrices(true);

            DB::commit();
            return responseSuccess($data);
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    public function newRoot()
    {
        $cliente_id = request()->get("cliente_id", null);
        DB::beginTransaction();
        try {
            $cliente = null;

            if (!is_null($cliente_id)) {
                $cliente = ClienteRepository::find($cliente_id);
            }

            $onlyGasPovo = is_null($cliente) ? false : !!$cliente->gasdopovo;

            $data = new stdClass();

            $product = new ProdutoController();
            $data->product = $product->getToOrder(true, $onlyGasPovo);

            $payment = new CondicaoPagamentoController();
            $dtPayment = $payment->getToOrder(true, true, true, $onlyGasPovo);
            $price = $payment->getPrices(true, $onlyGasPovo);

            foreach ($dtPayment as $pay) {
                $prodPrice = [];

                foreach ($price as $prod) {
                    if ($prod->condicaopagamento_id != $pay->id) continue;

                    $prodPrice[$prod->produto_id] = $prod->valor;
                }

                $pay->productPrices = $prodPrice;
            }

            $data->payment = $dtPayment;

            DB::commit();
            return responseSuccess($data);
        } catch (Exception $e) {
            DB::rollBack();
            return responseError($e->getMessage());
        }
    }

    private function checkIfClosed($revenda_id)
    {
        $revenda = User::find($revenda_id);
        $now = Carbon::now();

        $horario = $revenda->semanahorafechamento;

        if ($now->isSaturday()) $horario = $revenda->sabadohorafechamento;
        else if ($now->isSunday()) $horario = $revenda->domingohorafechamento;

        $formatedNow = Carbon::now()->format("Y-m-d");

        $hora = Carbon::createFromFormat("Y-m-d H:i:s", $formatedNow . " " . $horario);

        return $now->greaterThan($hora);
    }

    private function getPixInfo($order, $onOpen)
    {
        try {
            $user = $order->user;

            $baseUri = str_finish($user->erpurl, '/') . "api/";

            $formParams = [
                "pedido_id" => $order->id,
            ];

            $api = new ApiResources($baseUri, $user, "GET");

            $response = $api->getData($formParams, "order/getPix", "PIX");

            unset($order->user);

            $order->pix = $response;

            return $onOpen ? $order : responseSuccess($order);
        } catch (Exception $ex) {
            $msg = $ex->getMessage();

            if (str_contains(strtolower($msg), "expirado")) {
                return $this->cancelOrder($order);
            }

            if ($ex->getCode() != 422) {
                Util::log($ex, "warn");
                return responseError("Erro a obter seu pedido, por favor comunique a revenda!");
            }

            return $this->cancelOrder($order);
        }
    }

    public function setPaidOrder()
    {
        $data = request()->all();

        DB::beginTransaction();
        try {
            $pedido = Pedido::find($data["pedidoapi_id"]);

            $pedido->update([
                "erp_id"    => $data["cod_pedido"],
                "placa"     => $data["placa"],
                "pago"      => 1
            ]);

            DB::commit();
            return responseSuccess();
        } catch (Exception $ex) {
            DB::rollBack();
            Util::log($ex->getMessage());
            return responseError($ex->getMessage());
        }
    }

    public function isPixPaid($pedido_id)
    {
        try {
            $pedido = Pedido::findOrFail($pedido_id);

            if ($pedido->expirado) {
                $this->cancelOrder($pedido);
            }

            // ! Considerando o PIX pago mesmo quando expirado para versão antiga do aplicativo fazer um reload
            return responseSuccess(["pago"  => $pedido->pago == 1 || $pedido->expirado == 1]);
        } catch (Exception $ex) {
            return responseError("Pedido não encontrado");
        }
    }

    public function setExpiredOrders()
    {
        $pedidos_id = request()->get("pedidos_id");

        DB::beginTransaction();
        try {
            info("expired orders: " . json_encode($pedidos_id));
            Pedido::whereIn("id", $pedidos_id)->update(["expirado" => 1]);

            DB::commit();
            return responseSuccess();
        } catch (Exception $ex) {
            DB::rollBack();
            return responseError($ex->getMessage());
        }
    }

    public function cancelOrder($order)
    {
        $status = PedidoSituacaoRepository::with("imported")->whereAtivo(true)->whereCancelado(true)->first();
        $this->throwIf(!$status, "Desculpe, mas não conseguimos atualizar o status do seu pedido pois nenhum status foi encontrado.");
        $user = $order->user;

        $oldStatus = PedidoSituacaoRepository::whereId($order->pedidosituacao_id)->first();
        $this->throwIf($oldStatus && $oldStatus->entregue, "Pedido já encerrado!");

        $order->update([
            "pedidosituacao_id"     => $status->id,
            "datahoracancelamento"  => Carbon::now(getTimezone($user->uf)),
        ]);

        $baseUri = $user->erpurl . "api/";
        $api = new ApiResources($baseUri, $user);

        $api->post([
            "id"        => $order->id,
            "situacao"  => $status->imported->first()->erp_id
        ], "order/cancel");

        return null;
    }
}
