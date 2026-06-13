<?php

/** @noinspection PhpDynamicAsStaticMethodCallInspection */

/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 25/07/2018
 * Time: 14:32
 */

namespace App\Api\Repository;


use App\Api\Models\ProdutoImportacao;
use Illuminate\Support\Collection;

/**
 * Class ProdutoImportacaoRepository
 * @package App\Repository
 * @mixin ProdutoImportacao
 */
class ProdutoImportacaoRepository extends BaseRepository
{

    /**
     * GeneralConfigRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(ProdutoImportacao::class);
    }

    /**
     * @param $user_id
     * @return ProdutoImportacaoRepository[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function getToOrder($user_id, $produtogp_id = null)
    {
        $produtos = static::from("produtoimportacoes as imp")
            ->join('produtos as prod', 'imp.produto_id', 'prod.id')
            ->select("prod.descricao", "prod.id", "prod.thumbnail", "imp.avaliable")
            ->where("imp.ativo", true)
            ->where("prod.ativo", true)
            ->where("imp.user_id", $user_id)
            ->orderBy("prod.ordem");

        if (!is_null($produtogp_id)) {
            $produtos = $produtos->where("prod.id", $produtogp_id);
        }

        return $produtos->get();
    }

    /**
     * @param $user_id
     * @return ProdutoImportacao[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function byUser($user_id)
    {
        return static::from("produtoimportacoes as imp")
            ->join("produtos as prod", "prod.id", "imp.produto_id")
            ->whereUserId($user_id)->select("descricao", "erp_id")->get();
    }

    /**
     * @param $user_id
     * @param bool $fromPrices
     * @return mixed
     */
    public static function getLinked($user_id, $fromPrices = false)
    {
        $instance = (new static)::from('produtoimportacoes as imp')
            ->join("produtos as p", "p.id", "imp.produto_id")
            ->whereUserId($user_id)
            ->whereRaw("imp.ativo = 1 AND p.ativo = 1");

        if ($fromPrices) {
            $instance->selectRaw("imp.id, p.descricao, imp.avaliable");
        } else {
            $instance->selectRaw("imp.produto_id, imp.erp_id, p.descricao as apiDescricao, imp.avaliable");
        }

        return $instance->orderBy("p.descricao")->get();
    }

    /**
     * @param $user_id
     * @return mixed
     * @throws \Exception
     */
    public static function getLinkedPrices($user_id)
    {
        return static::getLinked($user_id, true);
    }

    /**
     * @param $product_id
     * @param $user_id
     * @return bool
     */
    public static function hasWithErp($product_id, $user_id)
    {
        return ! (static::getByErpId($product_id, $user_id));
    }

    /**
     * @param $product_id
     * @param $user_id
     * @return Collection|ProdutoImportacao
     */
    public static function getByErpId($product_id, $user_id)
    {
        return static::whereRaw("erp_id = " . $product_id . " AND user_id = " . $user_id)->first();
    }

    /**
     * @param $data
     * @param $user_id
     */
    public static function link($data, $user_id)
    {
        $actives = collect([]);
        foreach ($data as $d) {
            $d["user_id"] = $user_id;
            $d["ativo"] = true;
            $d["avaliable"] = !! $d["avaliable"];
            $exist = static::whereErpId($d["erp_id"])
                ->whereProdutoId($d["produto_id"])
                ->whereUserId($user_id)
                ->first();
            if ($exist) {
                $exist->update($d);
            } else {
                $exist = static::create($d);
            }
            $actives->push($exist);
        }
        static::inativeNotUsed($actives->pluck("id"), $user_id);
    }
}

