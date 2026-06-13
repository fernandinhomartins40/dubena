<?php

/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 25/07/2018
 * Time: 14:32
 */

namespace App\Repository;

use Ratchet\Client;
use App\Helpers\Util;
use App\Pedido;
use App\Repository\PedidoSituacaoImportacaoRepository as Situacao;
use App\Repository\PedidoSituacaoRepository as SituacaoLocal;
use Auth;
use Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * @method items()
 * @method static $this|Eloquent|Collection whereAtivo(bool $true)
 * @method static $this|Eloquent|Collection whereIn(string $string, $pluck)
 * @method get()
 * @mixin Pedido
 * @mixin Eloquent
 */
class PedidoRepository extends BaseRepository
{

    /**
     * GeneralConfigRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(Pedido::class);
    }

    /**
     * @param $cliente_id
     * @return Pedido|\Illuminate\Database\Eloquent\Builder|Model|Builder|object|null
     */
    public static function track($cliente_id)
    {
        return Pedido::from("pedidos as ped")
            ->with("user")
            ->join("clienteenderecos as end", "end.id", "ped.endereco_id")
            ->join("clienteimportacoes as cli", "cli.id", "ped.cliente_id")
            ->join("pedidosituacoes as status", "status.id", "ped.pedidosituacao_id")
            ->join("condicaopagamentos as pgto", "pgto.id", "ped.condicaopagamento_id")
            ->leftJoin("pedidoavaliacoes as av", "av.pedido_id", "ped.id")
            ->whereRaw("ped.cliente_id = " . $cliente_id)
            ->selectRaw(
                "ped.*, ped.nao_avaliado as ignorado, end.latitude, end.longitude, status.info as status, " .
                    "pgto.tipo as tipo_pag, " .
                    "status.cancelado, status.entregue, status.pendente, status.ementrega, IF(av.id IS NULL, 0, 1) as avaliado"
            )->orderBy("datahoraprevisao", "DESC")
            ->first();
    }

    /**
     * @param Collection $orders
     * @return Collection
     */
    public static function updateTrack(Collection $orders)
    {
        //        $address = env('WEBSOCKET_ADDRESS', null);
        //
        //        if (is_null($address)) {
        //            Util::notify("URL do Websocket não encontrada!");
        //        }

        $user_id = Auth::user()->id;
        // $user_id = 2;
        $allStatus = Situacao::from("pedidosituacaoimportacoes as sit")->whereIn("erp_id", $orders->pluck("cod_pedido_status"))
            ->join("pedidosituacoes as status", "status.id", "pedidosituacao_id")
            ->where("sit.ativo", 1)->where("status.ativo", 1)->where("sit.user_id", $user_id)
            ->selectRaw("status.cancelado, status.entregue, sit.*")->get();
        $statusCanceled = SituacaoLocal::whereAtivo(1)->whereCancelado(1)
            ->selectRaw("*, id as pedidosituacao_id")->first();
        $statusFinished = SituacaoLocal::whereAtivo(1)->whereEntregue(1)
            ->selectRaw("*, id as pedidosituacao_id")->first();
        $ordersDb = static::whereRaw("pedidos.erp_id in (" . $orders->implode("cod_pedido", ", ") . ")")
            ->leftJoin("pedidosituacoes as status", "status.id", "pedidos.pedidosituacao_id")
            ->selectRaw("pedidos.*, status.cancelado, status.entregue")->get();

        $errors = "";
        $updated = "";
        $results = collect([]);
        foreach ($orders as $order) {
            $isFinishedOrCanceledSgc = false;
            $orderDb = $ordersDb->firstWhere("erp_id", "=", $order->cod_pedido);

            if (! $orderDb) {
                $errors .= "Pedido " . $order->cod_pedido . " não foi encontrado no banco de dados da api " . PHP_EOL;
            }

            $orderIsNotFinishedOrCanceled = $orderDb && ! $orderDb->cancelado && ! $orderDb->entregue;

            $status = $allStatus->where("erp_id", $order->cod_pedido_status)->first();
            if (! $status) {
                if ($order->entrega_nao_realizada && $statusCanceled && $orderIsNotFinishedOrCanceled) {
                    $isFinishedOrCanceledSgc = true;
                    $status = $statusCanceled;
                    $errors .= "Situação de pedido " . $order->cod_pedido_status .
                        " não foi encontrado no banco de dados da api mas é Entrega não Realizada. Pedido finalizado para o usuário." . PHP_EOL;
                } elseif ($order->entrega_realizada && $statusFinished && $orderIsNotFinishedOrCanceled) {
                    $isFinishedOrCanceledSgc = true;
                    $status = $statusFinished;
                    $errors .= "Situação de pedido " . $order->cod_pedido_status .
                        " não foi encontrado no banco de dados da api mas é Entrega Realizada. Pedido finalizado para o usuário." . PHP_EOL;
                }
                if (! $isFinishedOrCanceledSgc) {
                    $errors .= "Situação de pedido " . $order->cod_pedido_status . " não foi encontrado no banco de dados da api " . PHP_EOL;
                }
            }

            if (! $errors || ($errors && $isFinishedOrCanceledSgc)) {
                if ($status && $status->pedidosituacao_id) {
                    $orderDb->update(["pedidosituacao_id" => $status->pedidosituacao_id]);
                    $orderDb->cancelado = $status->cancelado;
                    $orderDb->entregue = $status->entregue;
                    //                    if (! is_null($address)) {
                    //                        static::wsNotify($address, [
                    //                            "data"          => (object) [
                    //                                "pedido_id"     => $orderDb->id
                    //                            ],
                    //                            "event"          => "ORDER_UPDATED",
                    //                            "data_format"    => "json"
                    //                        ]);
                    //                    }
                } else {
                    $cod_pedido = $orderDb ? $orderDb->erp_id : " Pedido não encontrado ";
                    $updated .= "Pedido. ["  . $cod_pedido . "] não atualizado na API. " .
                        "Possívelmente já foi finalizado ou cancelado anteriormente. " . PHP_EOL;
                }
            }
            if ($orderDb && $status && ! $status->pendente) {
                if ($orderIsNotFinishedOrCanceled) {
                    $results->push($orderDb);
                }
            } elseif ($orderDb) {
            } else {
                $cod_pedido = $orderDb ? $orderDb->erp_id : " Pedido não encontrado ";
                $updated .= "Ped. ["  . $cod_pedido . "] status [" . $order->cod_pedido_status . "] não vinculado. Usuário não receberá notificação. " . PHP_EOL;
            }
        }
        if ($errors) {
            Util::notify($errors);
            Util::log($errors);
        }
        if ($updated) {
            Util::notify($updated, "alert");
            Util::log($updated);
        }

        return $results;
    }

    private static function wsNotify($address, $message)
    {
        try {
            Client\connect($address . "?client=api&app_key=" . sha1(env("APP_KEY")))->then(
                function (Client\WebSocket $conn) use ($message) {
                    $conn->send(json_encode((object) $message));
                    $conn->close();
                },
                function (Exception $e) {
                    Util::notify("Não foi possível realizar a comunicação com o Websocket: {$e->getMessage()}");
                }
            );
        } catch (Exception $e) {
            Util::notify($e->getMessage());
        }
    }

    /**
     * @param Collection $orders
     */
    public static function setVehicleIds(Collection $orders)
    {
        try {
            /**@var PedidoRepository $ordersDb*/
            $ordersDb = static::whereRaw("pedidos.erp_id in (" . $orders->implode("cod_pedido", ", ") . ")")->get();

            $errors = "";

            //            $address = env('WEBSOCKET_ADDRESS', null);
            //
            //            if (is_null($address)) {
            //                Util::notify("URL do Websocket não encontrada!");
            //            }

            foreach ($orders as $order) {
                /**@var PedidoRepository $orderDb*/
                $orderDb = $ordersDb->where("erp_id", $order->cod_pedido)->first();
                if (! $orderDb) {
                    $errors .= "Pedido [" . $order->cod_pedido . "] não foi encontrado no banco de dados da api " . PHP_EOL;
                }

                if ($order->placa != null && $order->placa != $orderDb->placa) {
                    $orderDb->update(["placa" => $order->placa]);

                    //                    if (is_null($address)) {
                    //                        continue;
                    //                    }
                    //                    static::wsNotify($address, [
                    //                        "data"      => (object) [
                    //                            "placa"         => $order->placa,
                    //                            "pedido_id"     => $orderDb->id
                    //                        ],
                    //                        "event"          => "VEHICLE_UPDATED",
                    //                        "data_format"    => "json"
                    //                    ]);
                }
            }
            if ($errors) {
                Util::notify($errors);
            }
        } catch (Exception $e) {
            Util::notify($e->getMessage());
        }
    }

    public static function historyOf($cliente_id)
    {
        return static::from("pedidos as p")
            ->join("pedidosituacoes as status", "status.id", "p.pedidosituacao_id")
            ->join("pedidoitens as it", "it.pedido_id", "p.id")
            ->join("produtos as prod", "prod.id", "it.produto_id")
            ->join("users as user", "user.id", "p.user_id")
            ->leftJoin("pedidoavaliacoes as av", "av.pedido_id", "p.id")
            ->leftJoin("cupons as cupom", "cupom.id", "p.cupom_id")
            ->selectRaw("datahoraprevisao as data, status.cancelado, status.pendente, p.erp_id, " .
                "status.cancelado, status.entregue, p.gasdopovo, p.valorfrete, " .
                "IF(av.id IS NULL, 0, 1) as avaliado, nao_avaliado as ignorado, av.rating, av.mensagem as mensagem_avaliacao, " .
                "p.id, it.pedido_id, it.precovendatotal, it.quantidade, it.produto_id, it.precovendaunitario, " .
                "prod.descricao, status.descricao as status, user.fantasia as reseller, cupom.codigo as cupom, prod.thumbnail")
            ->whereClienteId($cliente_id)
            ->orderBy("datahoraprevisao", "desc")
            ->limit(25)
            ->get();
    }

    /**
     * @param $code
     * @param $user_id
     * @return mixed
     */
    public static function hasOrderGB($code, $user_id)
    {
        $subSel =
            /** @lang text */
            "SELECT id FROM pedidosituacoes WHERE cancelado = 1 AND id IN "
            . "(SELECT pedidosituacao_id FROM pedidosituacaoimportacoes "
            . "WHERE user_id = " . $user_id . ")";

        $orders = static::staticInstance()->from("pedidos as p")
            ->join("pedidoitens as i", "i.pedido_id", "p.id")
            ->whereRaw("i.codigogb = " . $code .
                " AND p.user_id = " . $user_id .
                " AND pedidosituacao_id NOT IN ( " . $subSel . " )")->first();

        return ! is_null($orders);
    }

    public static function hasValidOrdersForClientWithCoupon($coupon_id, $client_id): bool
    {
        return static::join('pedidosituacoes as s', 'pedidosituacao_id', 's.id')
            ->where('s.cancelado', '0')
            ->where('pedidos.cupom_id', $coupon_id)
            ->where('pedidos.cliente_id', $client_id)
            ->count() != 0;
    }

    public static function validOrdersWithCouponCount($coupon_id)
    {
        return static::join('pedidosituacoes as s', 'pedidosituacao_id', 's.id')
            ->where('s.cancelado', '0')
            ->where('pedidos.cupom_id', $coupon_id)
            ->count();
    }
}
