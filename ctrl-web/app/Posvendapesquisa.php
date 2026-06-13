<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Posvendapesquisa
 *
 * @property int|null $CLIENTE_ID
 * @property string|null $CREATED_AT
 * @property string|null $DATAHORA
 * @property int $ID
 * @property string|null $OBSERVACAO
 * @property int|null $PEDIDO_ID
 * @property int $POSVENDA_ID
 * @property int|null $SETOR_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Cliente $cliente
 * @property-read \App\Pedido $pedido
 * @property-read \App\Posvenda $posVenda
 * @property-read \App\Setor $setor
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisa whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisa whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisa whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisa whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisa whereOBSERVACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisa wherePEDIDOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisa wherePOSVENDAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisa whereSETORID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Posvendapesquisa whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Posvendapesquisa extends Model
{

    protected $fillable = ['cliente_id', 'setor_id', 'pedido_id', 'posvenda_id', 'datahora', 'observacao'];

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function setor()
    {
        return $this->belongsTo('App\Setor');
    }

    public function pedido()
    {
        return $this->belongsTo('App\Pedido');
    }

    public function posVenda()
    {
        return $this->belongsTo('App\Posvenda');
    }

}
