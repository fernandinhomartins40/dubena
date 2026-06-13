<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 20/07/2018
 * Time: 15:02
 */

namespace App\Repository;

use App\PedidoAvaliacao;
use Illuminate\Support\Collection;

/**
 * Class PedidoAvaliacaoRepository
 * @package App\Repository
 * @method static $this|\Eloquent|Collection updateOrCreate($data)
 * @method static $this|\Eloquent|Collection create($data)
 * @method static $this|\Eloquent|Collection find(int $id)
 * @method static $this|\Eloquent|Collection wherePedidoId($pedido_id)
 */
class PedidoAvaliacaoRepository extends BaseRepository
{
    /**
     * PedidoAvaliacaoRepository constructor.
     */
    public function __construct()
    {
        parent::__construct(PedidoAvaliacao::class);
    }

    /**
     * @param $pedido_id
     * @return mixed
     */
    public static function getByOrder($pedido_id)
    {
        return static::wherePedidoId($pedido_id)->get();
    }

    /**
     * @param $pedido_id
     * @return bool
     */
    public static function hasWithOrder($pedido_id)
    {
        return static::wherePedidoId($pedido_id)->first() !== null;
    }
}