<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\PedidoItem
 *
 * @mixin \Eloquent
 * @property int $id
 * @property int $pedido_id
 * @property int $produto_id
 * @property float $quantidade
 * @property float $precovendaunitario
 * @property float $precovendatotal
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoItem wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoItem wherePrecovendatotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoItem wherePrecovendaunitario($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoItem whereProdutoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoItem whereQuantidade($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoItem whereUpdatedAt($value)
 * @property string $codigogb
 * @property-read \App\Produto $produtos
 * @method static \Illuminate\Database\Eloquent\Builder|\App\PedidoItem whereCodigogb($value)
 * @property-read \App\Produto $pedido
 */
class PedidoItem extends ApiModel
{
    protected $table = 'pedidoitens';

    protected $fillable = [
        'quantidade', 'precovendaunitario', 'precovendatotal', 'pedido_id', 'produto_id', 'codigogb'
    ];

    public function produtos()
    {
        return $this->belongsTo(Produto::class, "produto_id");
    }

    public function pedido()
    {
        return $this->belongsTo(Produto::class, "pedido_id");
    }

}


