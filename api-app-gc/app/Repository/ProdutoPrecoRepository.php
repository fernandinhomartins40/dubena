<?php /** @noinspection PhpDynamicAsStaticMethodCallInspection */

/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 25/07/2018
 * Time: 14:32
 */

namespace App\Repository;

use App\ProdutoCondicaoPagamento;

/**
 * Class ProdutoPrecoRepository
 * @package App\Repository
 */
class ProdutoPrecoRepository extends BaseRepository
{

    /**
     * GeneralConfigRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(ProdutoCondicaoPagamento::class);
    }

    public static function link($data, $user_id)
    {
        $actives = collect([]);
        foreach ($data as $d) {
            $toUpdate = [
                "valor" => $d["valor"]
            ];
            $d["user_id"] = $user_id;
            unset($d["valor"]);
            $actives->push(static::updateOrCreate($d, $toUpdate));
        }
        static::deleteNotUsed($actives->pluck("id"), $user_id);
    }

    /**
     * @param $user_id
     * @return mixed
     * @throws \Exception
     */
    public static function getLinked($user_id)
    {
        $payments = static::from('produtocondicaopagamentos as pc')
            ->join('condicaopagamentoimportacoes as condimp', 'condimp.id', 'pc.condicaopagamentoimportacao_id')
            ->join('produtoimportacoes as prodimp', 'prodimp.id', 'pc.produtoimportacao_id')
            ->join('produtos as p', 'p.id', 'prodimp.produto_id')
            ->join('condicaopagamentos as cond', 'cond.id', 'condimp.condicaopagamento_id')
                ->selectRaw("valor as preco, condimp.id as condicaopagamento_id, " .
                "prodimp.id as produto_id, p.descricao as prodDescricao, cond.descricao as condDescricao")
            ->where("condimp.ativo", true)
            ->where("prodimp.ativo", true)
            ->where("condimp.user_id", $user_id)
            ->where("prodimp.user_id", $user_id)
            ->get();

        return $payments;
    }
}