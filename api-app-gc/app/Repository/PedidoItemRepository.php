<?php

/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 02/08/2018
 * Time: 17:19
 */

namespace App\Repository;

use App\PedidoItem;
use DB;

/**
 * @method static create(array $item)
 */
class PedidoItemRepository extends BaseRepository
{

    /**
     * PedidoItemRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(PedidoItem::class);
    }

    /**
     * @param $order_id
     * @return mixed
     */
    public static function getByOrder($order_id)
    {
        return DB::table("pedidoitens as i")
            ->join("produtos as p", function ($join) {
                $join->on("p.id", "i.produto_id")->where("p.ativo", true);
            })
            ->join("produtoimportacoes as imp", function ($join) {
                $join->on("imp.produto_id", "p.id")->where("imp.ativo", true);
            })
            ->selectRaw("i.quantidade, i.precovendaunitario, " .
                "i.precovendatotal, imp.erp_id as produto_id, codigogb")
            ->wherePedidoId($order_id)->get();
    }

    /**
     * @param $order_id
     * @return mixed
     */
    public static function getItems($order_id)
    {
        return DB::table("pedidoitens as i")
            ->join("produtos as p", function ($join) {
                $join->on("p.id", "i.produto_id")->where("p.ativo", true);
            })
            ->join("produtoimportacoes as imp", function ($join) {
                $join->on("imp.produto_id", "p.id")->where("imp.ativo", true);
            })
            ->selectRaw("p.id, p.descricao, p.thumbnail, i.quantidade as quantity, i.precovendaunitario as unitPrice, " .
                "i.precovendatotal as total, imp.ativo as available")
            ->wherePedidoId($order_id)->get();
    }

    /**
     * @param $order_id
     * @return mixed
     */
    public static function toLink($order_id)
    {
        return static::getByOrder($order_id);
    }
}
