<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Vendaativaocorrencia
 *
 * @property int $CLIENTE_ID
 * @property string|null $CREATED_AT
 * @property string $DATAHORA
 * @property int $ID
 * @property string|null $OBSERVACAO
 * @property string|null $UPDATED_AT
 * @property int $USER_ID
 * @property int $VENDAATIVAOCORRENCIATIPO_ID
 * @property-read \App\Cliente $cliente
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Vendaativacliente[] $vendaAtivaCliente
 * @property-read \App\Vendaativaocorrenciatipo $vendaAtivaOcorreciaTipo
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrencia whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrencia whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrencia whereDATAHORA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrencia whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrencia whereOBSERVACAO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrencia whereUPDATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrencia whereUSERID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Vendaativaocorrencia whereVENDAATIVAOCORRENCIATIPOID($value)
 * @mixin \Eloquent
 */
class Vendaativaocorrencia extends Model
{

    protected $fillable = ['user_id', 'cliente_id', 'vendaativaocorrenciatipo_id',
        'datahora', 'observacao'];

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function vendaAtivaOcorreciaTipo()
    {
        return $this->belongsTo('App\Vendaativaocorrenciatipo');
    }

    public function vendaAtivaCliente()
    {
        return $this->hasMany('App\Vendaativacliente');
    }
}
