<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Clienteconveniodependente
 *
 * @property string|null $ATIVO
 * @property int $CLIENTE_ID
 * @property string|null $CREATED_AT
 * @property int $ID
 * @property string $NOME
 * @property int $PARENTESCO_ID
 * @property string|null $UPDATED_AT
 * @property-read \App\Cliente $cliente
 * @property-read \App\Parentesco $parentesco
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconveniodependente whereATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconveniodependente whereCLIENTEID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconveniodependente whereCREATEDAT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconveniodependente whereID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconveniodependente whereNOME($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconveniodependente wherePARENTESCOID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Clienteconveniodependente whereUPDATEDAT($value)
 * @mixin \Eloquent
 */
class Clienteconveniodependente extends Model
{
    protected $fillable = [
        'cliente_id',
        'parentesco_id',
        'nome',
        'ativo',
    ];

    public function cliente()
    {
        return $this->belongsTo('App\Cliente');
    }

    public function parentesco()
    {
        return $this->belongsTo('App\Parentesco');
    }

}
