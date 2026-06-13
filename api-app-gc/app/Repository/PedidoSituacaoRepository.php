<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 02/08/2018
 * Time: 13:25
 */

namespace App\Repository;


use App\PedidoSituacao;
use Illuminate\Support\Collection;

/**
 *
 * @mixin PedidoSituacao
 */
class PedidoSituacaoRepository extends BaseRepository
{

    /**
     * PedidoSituacaoRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(PedidoSituacao::class);
    }

    /**
     * @return mixed
     */
    public static function getToLink()
    {
        return static::select("descricao", "id", "pendente", "cancelado", "entregue")
            ->whereAtivo(true)->orderBy("descricao")->get();
    }
}