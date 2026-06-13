<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Vendaativacliente
 *
 * @property int $CLIENTE_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property int $ID
 * @property string $LIGARNOVAMENTE
 * @property int|null $PEDIDO_ID
 * @property string|null $PREVISAOPROXCOMPRA
 * @property string|null $UPDATED_AT
 * @property int $VENDAATIVA_ID
 * @property int|null $VENDAATIVAOCORRENCIA_ID
 * @property-read \App\Cliente $cliente
 * @property-read \App\Pedido $pedido
 * @property-read \App\Vendaativa $vendaAtiva
 * @property-read \App\Vendaativaocorrencia $vendaAtivaOcorrencia
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativacliente whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativacliente whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativacliente whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativacliente whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativacliente whereLIGARNOVAMENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativacliente wherePEDIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativacliente wherePREVISAOPROXCOMPRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativacliente whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativacliente whereVENDAATIVAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativacliente whereVENDAATIVAOCORRENCIAID($value)
 * @mixin \Eloquent
 */
class Vendaativacliente extends Model
{

    protected $fillable = ['vendaativa_id', 'cliente_id', 'pedido_id', 'vendaativaocorrencia_id',
        'datahora', 'ligarnovamente','previsaoproxcompra'];

    public function vendaAtiva()
    {
        return $this->belongsTo('App\VendaAtiva');
    }

    public function cliente()
    {
        return $this->belongsTo('App\Cliente','cliente_id');
    }

    public function pedido()
    {
        return $this->belongsTo('App\Pedido');
    }

    public function vendaAtivaOcorrencia()
    {
        return $this->belongsTo('App\Vendaativaocorrencia');
    }

}
