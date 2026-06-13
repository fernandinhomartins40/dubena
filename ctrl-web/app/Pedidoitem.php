<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Pedidoitem
 *
 * @property string|null $CREATED_AT
 * @property float $CUSTOMEDIO
 * @property int $ID
 * @property int $PEDIDO_ID
 * @property float $PRECOVENDATOTAL
 * @property float $PRECOVENDAUNITARIO
 * @property int $PRODUTO_ID
 * @property float $QUANTIDADE
 * @property string|null $UPDATED_AT
 * @property-read \App\Pedido $pedido
 * @property-read \App\Produto $produto
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidoitem whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidoitem whereCUSTOMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidoitem whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidoitem wherePEDIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidoitem wherePRECOVENDATOTAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidoitem wherePRECOVENDAUNITARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidoitem wherePRODUTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidoitem whereQUANTIDADE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidoitem whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Pedidoitem extends Model
{

    protected $fillable = ['pedido_id', 'produto_id', 'quantidade', 'precovendaunitario',
        'precovendatotal'];

    public function pedido()
    {
        return $this->belongsTo('App\Pedido', 'pedido_id');
    }

    public function produto()
    {
        return $this->belongsTo('App\Produto');
    }

}
