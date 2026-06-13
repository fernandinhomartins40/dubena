<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Conveniofechamentopedido
 *
 * @property int $CLIENTE_ID
 * @property int $CONVENIOFECHAMENTO_ID
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property int $PEDIDO_ID
 * @property string $PEDIDODATA
 * @property float $PEDIDOVALOR
 * @property string|null $UPDATED_AT
 * @property-read \App\Cliente $cliente
 * @property-read \App\Conveniofechamento $convenioFechamento
 * @property-read \App\Pedido $pedido
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamentopedido whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamentopedido whereCONVENIOFECHAMENTOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamentopedido whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamentopedido whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamentopedido wherePEDIDODATA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamentopedido wherePEDIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamentopedido wherePEDIDOVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Conveniofechamentopedido whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Conveniofechamentopedido extends Model
{
    protected $fillable = [
      'conveniofechamento_id',
      'pedido_id',
      'cliente_id',
      'pedidodata',
      'pedidovalor'
    ];

    public function cliente(){
      return $this->belongsTo('App\Cliente');
    }

    public function convenioFechamento(){
      return $this->belongsTo('App\Conveniofechamento');
    }

    public function pedido(){
      return $this->belongsTo('App\Pedido','pedido_id');
    }
}
