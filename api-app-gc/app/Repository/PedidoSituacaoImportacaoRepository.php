<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 02/08/2018
 * Time: 13:25
 */

namespace App\Repository;


use App\PedidoSituacaoImportacao;

/**
 * @method static $this whereIn(string $string, $value)
 * @method static $this whereAtivo(string $string)
 * @mixin PedidoSituacaoImportacao
 */
class PedidoSituacaoImportacaoRepository extends BaseRepository
{

    /**
     * PedidoSituacaoImportacaoRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(PedidoSituacaoImportacao::class);
    }

    /**
     * @param $user_id
     * @return mixed
     * @throws \Exception
     */
    public static function getLinked($user_id)
    {
        return (new static)::from('pedidosituacaoimportacoes as imp')
            ->join("pedidosituacoes as sit", "sit.id", "imp.pedidosituacao_id")
            ->whereUserId($user_id)
            ->whereRaw("imp.ativo = 1")
            ->selectRaw("imp.pedidosituacao_id, imp.erp_id, sit.descricao as apiDescricao")
            ->get();
    }
}