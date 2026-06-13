<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Pedidosituacaohistorico
 *
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property int $ID
 * @property int $PEDIDO_ID
 * @property int|null $PEDIDOMOTIVOATRASO_ID
 * @property int $PEDIDOSITUACAO_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Pedido $pedido
 * @property-read \App\Pedidomotivoatraso $pedidoMotivoAtraso
 * @property-read \App\Pedidomotivoatraso $pedidoSituacao
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacaohistorico whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacaohistorico whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacaohistorico whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacaohistorico wherePEDIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacaohistorico wherePEDIDOMOTIVOATRASOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacaohistorico wherePEDIDOSITUACAOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacaohistorico whereUPDATEDAT($value)
 * @mixin \Eloquent
 * @property int|null $APIPEDIDO_ID
 * @property string|null $ENVIADOAPI
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacaohistorico whereAPIPEDIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Pedidosituacaohistorico whereENVIADOAPI($value)
 */
class Pedidosituacaohistorico extends Model
{

    protected $fillable = ['pedido_id', 'pedidosituacao_id', 'pedidomotivoatraso_id', 'datahora', 'apipedido_id', 'enviadoapi'];

    function pedido()
    {
        return $this->belongsTo('App\Pedido');
    }

    function pedidoSituacao()
    {
        return $this->belongsTo('App\Pedidomotivoatraso');
    }

    function pedidoMotivoAtraso()
    {
        return $this->belongsTo('App\Pedidomotivoatraso');
    }
}
