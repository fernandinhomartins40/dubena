<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Veiculoentradasaidapedidos
 *
 * @property string|null $CREATED_AT
 * @property int $ENTRADASAIDA_ID
 * @property int $ID
 * @property int $PEDIDO_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Veiculoentradasaida $entradasaida
 * @property-read \App\Pedido $pedidos
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaidapedidos whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaidapedidos whereENTRADASAIDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaidapedidos whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaidapedidos wherePEDIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Veiculoentradasaidapedidos whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Veiculoentradasaidapedidos extends Model
{
    protected $fillable = ["entradasaida_id","pedido_id"];

    public function pedidos(){
        return $this->belongsTo('App\Pedido','pedido_id');
    }

    public function entradasaida(){
        return $this->belongsTo('App\Veiculoentradasaida','entradasaida_id');
    }
}
