<?php

/** @noinspection PhpDynamicAsStaticMethodCallInspection */

/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 25/07/2018
 * Time: 14:32
 */

namespace App\Api\Repository;

use App\Api\Models\CondicaoPagamentoImportacao;

class CondPgtoImportacaoRepository extends BaseRepository
{

    /**
     * GeneralConfigRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(CondicaoPagamentoImportacao::class);
    }

    /**
     * @param $user_id
     * @return mixed
     * @throws \Exception
     */
    public static function getToOrder($user_id, $withOnline, $withPix, $onlyGasPovo)
    {
        $payments = static::from('condicaopagamentoimportacoes as imp')
            ->join('condicaopagamentos as cond', 'imp.condicaopagamento_id', 'cond.id')
            ->select("cond.id", "cond.descricao", "cond.tipo")
            ->where("imp.ativo", true)
            ->where("cond.ativo", true)
            ->orderBy("cond.ordem")
            ->orderBy("cond.descricao")
            ->where("imp.user_id", $user_id);

        if (!$withOnline) {
            $payments = $payments->where("cond.tipo", "<", "6");
        } else if (!$withPix) {
            $payments = $payments->where("cond.tipo", "<", "7");
        }

        if ($onlyGasPovo) {
            $payments = $payments->where("cond.gasdopovo", true);
        } else {
            $payments = $payments->where("cond.gasdopovo", false);
        }

        return $payments->get();
    }

    /**
     * @param $user_id
     * @return mixed
     * @throws \Exception
     */
    public static function getPrices($user_id, $onlyGasPovo)
    {
        $payments = static::from('condicaopagamentoimportacoes as cond')
            ->join('produtocondicaopagamentos as pc', 'cond.id', 'pc.condicaopagamentoimportacao_id')
            ->join('produtoimportacoes as prod', 'prod.id', 'pc.produtoimportacao_id')
            ->join('produtos as produtos', 'produtos.id', 'prod.produto_id')
            ->join('condicaopagamentos as cp', 'cond.condicaopagamento_id', 'cp.id')
            ->selectRaw("cond.condicaopagamento_id, prod.produto_id, valor")
            ->where("cond.ativo", true)
            ->where("prod.ativo", true)
            ->where("cond.user_id", $user_id)
            ->where("prod.user_id", $user_id)
            ->orderBy("produtos.ordem");

        if ($onlyGasPovo) {
            $payments = $payments->where("cp.gasdopovo", true);
        } else {
            $payments = $payments->where("cp.gasdopovo", false);
        }

        return $payments->get();
    }

    /**
     * @param $user_id
     * @return mixed
     * @throws \Exception
     */
    public static function getLinked($user_id, $fromPrices = false)
    {
        $instance = (new static)::from('condicaopagamentoimportacoes as imp')
            ->join("condicaopagamentos as cond", "cond.id", "imp.condicaopagamento_id")
            ->whereUserId($user_id)->whereRaw("imp.ativo = 1");

        if ($fromPrices) {
            $instance->selectRaw("imp.id, cond.descricao");
        } else {
            $instance->selectRaw("imp.condicaopagamento_id, imp.erp_id, cond.descricao as apiDescricao");
        }

        return $instance->orderBy("cond.descricao")->get();
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
}

